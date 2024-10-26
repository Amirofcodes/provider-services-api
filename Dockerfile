FROM php:8.3-fpm
# Installer les dépendances nécessaires
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copier le code source de l'application
WORKDIR /var/www
COPY . .

# Installer les dépendances du projet
RUN composer install --no-dev --optimize-autoloader
