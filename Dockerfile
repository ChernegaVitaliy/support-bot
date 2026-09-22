FROM php:8.3-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        supervisor \
        git \
        unzip \
        libsqlite3-dev \
        curl \
        libssl-dev \
    && docker-php-ext-install \
        pdo_sqlite \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Create storage and Symfony var directories
RUN mkdir -p /app/storage /app/var/cache /app/var/log /app/var/sessions \
    && chmod -R 777 /app/storage /app/var

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/support-bot.conf

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]
