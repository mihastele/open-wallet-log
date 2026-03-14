-- Open Wallet Log - Database Schema
-- Run this SQL to set up the complete database structure

-- Create database
CREATE DATABASE IF NOT EXISTS finpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finpro;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    email_verified TINYINT(1) DEFAULT 0,
    verify_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME,
    last_login DATETIME,
    avatar VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Auth tokens table
CREATE TABLE auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Accounts table
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('checking', 'savings', 'investment', 'credit') NOT NULL,
    currency ENUM('USD', 'EUR', 'GBP') DEFAULT 'USD',
    balance DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    interest_rate DECIMAL(5,4) DEFAULT 0.0000,
    opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_account_number (account_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'payment') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency ENUM('USD', 'EUR', 'GBP') DEFAULT 'USD',
    description VARCHAR(255),
    category VARCHAR(50) DEFAULT 'uncategorized',
    reference VARCHAR(50),
    status ENUM('pending', 'processing', 'completed', 'failed', 'reversed') DEFAULT 'pending',
    related_account_id INT,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_account_id (account_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Loans table
CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,4) NOT NULL,
    term_months INT NOT NULL,
    monthly_payment DECIMAL(15,2) NOT NULL,
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    purpose TEXT,
    type ENUM('personal', 'business', 'mortgage', 'auto') NOT NULL,
    employment_status VARCHAR(50),
    income DECIMAL(15,2),
    status ENUM('pending', 'approved', 'active', 'completed', 'rejected', 'defaulted') DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_notes TEXT,
    approved_at DATETIME,
    disbursed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Loan payments table
CREATE TABLE loan_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    account_id INT NOT NULL,
    payment_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX idx_loan_id (loan_id)
) ENGINE=InnoDB;

-- Stocks table (for investments)
CREATE TABLE stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    sector VARCHAR(50),
    current_price DECIMAL(15,4) NOT NULL,
    currency ENUM('USD', 'EUR', 'GBP') DEFAULT 'USD',
    last_updated DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_symbol (symbol)
) ENGINE=InnoDB;

-- Investments table
CREATE TABLE investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stock_id INT NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    purchase_price DECIMAL(15,4) NOT NULL,
    purchase_date DATETIME NOT NULL,
    status ENUM('active', 'sold') DEFAULT 'active',
    sold_date DATETIME,
    sold_price DECIMAL(15,4),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_id) REFERENCES stocks(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Investment sales table
CREATE TABLE investment_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    sell_price DECIMAL(15,4) NOT NULL,
    total_proceeds DECIMAL(15,2) NOT NULL,
    profit_loss DECIMAL(15,2) NOT NULL,
    account_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investment_id) REFERENCES investments(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('transaction', 'alert', 'loan', 'investment', 'security', 'system') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Rate limits table
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Security logs table
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(50) NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_event (event),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'float', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode'),
('registration_enabled', 'true', 'boolean', 'Allow new user registrations'),
('max_accounts_per_user', '5', 'integer', 'Maximum accounts per user'),
('default_currency', 'USD', 'string', 'Default currency for new accounts'),
('savings_interest_rate', '0.025', 'float', 'Annual interest rate for savings accounts'),
('loan_personal_rate', '0.0899', 'float', 'Annual interest rate for personal loans'),
('loan_business_rate', '0.0699', 'float', 'Annual interest rate for business loans'),
('loan_mortgage_rate', '0.0459', 'float', 'Annual interest rate for mortgage loans'),
('daily_transfer_limit', '10000', 'float', 'Daily transfer limit per user'),
('daily_withdrawal_limit', '5000', 'float', 'Daily withdrawal limit per user'),
('single_transfer_limit', '5000', 'float', 'Maximum amount per single transfer');

-- Insert sample stocks
INSERT INTO stocks (symbol, name, sector, current_price) VALUES
('AAPL', 'Apple Inc.', 'Technology', 175.50),
('GOOGL', 'Alphabet Inc.', 'Technology', 142.30),
('MSFT', 'Microsoft Corporation', 'Technology', 378.90),
('AMZN', 'Amazon.com Inc.', 'Consumer', 178.20),
('TSLA', 'Tesla Inc.', 'Automotive', 248.50),
('META', 'Meta Platforms Inc.', 'Technology', 505.20),
('NVDA', 'NVIDIA Corporation', 'Technology', 875.30),
('JPM', 'JPMorgan Chase & Co.', 'Finance', 195.40),
('JNJ', 'Johnson & Johnson', 'Healthcare', 152.80),
('V', 'Visa Inc.', 'Finance', 285.60);

-- Insert admin user (password: admin123)
-- IMPORTANT: Change this password in production!
INSERT INTO users (email, password, firstname, lastname, role, status, email_verified) VALUES
('admin@finpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 'active', 1);
-- Default password for admin: 'password' - CHANGE THIS!
