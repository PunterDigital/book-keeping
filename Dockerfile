# Multi-stage build for Laravel application
FROM php:8.2-fpm-alpine as base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Development stage
FROM base as development

# Install development dependencies
RUN composer install --no-scripts --no-autoloader

# Copy application code
COPY . .

# Create storage symlink
RUN rm -rf /var/www/public/storage \
    && ln -s /var/www/storage/app/public /var/www/public/storage

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Generate autoload files
RUN composer dump-autoload --optimize

# Production stage
FROM base as production

# Install only production dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Create storage symlink
RUN rm -rf /var/www/public/storage \
    && ln -s /var/www/storage/app/public /var/www/public/storage

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Generate autoload files
RUN composer dump-autoload --optimize --classmap-authoritative

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]