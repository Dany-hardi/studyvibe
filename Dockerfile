FROM php:8.2-apache

# Installer LaTeX et les dépendances pour la génération de rapports PDF
RUN apt-get update && apt-get install -y --no-install-recommends \
    texlive-latex-base \
    texlive-latex-recommended \
    texlive-latex-extra \
    texlive-fonts-recommended \
    texlive-lang-french \
    && rm -rf /var/lib/apt/lists/*

# Installer l'extension pdo_mysql requise par la base de données
RUN docker-php-ext-install pdo pdo_mysql

# Activer le module rewrite d'Apache (mod_rewrite)
RUN a2enmod rewrite

# Réglages de performance pour les évaluations en direct (300+ étudiants en même temps), voir docs/PERFORMANCE.md
RUN docker-php-ext-install opcache
COPY docker/php-tuning.ini /usr/local/etc/php/conf.d/zz-studyvibe-tuning.ini
COPY docker/apache-tuning.conf /etc/apache2/conf-available/studyvibe-tuning.conf
RUN a2enconf studyvibe-tuning

# Copier les fichiers du projet dans le conteneur
COPY . /var/www/html/

# Ajuster les permissions pour le serveur Apache
RUN chown -R www-data:www-data /var/www/html

# Exposer le port par défaut d'Apache
EXPOSE 80
