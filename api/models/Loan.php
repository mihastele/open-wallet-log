<?php
/**
 * Loan Model
 */
class Loan {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id, $userId = null) {
        $sql = "SELECT l.*, u.firstname, u.lastname, u.email
                FROM loans l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND l.user_id = ?";
            $params[] = $userId;
        }
        
        $loan = $this->db->fetch($sql, $params);
        
        if ($loan) {
            $loan['progress'] = $this->calculateProgress($loan['amount'], $loan['amount_paid']);
        }
        
        return $loan;
    }
    
    public function getByUserId($userId) {
        $loans = $this->db->fetchAll(
            "SELECT l.*, 
                    (SELECT COUNT(*) FROM loan_payments WHERE loan_id = l.id) as payment_count
             FROM loans l 
             WHERE l.user_id = ?
             ORDER BY l.created_at DESC",
            [$userId]
        );
        
        foreach ($loans as &$loan) {
            $loan['progress'] = $this->calculateProgress($loan['amount'], $loan['amount_paid']);
        }
        
        return $loans;
    }
    
    public function getPending($filters = []) {
        $sql = "SELECT l.*, u.firstname, u.lastname, u.email
                FROM loans l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.status = 'pending'";
        $params = [];
        
        $sql .= " ORDER BY l.created_at DESC LIMIT ?, ?";
        $params[] = $filters['offset'] ?? 0;
        $params[] = $filters['limit'] ?? 50;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function apply($data) {
        try {
            // Calculate interest rate based on loan type
            $interestRate = $this->getInterestRate($data['type']);
            
            // Calculate monthly payment
            $monthlyPayment = $this->calculateMonthlyPayment(
                $data['amount'],
                $data['term_months'],
                $interestRate
            );
            
            $loanId = $this->db->insert(
                "INSERT INTO loans (user_id, amount, interest_rate, term_months, 
                 monthly_payment, purpose, type, employment_status, income, 
                 status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [
                    $data['user_id'],
                    $data['amount'],
                    $interestRate,
                    $data['term_months'],
                    $monthlyPayment,
                    $data['purpose'],
                    $data['type'],
                    $data['employment_status'] ?? null,
                    $data['income'] ?? null
                ]
            );
            
            return [
                'success' => true,
                'loan' => $this->getById($loanId)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function processApplication($loanId, $action, $reason = '') {
        $loan = $this->getById($loanId);
        
        if (!$loan) {
            return ['success' => false, 'message' => 'Loan not found'];
        }
        
        if ($loan['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Loan has already been processed'];
        }
        
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        $this->db->update(
            "UPDATE loans SET status = ?, reviewed_by = ?, reviewed_at = NOW(), 
             review_notes = ? WHERE id = ?",
            [$status, $_SESSION['user_id'] ?? null, $reason, $loanId]
        );
        
        // If approved, create a loan account or disburse funds
        if ($action === 'approve') {
            $this->disburseLoan($loanId, $loan);
        }
        
        return ['success' => true];
    }
    
    private function disburseLoan($loanId, $loan) {
        // In a real system, this would transfer funds to user's account
        // For now, we'll just create a deposit transaction
        
        $account = $this->db->fetch(
            "SELECT id FROM accounts WHERE user_id = ? AND type = 'checking' AND status = 'active' LIMIT 1",
            [$loan['user_id']]
        );
        
        if ($account) {
            $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, status, created_at) 
                 VALUES (?, ?, 'deposit', ?, ?, 'loan', 'completed', NOW())",
                [
                    $loan['user_id'],
                    $account['id'],
                    $loan['amount'],
                    'Loan disbursement - ' . ucfirst($loan['type'])
                ]
            );
            
            $this->db->update(
                "UPDATE accounts SET balance = balance + ? WHERE id = ?",
                [$loan['amount'], $account['id']]
            );
        }
    }
    
    public function makePayment($data) {
        try {
            $this->db->beginTransaction();
            
            $loan = $this->getById($data['loan_id'], $data['user_id']);
            
            if (!$loan) {
                throw new Exception('Loan not found');
            }
            
            if ($loan['status'] !== 'approved' && $loan['status'] !== 'active') {
                throw new Exception('Loan is not active');
            }
            
            // Check if account has sufficient funds
            $account = $this->db->fetch(
                "SELECT balance FROM accounts WHERE id = ? AND user_id = ? LIMIT 1",
                [$data['account_id'], $data['user_id']]
            );
            
            if (!$account) {
                throw new Exception('Account not found');
            }
            
            if ($account['balance'] < $data['amount']) {
                throw new Exception('Insufficient funds');
            }
            
            // Create payment record
            $paymentId = $this->db->insert(
                "INSERT INTO loan_payments (loan_id, amount, account_id, payment_date, created_at) 
                 VALUES (?, ?, ?, CURDATE(), NOW())",
                [$data['loan_id'], $data['amount'], $data['account_id']]
            );
            
            // Update loan amount paid
            $newAmountPaid = $loan['amount_paid'] + $data['amount'];
            $remainingBalance = $loan['amount'] - $newAmountPaid;
            
            // Check if loan is fully paid
            $newStatus = $remainingBalance <= 0 ? 'completed' : ($loan['status'] === 'approved' ? 'active' : $loan['status']);
            
            $this->db->update(
                "UPDATE loans SET amount_paid = ?, status = ?, updated_at = NOW() WHERE id = ?",
                [$newAmountPaid, $newStatus, $data['loan_id']]
            );
            
            // Deduct from account
            $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, status, created_at) 
                 VALUES (?, ?, 'payment', ?, ?, 'loan_payment', 'completed', NOW())",
                [
                    $data['user_id'],
                    $data['account_id'],
                    $data['amount'],
                    'Loan payment - ' . ucfirst($loan['type']) . ' loan'
                ]
            );
            
            $this->db->update(
                "UPDATE accounts SET balance = balance - ? WHERE id = ?",
                [$data['amount'], $data['account_id']]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment' => [
                    'id' => $paymentId,
                    'amount' => $data['amount'],
                    'remaining_balance' => max(0, $remainingBalance)
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getPaymentSchedule($loanId, $userId = null) {
        $loan = $this->getById($loanId, $userId);
        
        if (!$loan) {
            return [];
        }
        
        $schedule = [];
        $remainingBalance = $loan['amount'];
        $monthlyPayment = $loan['monthly_payment'];
        $monthlyInterest = $loan['interest_rate'] / 12;
        $startDate = new DateTime($loan['approved_at'] ?? $loan['created_at']);
        
        for ($i = 1; $i <= $loan['term_months']; $i++) {
            $interestPayment = $remainingBalance * $monthlyInterest;
            $principalPayment = $monthlyPayment - $interestPayment;
            $remainingBalance -= $principalPayment;
            
            $paymentDate = clone $startDate;
            $paymentDate->modify("+{$i} months");
            
            $schedule[] = [
                'payment_number' => $i,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'payment_amount' => round($monthlyPayment, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interestPayment, 2),
                'remaining_balance' => max(0, round($remainingBalance, 2))
            ];
        }
        
        return $schedule;
    }
    
    public function calculate($amount, $termMonths, $interestRate) {
        $monthlyRate = $interestRate / 12;
        
        if ($monthlyRate == 0) {
            $monthlyPayment = $amount / $termMonths;
        } else {
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / 
                             (pow(1 + $monthlyRate, $termMonths) - 1);
        }
        
        $totalPayment = $monthlyPayment * $termMonths;
        $totalInterest = $totalPayment - $amount;
        
        return [
            'monthly_payment' => round($monthlyPayment, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2),
            'interest_rate' => $interestRate
        ];
    }
    
    public function getActiveLoanCount($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM loans 
             WHERE user_id = ? AND status IN ('approved', 'active')",
            [$userId]
        );
        return (int)$result['count'];
    }
    
    public function getTotalLoanAmount($userId) {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM loans 
             WHERE user_id = ? AND status IN ('approved', 'active')",
            [$userId]
        );
        return (float)$result['total'];
    }
    
    public function getTotalOwed($userId) {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid), 0) as total FROM loans 
             WHERE user_id = ? AND status IN ('approved', 'active')",
            [$userId]
        );
        return (float)$result['total'];
    }
    
    public function getPendingCount() {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM loans WHERE status = 'pending'"
        );
        return (int)$result['count'];
    }
    
    public function getTotalIssued() {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM loans WHERE status IN ('approved', 'active', 'completed')"
        );
        return (float)$result['total'];
    }
    
    private function calculateProgress($total, $paid) {
        if ($total == 0) return 0;
        return min(100, round(($paid / $total) * 100, 2));
    }
    
    private function getInterestRate($type) {
        $rates = [
            'personal' => 0.0899,  // 8.99%
            'business' => 0.0699,  // 6.99%
            'mortgage' => 0.0459,  // 4.59%
            'auto' => 0.0399       // 3.99%
        ];
        
        return $rates[$type] ?? 0.0899;
    }
    
    private function calculateMonthlyPayment($amount, $termMonths, $interestRate) {
        $calculation = $this->calculate($amount, $termMonths, $interestRate);
        return $calculation['monthly_payment'];
    }
}
