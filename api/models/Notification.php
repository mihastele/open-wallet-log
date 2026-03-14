<?php
/**
 * Notification Model
 */
class Notification {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id, $userId = null) {
        $sql = "SELECT * FROM notifications WHERE id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetch($sql, $params);
    }
    
    public function getByUserId($userId, $limit = 20, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function create($data) {
        return $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, data, is_read, created_at) 
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'],
                isset($data['data']) ? json_encode($data['data']) : null
            ]
        );
    }
    
    public function markRead($notificationId, $userId) {
        return $this->db->update(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        ) > 0;
    }
    
    public function markAllRead($userId) {
        return $this->db->update(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        ) > 0;
    }
    
    public function getUnreadCount($userId) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM notifications 
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return (int)$result['count'];
    }
    
    public function delete($notificationId, $userId) {
        return $this->db->delete(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        ) > 0;
    }
    
    public function deleteAll($userId) {
        return $this->db->delete(
            "DELETE FROM notifications WHERE user_id = ?",
            [$userId]
        ) > 0;
    }
    
    public function cleanup($days = 30) {
        return $this->db->delete(
            "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        ) > 0;
    }
    
    // Helper methods for creating specific notification types
    
    public function notifyTransaction($userId, $transactionType, $amount, $description) {
        $titles = [
            'deposit' => 'Deposit Received',
            'withdrawal' => 'Withdrawal Completed',
            'transfer' => 'Transfer Completed',
            'payment' => 'Payment Processed'
        ];
        
        return $this->create([
            'user_id' => $userId,
            'type' => 'transaction',
            'title' => $titles[$transactionType] ?? 'Transaction Update',
            'message' => sprintf('%s of $%.2f - %s', 
                ucfirst($transactionType), 
                $amount, 
                $description
            ),
            'data' => ['amount' => $amount, 'type' => $transactionType]
        ]);
    }
    
    public function notifyLowBalance($userId, $accountName, $balance) {
        return $this->create([
            'user_id' => $userId,
            'type' => 'alert',
            'title' => 'Low Balance Alert',
            'message' => sprintf('Your account "%s" balance is low: $%.2f', $accountName, $balance),
            'data' => ['account_name' => $accountName, 'balance' => $balance]
        ]);
    }
    
    public function notifyLoanUpdate($userId, $loanId, $status) {
        $messages = [
            'approved' => 'Your loan application has been approved!',
            'rejected' => 'Your loan application was not approved.',
            'completed' => 'Your loan has been fully paid off!'
        ];
        
        return $this->create([
            'user_id' => $userId,
            'type' => 'loan',
            'title' => 'Loan Update',
            'message' => $messages[$status] ?? 'Your loan status has been updated.',
            'data' => ['loan_id' => $loanId, 'status' => $status]
        ]);
    }
    
    public function notifySecurityAlert($userId, $event, $details) {
        return $this->create([
            'user_id' => $userId,
            'type' => 'security',
            'title' => 'Security Alert',
            'message' => sprintf('Security event: %s. %s', $event, $details),
            'data' => ['event' => $event, 'details' => $details]
        ]);
    }
    
    public function notifyInvestmentAlert($userId, $symbol, $change) {
        $changeType = $change >= 0 ? 'up' : 'down';
        
        return $this->create([
            'user_id' => $userId,
            'type' => 'investment',
            'title' => 'Stock Alert: ' . $symbol,
            'message' => sprintf('%s is %s %.2f%%', $symbol, $changeType, abs($change)),
            'data' => ['symbol' => $symbol, 'change' => $change]
        ]);
    }
}
