<?php
/**
 * Open Wallet Log - Configuration
 */

// Database configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'finpro');
    define('DB_USER', $_ENV['DB_USER'] ?? 'root');
    define('DB_PASS', $_ENV['DB_PASS'] ?? '');
    define('DB_CHARSET', 'utf8mb4');
}

// Application configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Open Wallet Log');
    define('APP_VERSION', '1.0.0');
    define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
    define('APP_DEBUG', ($_ENV['APP_DEBUG'] ?? 'true') === 'true');
    define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
}

// Security configuration
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production');
    define('JWT_EXPIRES', 86400); // 24 hours
    define('CSRF_TOKEN_NAME', 'csrf_token');
    define('SESSION_NAME', 'finpro_session');
}

// Rate limiting
if (!defined('RATE_LIMIT_REQUESTS')) {
    define('RATE_LIMIT_REQUESTS', 100);
    define('RATE_LIMIT_WINDOW', 60); // seconds
}

// File upload configuration
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 10485760); // 10MB
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
}

// Currency and formatting
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'USD');
    define('CURRENCY_SYMBOL', '$');
    define('DATE_FORMAT', 'Y-m-d');
    define('DATETIME_FORMAT', 'Y-m-d H:i:s');
}

// Pagination
if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 20);
    define('MAX_PAGE_SIZE', 100);
}

// Interest rates (annual)
if (!defined('SAVINGS_INTEREST_RATE')) {
    define('SAVINGS_INTEREST_RATE', 0.025); // 2.5%
    define('DEFAULT_LOAN_RATE', 0.0899); // 8.99%
}
