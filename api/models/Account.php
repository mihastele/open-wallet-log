<?php
/**
 * Account Model
 */
class Account {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id, $userId = null) {
        $sql = "SELECT a.*, u.firstname, u.lastname 
                FROM accounts a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetch($sql, $params);
    }
    
    public function getByUserId($userId) {
        return $this->db->fetchAll(
            "SELECT a.*, 
                    (SELECT COUNT(*) FROM transactions WHERE account_id = a.id) as transaction_count
             FROM accounts a 
             WHERE a.user_id = ? AND a.status = 'active'
             ORDER BY a.created_at DESC",
            [$userId]
        );
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT a.*, u.email, u.firstname, u.lastname 
                FROM accounts a 
                JOIN users u ON a.user_id = u.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['type'])) {
            $sql .= " AND a.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY a.created_at DESC LIMIT ?, ?";
        $params[] = $filters['offset'] ?? 0;
        $params[] = $filters['limit'] ?? 50;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate unique account number
            $accountNumber = $this->generateAccountNumber();
            
            $accountId = $this->db->insert(
                "INSERT INTO accounts (user_id, account_number, name, type, currency, balance, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
                [
                    $data['user_id'],
                    $accountNumber,
                    $data['name'] ?? null,
                    $data['type'],
                    $data['currency'],
                    $data['initial_deposit'] ?? 0
                ]
            );
            
            // If initial deposit, create transaction
            if (!empty($data['initial_deposit']) && $data['initial_deposit'] > 0) {
                $this->db->insert(
                    "INSERT INTO transactions (user_id, account_id, type, amount, description, status, created_at) 
                     VALUES (?, ?, 'deposit', ?, 'Initial deposit', 'completed', NOW())",
                    [$data['user_id'], $accountId, $data['initial_deposit']]
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'account' => $this->getById($accountId)
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function updateBalance($accountId, $amount) {
        return $this->db->update(
            "UPDATE accounts SET balance = balance + ?, updated_at = NOW() WHERE id = ?",
            [$amount, $accountId]
        ) > 0;
    }
    
    public function getBalance($userId, $accountId = null) {
        if ($accountId) {
            $result = $this->db->fetch(
                "SELECT balance FROM accounts WHERE id = ? AND user_id = ? LIMIT 1",
                [$accountId, $userId]
            );
            return $result ? (float)$result['balance'] : 0;
        }
        
        $result = $this->db->fetch(
            "SELECT SUM(balance) as total FROM accounts WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
        return (float)($result['total'] ?? 0);
    }
    
    public function getBalanceByType($userId, $type) {
        $result = $this->db->fetch(
            "SELECT SUM(balance) as total FROM accounts WHERE user_id = ? AND type = ? AND status = 'active'",
            [$userId, $type]
        );
        return (float)($result['total'] ?? 0);
    }
    
    public function getTotalBalance($userId) {
        return $this->getBalance($userId);
    }
    
    public function close($accountId, $userId) {
        // Check if account has balance
        $account = $this->getById($accountId, $userId);
        if (!$account) {
            return ['success' => false, 'message' => 'Account not found'];
        }
        
        if ($account['balance'] != 0) {
            return ['success' => false, 'message' => 'Account must have zero balance to close'];
        }
        
        // Check for pending transactions
        $pending = $this->db->fetch(
            "SELECT COUNT(*) as count FROM transactions 
             WHERE account_id = ? AND status IN ('pending', 'processing')",
            [$accountId]
        );
        
        if ($pending['count'] > 0) {
            return ['success' => false, 'message' => 'Account has pending transactions'];
        }
        
        $this->db->update(
            "UPDATE accounts SET status = 'closed', closed_at = NOW() WHERE id = ?",
            [$accountId]
        );
        
        return ['success' => true];
    }
    
    public function getBalanceHistory($userId, $accountId = null, $months = 12) {
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                       SUM(CASE WHEN type IN ('deposit', 'transfer_in') THEN amount ELSE 0 END) as deposits,
                       SUM(CASE WHEN type IN ('withdrawal', 'transfer_out', 'payment') THEN amount ELSE 0 END) as withdrawals,
                       COUNT(*) as transaction_count
                FROM transactions 
                WHERE user_id = ? AND status = 'completed'";
        $params = [$userId];
        
        if ($accountId) {
            $sql .= " AND account_id = ?";
            $params[] = $accountId;
        }
        
        $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                  GROUP BY month 
                  ORDER BY month";
        $params[] = $months;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getAccountCount($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM accounts WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
        return (int)$result['count'];
    }
    
    public function getTotalCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM accounts");
        return (int)$result['count'];
    }
    
    public function getSystemTotalBalance() {
        $result = $this->db->fetch(
            "SELECT SUM(balance) as total FROM accounts WHERE status = 'active'"
        );
        return (float)($result['total'] ?? 0);
    }
    
    private function generateAccountNumber() {
        $prefix = 'FP';
        $timestamp = substr(time(), -8);
        $random = random_int(1000, 9999);
        return $prefix . $timestamp . $random;
    }
    
    public function hasSufficientFunds($accountId, $amount) {
        $result = $this->db->fetch(
            "SELECT balance FROM accounts WHERE id = ? LIMIT 1",
            [$accountId]
        );
        
        if (!$result) {
            return false;
        }
        
        return (float)$result['balance'] >= (float)$amount;
    }
}
