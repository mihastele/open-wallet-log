<?php
/**
 * Security Class - Security utilities
 */
class Security {
    
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateCSRFToken() {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = self::generateToken();
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function encrypt($data, $key = null) {
        if ($key === null) {
            $key = JWT_SECRET;
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $key, 0, $iv, $tag);
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    public static function decrypt($data, $key = null) {
        if ($key === null) {
            $key = JWT_SECRET;
        }
        
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        
        return openssl_decrypt($ciphertext, 'AES-256-GCM', $key, 0, $iv, $tag);
    }
    
    public static function maskAccountNumber($accountNumber) {
        $length = strlen($accountNumber);
        if ($length <= 4) {
            return $accountNumber;
        }
        return str_repeat('*', $length - 4) . substr($accountNumber, -4);
    }
    
    public static function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 4)) . substr($local, -2);
        
        return $maskedLocal . '@' . $domain;
    }
    
    public static function generateAccountNumber() {
        // Generate a unique account number
        $prefix = 'FP';
        $timestamp = time();
        $random = random_int(1000, 9999);
        return $prefix . $timestamp . $random;
    }
    
    public static function rateLimitCheck($identifier, $maxAttempts = 5, $window = 300) {
        $db = Database::getInstance();
        
        // Clean old entries
        $db->query(
            "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$window]
        );
        
        // Count attempts
        $attempts = $db->fetch(
            "SELECT COUNT(*) as count FROM rate_limits WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$identifier, $window]
        );
        
        if ($attempts['count'] >= $maxAttempts) {
            return false;
        }
        
        // Log attempt
        $db->insert(
            "INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())",
            [$identifier]
        );
        
        return true;
    }
    
    public static function logSecurityEvent($event, $userId = null, $details = null) {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $db->insert(
            "INSERT INTO security_logs (event, user_id, ip_address, user_agent, details, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$event, $userId, $ip, $userAgent, json_encode($details)]
        );
    }
    
    public static function validateFileUpload($file, $allowedTypes = null, $maxSize = null) {
        if ($allowedTypes === null) {
            $allowedTypes = ALLOWED_EXTENSIONS;
        }
        if ($maxSize === null) {
            $maxSize = MAX_UPLOAD_SIZE;
        }
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'application/pdf' => ['pdf']
        ];
        
        $validMime = false;
        foreach ($allowedMimes as $mime => $extensions) {
            if (in_array($ext, $extensions) && $mimeType === $mime) {
                $validMime = true;
                break;
            }
        }
        
        if (!$validMime) {
            return ['valid' => false, 'error' => 'Invalid file content'];
        }
        
        return ['valid' => true];
    }
    
    public static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    public static function generateSecurePIN($length = 4) {
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= random_int(0, 9);
        }
        return $pin;
    }
    
    public static function verifyRecaptcha($response, $secret = null) {
        if ($secret === null) {
            return true; // Skip if not configured
        }
        
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret,
            'response' => $response,
            'remoteip' => self::getClientIP()
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $result = json_decode($result, true);
        
        return $result['success'] ?? false;
    }
}
