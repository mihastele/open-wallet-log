<?php
/**
 * Mailer Class - Email sending via SMTP
 */

class Mailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $from;
    private $fromName;
    
    public function __construct() {
        $this->host = $_ENV['SMTP_HOST'] ?? 'localhost';
        $this->port = $_ENV['SMTP_PORT'] ?? 1025;
        $this->user = $_ENV['SMTP_USER'] ?? '';
        $this->pass = $_ENV['SMTP_PASS'] ?? '';
        $this->from = $_ENV['SMTP_FROM'] ?? 'noreply@openwalletlog.local';
        $this->fromName = APP_NAME ?? 'Open Wallet Log';
    }
    
    /**
     * Send an email
     */
    public function send($to, $subject, $body, $isHtml = true) {
        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->from . '>';
        $headers[] = 'Reply-To: ' . $this->from;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        // For development with mailpit, use mail() function
        // Mailpit intercepts emails sent via mail() on port 25
        // Or we can use SMTP directly
        
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        // Try SMTP connection first
        $sent = $this->sendViaSmtp($to, $subject, $body, $headers);
        
        if (!$sent) {
            // Fallback to PHP mail() function
            $headerStr = implode("\r\n", $headers);
            $sent = mail($to, $subject, $body, $headerStr);
        }
        
        return $sent;
    }
    
    /**
     * Send email via SMTP socket connection
     */
    private function sendViaSmtp($to, $subject, $body, $headers) {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        
        if (!$socket) {
            error_log("SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }
        
        // Read server greeting
        $this->readSmtp($socket);
        
        // Send EHLO
        $this->writeSmtp($socket, "EHLO localhost");
        $this->readSmtp($socket);
        
        // MAIL FROM
        $this->writeSmtp($socket, "MAIL FROM:<{$this->from}>");
        $this->readSmtp($socket);
        
        // RCPT TO
        $this->writeSmtp($socket, "RCPT TO:<{$to}>");
        $this->readSmtp($socket);
        
        // DATA
        $this->writeSmtp($socket, "DATA");
        $this->readSmtp($socket);
        
        // Build email content
        $email = implode("\r\n", $headers) . "\r\n";
        $email .= "To: {$to}\r\n";
        $email .= "Subject: {$subject}\r\n";
        $email .= "\r\n";
        $email .= $body;
        $email .= "\r\n.";
        
        $this->writeSmtp($socket, $email);
        $this->readSmtp($socket);
        
        // QUIT
        $this->writeSmtp($socket, "QUIT");
        fclose($socket);
        
        return true;
    }
    
    private function readSmtp($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
    
    private function writeSmtp($socket, $data) {
        fwrite($socket, $data . "\r\n");
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($email, $token, $firstname) {
        $verifyUrl = (APP_URL ?? 'http://localhost') . '/?verify=' . $token;
        
        $subject = 'Verify Your Email Address';
        $body = $this->getTemplate('verification', [
            'firstname' => $firstname,
            'verify_url' => $verifyUrl,
            'app_name' => $this->fromName
        ]);
        
        return $this->send($email, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $token, $firstname) {
        $resetUrl = (APP_URL ?? 'http://localhost') . '/?reset-token=' . $token;
        
        $subject = 'Reset Your Password';
        $body = $this->getTemplate('password-reset', [
            'firstname' => $firstname,
            'reset_url' => $resetUrl,
            'app_name' => $this->fromName
        ]);
        
        return $this->send($email, $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getTemplate($template, $data) {
        $templates = [
            'verification' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .button { display: inline-block; padding: 12px 24px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 6px; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>Welcome to {app_name}!</h2>
                        <p>Hello {firstname},</p>
                        <p>Thank you for registering. Please verify your email address by clicking the button below:</p>
                        <p><a href="{verify_url}" class="button">Verify Email Address</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p><code>{verify_url}</code></p>
                        <p>This link will expire in 24 hours.</p>
                        <div class="footer">
                            <p>If you did not create an account, please ignore this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'password-reset' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .button { display: inline-block; padding: 12px 24px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 6px; }
                        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                        .warning { background: #fef3cd; padding: 10px; border-radius: 6px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>Password Reset Request</h2>
                        <p>Hello {firstname},</p>
                        <p>We received a request to reset your password. Click the button below to create a new password:</p>
                        <p><a href="{reset_url}" class="button">Reset Password</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p><code>{reset_url}</code></p>
                        <p>This link will expire in 1 hour.</p>
                        <div class="warning">
                            <p><strong>Security note:</strong> If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                        </div>
                        <div class="footer">
                            <p>{app_name} - Secure Financial Management</p>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];
        
        $content = $templates[$template] ?? '';
        
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }
}
