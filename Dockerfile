FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libgmp-dev \
    zip \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    mysqli \
    zip \
    curl \
    mbstring \
    dom \
    xml \
    simplexml \
    xmlwriter \
    fileinfo \
    opcache \
    bcmath \
    gmp

# Enable Apache rewrite module
RUN a2enmod rewrite

# Setup application directories
RUN mkdir -p /var/www/html/new_site \
             /var/www/storage/cache \
             /var/www/storage/download \
             /var/www/storage/logs \
             /var/www/storage/session \
             /var/www/storage/upload

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer.json to storage folder and install PHP dependencies
COPY composer.json /var/www/storage/
WORKDIR /var/www/storage
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Set working directory back to html root
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html/new_site/

# Adjust .htaccess for /new_site/ subdirectory deployment
RUN if [ -f /var/www/html/new_site/.htaccess ]; then \
        sed -i 's|RewriteBase /neww_magicalsingingbowls/|RewriteBase /new_site/|g' /var/www/html/new_site/.htaccess; \
    fi

# Adjust config files inside container to use container directories instead of host directories
RUN if [ -f /var/www/html/new_site/config.php ]; then chmod 644 /var/www/html/new_site/config.php; fi \
    && if [ -f /var/www/html/new_site/msbadmin/config.php ]; then chmod 644 /var/www/html/new_site/msbadmin/config.php; fi

RUN php -r " \
    foreach (['/var/www/html/new_site/config.php', '/var/www/html/new_site/msbadmin/config.php'] as \$file) { \
        if (file_exists(\$file)) { \
            \$c = file_get_contents(\$file); \
            \$c = str_replace('/var/www/magicalsingingbowls/new_site', '/var/www/html/new_site', \$c); \
            \$c = preg_replace(\"/define\('DIR_STORAGE',\s*'[^']+'\);/\", \"define('DIR_STORAGE', '/var/www/storage/');\", \$c); \
            \$c = preg_replace(\"/define\('DIR_CACHE',\s*'[^']+'\);/\", \"define('DIR_CACHE', DIR_STORAGE . 'cache/');\", \$c); \
            \$c = preg_replace(\"/define\('DIR_DOWNLOAD',\s*'[^']+'\);/\", \"define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');\", \$c); \
            \$c = preg_replace(\"/define\('DIR_LOGS',\s*'[^']+'\);/\", \"define('DIR_LOGS', DIR_STORAGE . 'logs/');\", \$c); \
            \$c = preg_replace(\"/define\('DIR_SESSION',\s*'[^']+'\);/\", \"define('DIR_SESSION', DIR_STORAGE . 'session/');\", \$c); \
            \$c = preg_replace(\"/define\('DIR_UPLOAD',\s*'[^']+'\);/\", \"define('DIR_UPLOAD', DIR_STORAGE . 'upload/');\", \$c); \
            if (file_put_contents(\$file, \$c) === false) { \
                throw new Exception('Failed to write to ' . \$file); \
            } \
        } \
    }"

# Set proper ownership and permissions for OpenCart
RUN chown -R www-data:www-data /var/www/html /var/www/storage \
    && chmod -R 755 /var/www/html /var/www/storage

# Expose port 80
EXPOSE 80
