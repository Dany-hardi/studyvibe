FROM php:8.2-apache

# Installer l'extension pdo_mysql requise par la base de données
RUN docker-php-ext-install pdo pdo_mysql

# Activer le module rewrite d'Apache (mod_rewrite)
RUN a2enmod rewrite

# Copier les fichiers du projet dans le conteneur
COPY . /var/www/html/

# Ajuster les permissions pour le serveur Apache
RUN chown -R www-data:www-data /var/www/html

# Exposer le port par défaut d'Apache
EXPOSE 80
