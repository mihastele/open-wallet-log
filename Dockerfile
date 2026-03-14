FROM php:8.1-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    zip \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod deflate
RUN a2enmod expires

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install dependencies if composer.json exists
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# PHP configuration
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time = 60" >> /usr/local/etc/php/conf.d/execution.ini

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
