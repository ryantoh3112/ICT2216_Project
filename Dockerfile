FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip zip libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]

# #### STAGE 1: BUILDING ENVIRONMENT ####
# # 1. build env
# FROM php:8.2-fpm AS builder

# # 2. install dependencies and php extensions
# RUN apt-get update && apt-get install -y \
#     git unzip zip libicu-dev libzip-dev libonig-dev libpq-dev \
#     && docker-php-ext-install intl pdo pdo_mysql zip opcache

# # 3. install composer
# COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# # 4. set working directory
# WORKDIR /var/www/html

# # 5. Copy app source code
# COPY . .

# # Install Symfony dependencies (optimize for production)
# RUN composer install --no-dev --optimize-autoloader \
#  && php bin/console cache:clear --env=prod \
#  && php bin/console cache:warmup --env=prod


 
# #### STAGE 2: RUNNING ENVIRONMENT ####
# FROM php:8.2-fpm

# WORKDIR /var/www/html

# # Copy only built app from builder stage
# COPY --from=builder /var/www/html /var/www/html
# COPY --from=builder /usr/bin/composer /usr/bin/composer

# # Expose PHP-FPM port
# EXPOSE 9000

# CMD ["php-fpm"]