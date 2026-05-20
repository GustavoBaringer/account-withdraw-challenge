FROM hyperf/hyperf:8.2-alpine-v3.19-swoole-slim

WORKDIR /var/www/html

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first for layer caching
COPY composer.json composer.lock* ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    && composer clear-cache

COPY . .

RUN composer dump-autoload --optimize

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9501

ENTRYPOINT ["/entrypoint.sh"]
