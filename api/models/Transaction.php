<?php
/**
 * Transaction Model
 */
class Transaction {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id, $userId = null) {
        $sql = "SELECT t.*, a.account_number, a.type as account_type, a.currency
                FROM transactions t 
                JOIN accounts a ON t.account_id = a.id 
                WHERE t.id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND t.user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetch($sql, $params);
    }
    
    public function getByUserId($userId, $filters = []) {
        $sql = "SELECT t.*, a.account_number, a.type as account_type, a.currency
                FROM transactions t 
                JOIN accounts a ON t.account_id = a.id 
                WHERE t.user_id = ?";
        $params = [$userId];
        
        if (!empty($filters['account_id'])) {
            $sql .= " AND t.account_id = ?";
            $params[] = $filters['account_id'];
        }
        
        if (!empty($filters['type'])) {
            $sql .= " AND t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(t.created_at) >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(t.created_at) <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT ?, ?";
        $params[] = $filters['offset'] ?? 0;
        $params[] = $filters['limit'] ?? 50;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Verify account belongs to user
            $account = $this->db->fetch(
                "SELECT id, balance, status FROM accounts WHERE id = ? AND user_id = ? LIMIT 1",
                [$data['account_id'], $data['user_id']]
            );
            
            if (!$account) {
                throw new Exception('Account not found');
            }
            
            if ($account['status'] !== 'active') {
                throw new Exception('Account is not active');
            }
            
            // For withdrawals, check sufficient funds
            if ($data['type'] === 'withdrawal' && $account['balance'] < $data['amount']) {
                throw new Exception('Insufficient funds');
            }
            
            $reference = $this->generateReference();
            
            $transactionId = $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, reference, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())",
                [
                    $data['user_id'],
                    $data['account_id'],
                    $data['type'],
                    $data['amount'],
                    $data['description'] ?? '',
                    $data['category'] ?? 'uncategorized',
                    $reference
                ]
            );
            
            // Update account balance
            $balanceChange = $data['type'] === 'deposit' ? $data['amount'] : -$data['amount'];
            $this->db->update(
                "UPDATE accounts SET balance = balance + ?, updated_at = NOW() WHERE id = ?",
                [$balanceChange, $data['account_id']]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction' => $this->getById($transactionId)
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function transfer($data) {
        try {
            $this->db->beginTransaction();
            
            // Verify both accounts belong to user
            $fromAccount = $this->db->fetch(
                "SELECT id, balance, status FROM accounts WHERE id = ? AND user_id = ? LIMIT 1",
                [$data['from_account_id'], $data['user_id']]
            );
            
            if (!$fromAccount) {
                throw new Exception('Source account not found');
            }
            
            if ($fromAccount['status'] !== 'active') {
                throw new Exception('Source account is not active');
            }
            
            if ($fromAccount['balance'] < $data['amount']) {
                throw new Exception('Insufficient funds');
            }
            
            // Check if transfer is to own account or external
            $toAccount = $this->db->fetch(
                "SELECT id, status FROM accounts WHERE id = ? LIMIT 1",
                [$data['to_account_id']]
            );
            
            if (!$toAccount) {
                throw new Exception('Destination account not found');
            }
            
            if ($toAccount['status'] !== 'active') {
                throw new Exception('Destination account is not active');
            }
            
            $reference = $this->generateReference();
            
            // Debit from source
            $debitTx = $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, reference, status, related_account_id, created_at) 
                 VALUES (?, ?, 'transfer_out', ?, ?, 'transfer', ?, 'completed', ?, NOW())",
                [
                    $data['user_id'],
                    $data['from_account_id'],
                    $data['amount'],
                    $data['description'] ?? 'Transfer',
                    $reference,
                    $data['to_account_id']
                ]
            );
            
            // Credit to destination
            $creditTx = $this->db->insert(
                "INSERT INTO transactions (user_id, account_id, type, amount, description, 
                 category, reference, status, related_account_id, created_at) 
                 VALUES (?, ?, 'transfer_in', ?, ?, 'transfer', ?, 'completed', ?, NOW())",
                [
                    $data['user_id'],
                    $data['to_account_id'],
                    $data['amount'],
                    $data['description'] ?? 'Transfer',
                    $reference,
                    $data['from_account_id']
                ]
            );
            
            // Update balances
            $this->db->update(
                "UPDATE accounts SET balance = balance - ?, updated_at = NOW() WHERE id = ?",
                [$data['amount'], $data['from_account_id']]
            );
            
            $this->db->update(
                "UPDATE accounts SET balance = balance + ?, updated_at = NOW() WHERE id = ?",
                [$data['amount'], $data['to_account_id']]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction' => $this->getById($debitTx)
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deposit($data) {
        $data['type'] = 'deposit';
        return $this->create($data);
    }
    
    public function withdraw($data) {
        $data['type'] = 'withdrawal';
        return $this->create($data);
    }
    
    public function getRecent($userId, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT t.*, a.account_number, a.type as account_type
             FROM transactions t 
             JOIN accounts a ON t.account_id = a.id 
             WHERE t.user_id = ?
             ORDER BY t.created_at DESC 
             LIMIT ?",
            [$userId, $limit]
        );
    }
    
    public function getRecentActivity($userId, $limit = 10) {
        return $this->getRecent($userId, $limit);
    }
    
    public function getMonthlyIncome($userId, $startDate, $endDate) {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM transactions 
             WHERE user_id = ? AND type IN ('deposit', 'transfer_in') 
             AND status = 'completed' 
             AND DATE(created_at) BETWEEN ? AND ?",
            [$userId, $startDate, $endDate]
        );
        return (float)$result['total'];
    }
    
    public function getMonthlyExpenses($userId, $startDate, $endDate) {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total 
             FROM transactions 
             WHERE user_id = ? AND type IN ('withdrawal', 'transfer_out', 'payment') 
             AND status = 'completed' 
             AND DATE(created_at) BETWEEN ? AND ?",
            [$userId, $startDate, $endDate]
        );
        return (float)$result['total'];
    }
    
    public function getMonthlyChange($userId) {
        $startOfMonth = date('Y-m-01');
        $today = date('Y-m-d');
        
        $income = $this->getMonthlyIncome($userId, $startOfMonth, $today);
        $expenses = $this->getMonthlyExpenses($userId, $startOfMonth, $today);
        
        return [
            'income' => $income,
            'expenses' => $expenses,
            'net' => $income - $expenses
        ];
    }
    
    public function getPendingCount($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM transactions 
             WHERE user_id = ? AND status IN ('pending', 'processing')",
            [$userId]
        );
        return (int)$result['count'];
    }
    
    public function getIncomeExpenseReport($userId, $period = 'monthly', $months = 12) {
        $groupBy = $period === 'monthly' ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE_FORMAT(created_at, '%Y-%u')";
        
        return $this->db->fetchAll(
            "SELECT {$groupBy} as period,
                    SUM(CASE WHEN type IN ('deposit', 'transfer_in') THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type IN ('withdrawal', 'transfer_out', 'payment') THEN amount ELSE 0 END) as expenses
             FROM transactions 
             WHERE user_id = ? AND status = 'completed'
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY period
             ORDER BY period",
            [$userId, $months]
        );
    }
    
    public function getSpendingByCategory($userId, $startDate, $endDate) {
        return $this->db->fetchAll(
            "SELECT category, SUM(amount) as total, COUNT(*) as count
             FROM transactions 
             WHERE user_id = ? AND type IN ('withdrawal', 'payment') 
             AND status = 'completed' 
             AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY category
             ORDER BY total DESC",
            [$userId, $startDate, $endDate]
        );
    }
    
    public function getTodayCount() {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM transactions WHERE DATE(created_at) = CURDATE()"
        );
        return (int)$result['count'];
    }
    
    public function getTodayVolume() {
        $result = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions 
             WHERE DATE(created_at) = CURDATE() AND status = 'completed'"
        );
        return (float)$result['total'];
    }
    
    private function generateReference() {
        return 'TXN' . date('Ymd') . strtoupper(substr(uniqid(), -8));
    }
}
