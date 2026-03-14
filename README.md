# Open Wallet Log - Setup Instructions

A comprehensive, enterprise-featured Progressive Web App (PWA) for financial management built with plain PHP backend and modern JavaScript frontend.

## Features

### Core Financial Features
- **Multi-Account Management**: Checking, Savings, Investment, and Credit accounts
- **Transaction System**: Deposits, Withdrawals, Transfers, and Payments
- **Loan Management**: Apply for loans, payment schedules, and tracking
- **Investment Portfolio**: Stock trading, portfolio tracking, and performance analytics
- **Reports & Analytics**: Income/expense reports, spending categories, balance history
- **Real-time Notifications**: Push notifications for transactions and alerts

### Enterprise Features
- **User Management**: Role-based access (User/Admin)
- **Admin Dashboard**: System-wide reports and user management
- **Security**: CSRF protection, rate limiting, encryption
- **Audit Logging**: Complete activity tracking
- **API Rate Limiting**: Configurable request limits

### PWA Features
- **Offline Support**: Queue transactions when offline
- **Background Sync**: Automatic sync when back online
- **Push Notifications**: Real-time updates
- **Installable**: Add to home screen on mobile/desktop
- **Responsive Design**: Works on all devices

## Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)
- Apache 2.4+ with mod_rewrite enabled
- SSL certificate (recommended for production)

### PHP Extensions
- PDO (with MySQL driver)
- OpenSSL
- JSON
- Session

## Installation

### Step 1: Clone or Download
Place all files in your web server document root (e.g., `/var/www/html/` or `C:/xampp/htdocs/`).

### Step 2: Database Setup

1. Create the database:
```sql
CREATE DATABASE finpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the schema:
```bash
mysql -u root -p finpro < database/schema.sql
```

Or use phpMyAdmin to import `database/schema.sql`.

### Step 3: Configuration

No configuration needed for basic setup - the app uses default values. For production, you can set environment variables:

```bash
# Database
export DB_HOST=localhost
export DB_NAME=finpro
export DB_USER=your_db_user
export DB_PASS=your_db_password

# Security
export JWT_SECRET=your-secret-key-here
export APP_ENV=production
```

Or edit `api/config/config.php` directly.

### Step 4: Apache Configuration

Ensure Apache has mod_rewrite enabled:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

The `.htaccess` file is included for URL rewriting and security headers.

### Step 5: Permissions

Set proper permissions:
```bash
# Linux/Mac
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
```

### Step 6: Access the App

Open your browser:
```
http://localhost/
```

Default admin credentials:
- Email: `admin@finpro.com`
- Password: `password`

**IMPORTANT**: Change the admin password immediately after first login!

## Composer Dependencies (Optional)

While the app works without Composer, you can add it for additional features:

### Installing Composer

**Linux/Mac:**
```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Verify installation
composer --version
```

**Windows:**
1. Download the installer from https://getcomposer.org/download/
2. Run the installer and follow the prompts
3. Add Composer to your system PATH
4. Verify: `composer --version`

### Installing Dependencies

```bash
# Navigate to your project directory
cd /var/www/html  # or your web root

# Install dependencies
composer install

# For production, use:
composer install --no-dev --optimize-autoloader
```

### Setting Up for Apache (Static Server)

When deploying to Apache, you have two options:

**Option 1: Without Composer (Recommended for shared hosting)**
The app is fully functional without Composer. Just upload all files to your Apache server:
```bash
# Upload all files to your web root
# No additional steps needed - the app uses plain PHP
```

**Option 2: With Composer (For VPS/Dedicated servers)**

1. Upload all files including the `vendor` folder after running `composer install`
2. Ensure your Apache configuration allows `.htaccess` files:

```apache
<Directory /var/www/html>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

3. Set proper permissions:
```bash
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
sudo chmod -R 775 /var/www/html/vendor  # If using Composer
```

### Available Optional Packages

To add optional packages for enhanced functionality:

```bash
# Email notifications (recommended for production)
composer require phpmailer/phpmailer

# Environment variable management
composer require vlucas/phpdotenv

# Enhanced JWT handling
composer require firebase/php-jwt

# Advanced logging
composer require monolog/monolog

# UUID generation
composer require ramsey/uuid
```

### Development Tools

```bash
# Install dev dependencies
composer install --dev

# Run code style checks
composer cs-check

# Fix code style issues
composer cs-fix

# Run static analysis
composer analyze

# Run tests
composer test
```

## Apache Virtual Host Configuration (Production)

Create `/etc/apache2/sites-available/finpro.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
    
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript application/json
    </IfModule>
    
    # Caching for static assets
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/jpeg "access plus 1 year"
        ExpiresByType image/png "access plus 1 year"
        ExpiresByType text/css "access plus 1 month"
        ExpiresByType text/javascript "access plus 1 month"
        ExpiresByType application/javascript "access plus 1 month"
    </IfModule>
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite finpro.conf
sudo systemctl reload apache2
```

## Docker Setup (Optional)

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  app:
    image: php:7.4-apache
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    
  db:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: finpro
      MYSQL_USER: finpro
      MYSQL_PASSWORD: finpro123
    volumes:
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - mysql_data:/var/lib/mysql
    
  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports:
      - "8080:80"
    environment:
      PMA_HOST: db
      PMA_USER: root
      PMA_PASSWORD: rootpassword

volumes:
  mysql_data:
```

Run:
```bash
docker-compose up -d
```

## API Documentation

All API endpoints are accessed via `/api/`

### Authentication Endpoints
- `POST /api/auth/login` - User login
- `POST /api/auth/register` - User registration
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Get current user
- `PUT /api/auth/profile` - Update profile

### Account Endpoints
- `GET /api/accounts` - List accounts
- `POST /api/accounts/create` - Create new account
- `GET /api/accounts/balance` - Get balance

### Transaction Endpoints
- `GET /api/transactions` - List transactions
- `POST /api/transactions/transfer` - Transfer money
- `POST /api/transactions/deposit` - Make deposit
- `POST /api/transactions/withdraw` - Withdraw funds

### Loan Endpoints
- `GET /api/loans` - List loans
- `POST /api/loans/apply` - Apply for loan
- `POST /api/loans/payment` - Make loan payment

### Investment Endpoints
- `GET /api/investments/portfolio` - Get portfolio
- `POST /api/investments/buy` - Buy stock
- `POST /api/investments/sell` - Sell stock

### Report Endpoints
- `GET /api/reports/summary` - Financial summary
- `GET /api/reports/income-expense` - Income vs expenses
- `GET /api/reports/balance-history` - Balance over time

## Security Considerations

1. **Change default passwords** - Immediately change admin password
2. **Use HTTPS** - Always use SSL in production
3. **Update JWT_SECRET** - Change in `api/config/config.php`
4. **Rate limiting** - Configured by default, adjust as needed
5. **CORS** - Update allowed origins for production
6. **Database** - Use strong database passwords
7. **File permissions** - Don't allow write access to web root
8. **Backups** - Regular database backups recommended

## Troubleshooting

### Database connection failed
- Check database credentials in `api/config/config.php`
- Ensure MySQL is running
- Verify database exists

### API returns 404
- Ensure mod_rewrite is enabled
- Check `.htaccess` file exists and is readable
- Verify Apache AllowOverride is set to All

### CORS errors
- Update allowed origins in `api/index.php`
- Ensure proper headers are sent

### Cache issues
- Clear browser cache
- Restart Apache
- Check file permissions

## License

MIT License - See LICENSE file for details

## Support

For issues and feature requests, please create an issue in the repository.

---

**Built with**: PHP 7.4+, MySQL, Vanilla JavaScript, Chart.js


  docker compose -f docker-compose.dev.yml up -d