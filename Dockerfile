# Stage 1: Build the Vue frontend
FROM node:22-alpine AS frontend-builder
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    unzip \
    libpq-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd zip opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy the built Vue frontend into the image
COPY --from=frontend-builder /build/dist /var/www/html/frontend/dist

# Install dependencies (no dev for production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy custom PHP-FPM config (clear_env = no)
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads 2>/dev/null || true

# Copy Nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Expose port 8080 (Render uses this)
EXPOSE 8080

# Start both PHP-FPM and Nginx
CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
