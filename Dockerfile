# ---- Build stage: Node assets ----
FROM node:20-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY . .
RUN npm run build

# ---- PHP stage ----
FROM php:8.4-fpm-alpine AS php

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    curl-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath \
    curl \
    fileinfo \
    gd \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    opcache \
    zip \
    pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy auth + composer files first for caching
COPY auth.json composer.json composer.lock ./

# Install dependencies (no dev in production)
RUN composer install --optimize-autoloader --no-dev --no-scripts --no-interaction

# Copy application code
COPY . .

# Copy built assets from node stage
COPY --from=node-build /app/public/build public/build

# Post-install
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true

# Create storage directories and set permissions
RUN mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs storage/app/public/basset bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config
RUN mkdir -p /run/nginx
COPY <<'NGINX' /etc/nginx/http.d/default.conf
server {
    listen 8080;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_buffering off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

# Supervisor config
COPY <<'SUPERVISOR' /etc/supervisord.conf
[supervisord]
nodaemon=true
logfile=/dev/stdout
logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:php-fpm]
command=php-fpm
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
SUPERVISOR

EXPOSE 8080

CMD php artisan storage:link --force && \
    php artisan migrate --force && \
    php artisan config:clear && \
    (php artisan cache:clear || true) && \
    php artisan view:clear && \
    php artisan basset:clear && \
    /usr/bin/supervisord -c /etc/supervisord.conf
