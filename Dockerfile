# Image de base avec PHP 8.4 + Apache
FROM php:8.4-apache

ARG APP_ENV=dev

# Installer dépendances système et extensions PHP
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libpq-dev libzip-dev \
    default-mysql-client netcat-openbsd wget \
    && docker-php-ext-install intl pdo pdo_mysql zip \
    && a2enmod rewrite

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurer Apache pour Symfony
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Définir le dossier de travail
WORKDIR /var/www/html

# Copier le code Symfony
COPY . .

# Droits sur var/ et optimisation pour production
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 var \
    && chmod -R 777 var/cache var/log

# Installer dépendances Symfony (prod ou dev)
RUN if [ "$APP_ENV" = "prod" ]; then \
    composer install --no-dev --optimize-autoloader; \
    else \
    composer install --optimize-autoloader; \
    fi \
    && composer run-script auto-scripts \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 777 var/cache var/log

# Créer un script pour initialiser la DB à la première création
RUN echo '#!/bin/bash\n\
# Attendre que MySQL soit prêt\n\
until nc -z db 3306; do sleep 1; done\n\
# Créer la base si nécessaire et exécuter les migrations\n\
php bin/console doctrine:database:create --if-not-exists\n\
php bin/console doctrine:migrations:migrate --no-interaction || true\n\
# Démarrer Apache\n\
exec apache2-foreground' > /usr/local/bin/start-app.sh \
    && chmod +x /usr/local/bin/start-app.sh

EXPOSE 80
CMD ["/usr/local/bin/start-app.sh"]
