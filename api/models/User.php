<?php
/**
 * User Model
 */
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT id, email, firstname, lastname, phone, role, status, 
                    email_verified, created_at, last_login 
             FROM users WHERE id = ? LIMIT 1",
            [$id]
        );
    }
    
    public function getByEmail($email) {
        return $this->db->fetch(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT id, email, firstname, lastname, phone, role, status, 
                       email_verified, created_at, last_login 
                FROM users WHERE 1=1";
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params = array_merge($params, [$search, $search, $search]);
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?, ?";
        $params[] = $filters['offset'] ?? 0;
        $params[] = $filters['limit'] ?? 50;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function update($id, $data) {
        $allowedFields = ['firstname', 'lastname', 'phone', 'avatar'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        return $this->db->update($sql, $params) > 0;
    }
    
    public function updateStatus($id, $status) {
        return $this->db->update(
            "UPDATE users SET status = ? WHERE id = ?",
            [$status, $id]
        ) > 0;
    }
    
    public function updateRole($id, $role) {
        return $this->db->update(
            "UPDATE users SET role = ? WHERE id = ?",
            [$role, $id]
        ) > 0;
    }
    
    public function delete($id) {
        return $this->db->delete(
            "DELETE FROM users WHERE id = ?",
            [$id]
        ) > 0;
    }
    
    public function getTotalCount() {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM users");
        return (int)$result['count'];
    }
    
    public function getActiveToday() {
        $result = $this->db->fetch(
            "SELECT COUNT(DISTINCT user_id) as count FROM auth_tokens 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        return (int)$result['count'];
    }
    
    public function exists($email) {
        $result = $this->db->fetch(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        return $result !== false;
    }
}
