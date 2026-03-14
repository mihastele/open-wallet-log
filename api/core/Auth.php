<?php
/**
 * Auth Class - Authentication and authorization
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($email, $password) {
        // Get user by email
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Log failed attempt
            $this->logFailedLogin($user['id']);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check if account is locked
        if ($user['failed_attempts'] >= 5 && 
            strtotime($user['locked_until']) > time()) {
            return ['success' => false, 'message' => 'Account temporarily locked. Try again later.'];
        }
        
        // Reset failed attempts
        $this->db->update(
            "UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Generate tokens
        $token = $this->generateToken($user);
        
        // Store session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        return [
            'success' => true,
            'token' => $token,
            'user' => $this->sanitizeUser($user),
            'expires' => time() + JWT_EXPIRES
        ];
    }
    
    public function register($data) {
        // Check if email exists
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$data['email']]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_ARGON2ID);
        
        // Generate verification token
        $verifyToken = bin2hex(random_bytes(32));
        
        try {
            $userId = $this->db->insert(
                "INSERT INTO users (firstname, lastname, email, phone, password, verify_token, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['firstname'],
                    $data['lastname'],
                    $data['email'],
                    $data['phone'],
                    $hashedPassword,
                    $verifyToken
                ]
            );
            
            // Send verification email (implement email sending)
            $this->sendVerificationEmail($data['email'], $verifyToken);
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function logout() {
        // Clear session
        $_SESSION = [];
        session_destroy();
        
        return ['success' => true];
    }
    
    public function verifyEmail($token) {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE verify_token = ? AND email_verified = 0 LIMIT 1",
            [$token]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }
        
        $this->db->update(
            "UPDATE users SET email_verified = 1, verify_token = NULL, status = 'active' WHERE id = ?",
            [$user['id']]
        );
        
        return ['success' => true];
    }
    
    public function sendPasswordReset($email) {
        $user = $this->db->fetch(
            "SELECT id, firstname FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        
        if (!$user) {
            // Don't reveal if email exists
            return ['success' => true];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->db->update(
            "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
            [$token, $expires, $user['id']]
        );
        
        // Send reset email
        $this->sendResetEmail($email, $token, $user['firstname']);
        
        return ['success' => true];
    }
    
    public function resetPassword($token, $newPassword) {
        $user = $this->db->fetch(
            "SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1",
            [$token]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        $this->db->update(
            "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [$hashedPassword, $user['id']]
        );
        
        return ['success' => true];
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetch(
            "SELECT password FROM users WHERE id = ? LIMIT 1",
            [$userId]
        );
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);
        
        $this->db->update(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );
        
        return ['success' => true];
    }
    
    public static function getCurrentUser() {
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            return $db->fetch(
                "SELECT id, email, firstname, lastname, role, status FROM users WHERE id = ? LIMIT 1",
                [$_SESSION['user_id']]
            );
        }
        
        // Check for bearer token
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            return self::validateToken($token);
        }
        
        return null;
    }
    
    private function generateToken($user) {
        // Simple token generation (in production, use JWT)
        $token = bin2hex(random_bytes(32));
        
        // Store token in database
        $expires = date('Y-m-d H:i:s', time() + JWT_EXPIRES);
        $this->db->insert(
            "INSERT INTO auth_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())",
            [$user['id'], $token, $expires]
        );
        
        return $token;
    }
    
    private static function validateToken($token) {
        $db = Database::getInstance();
        $result = $db->fetch(
            "SELECT u.* FROM users u 
             JOIN auth_tokens at ON u.id = at.user_id 
             WHERE at.token = ? AND at.expires_at > NOW() LIMIT 1",
            [$token]
        );
        
        return $result;
    }
    
    private function sanitizeUser($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'email_verified' => (bool)$user['email_verified'],
            'created_at' => $user['created_at']
        ];
    }
    
    private function logFailedLogin($userId) {
        $this->db->update(
            "UPDATE users SET failed_attempts = failed_attempts + 1, 
             locked_until = CASE WHEN failed_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) ELSE locked_until END 
             WHERE id = ?",
            [$userId]
        );
    }
    
    private function sendVerificationEmail($email, $token) {
        require_once __DIR__ . '/Mailer.php';
        $mailer = new Mailer();
        
        // Get user firstname for personalization
        $user = $this->db->fetch(
            "SELECT firstname FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
        
        $firstname = $user['firstname'] ?? 'User';
        
        $sent = $mailer->sendVerificationEmail($email, $token, $firstname);
        
        if (!$sent) {
            error_log("Failed to send verification email to {$email}");
        }
        
        return $sent;
    }
    
    private function sendResetEmail($email, $token, $name) {
        require_once __DIR__ . '/Mailer.php';
        $mailer = new Mailer();
        
        $sent = $mailer->sendPasswordResetEmail($email, $token, $name);
        
        if (!$sent) {
            error_log("Failed to send password reset email to {$email}");
        }
        
        return $sent;
    }
}
