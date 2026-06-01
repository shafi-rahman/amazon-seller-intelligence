FROM php:8.4-fpm-alpine

# System packages
RUN apk add --no-cache \
    bash \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    zip \
    opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/asip.ini

WORKDIR /var/www/html

# Non-root user
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www
USER www

EXPOSE 9000
CMD ["php-fpm"]
