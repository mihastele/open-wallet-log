<?php
/**
 * RateLimiter Class - API rate limiting
 */
class RateLimiter {
    private $db;
    private $maxRequests;
    private $window;
    
    public function __construct($maxRequests = null, $window = null) {
        $this->db = Database::getInstance();
        $this->maxRequests = $maxRequests ?? RATE_LIMIT_REQUESTS;
        $this->window = $window ?? RATE_LIMIT_WINDOW;
    }
    
    public function check($identifier) {
        $this->cleanup();
        
        $count = $this->getRequestCount($identifier);
        
        if ($count >= $this->maxRequests) {
            return false;
        }
        
        $this->increment($identifier);
        return true;
    }
    
    public function getRemaining($identifier) {
        $this->cleanup();
        $count = $this->getRequestCount($identifier);
        return max(0, $this->maxRequests - $count);
    }
    
    public function getResetTime($identifier) {
        $oldest = $this->db->fetch(
            "SELECT MIN(created_at) as oldest FROM rate_limits 
             WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$identifier, $this->window]
        );
        
        if ($oldest['oldest']) {
            $oldestTime = strtotime($oldest['oldest']);
            return $oldestTime + $this->window;
        }
        
        return time();
    }
    
    private function getRequestCount($identifier) {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as count FROM rate_limits 
             WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$identifier, $this->window]
        );
        
        return (int)$result['count'];
    }
    
    private function increment($identifier) {
        $this->db->insert(
            "INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())",
            [$identifier]
        );
    }
    
    private function cleanup() {
        // Remove old entries periodically (1% chance)
        if (random_int(1, 100) === 1) {
            $this->db->query(
                "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$this->window * 2]
            );
        }
    }
    
    public function getHeaders($identifier) {
        $remaining = $this->getRemaining($identifier);
        $reset = $this->getResetTime($identifier);
        
        return [
            'X-RateLimit-Limit' => $this->maxRequests,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset
        ];
    }
}
