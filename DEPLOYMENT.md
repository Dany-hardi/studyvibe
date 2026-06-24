# Guide de déploiement — StudyVibe LMS

Ce document décrit comment installer, configurer et mettre en production **StudyVibe** sur un serveur Linux (VPS, mutualisé ou local).

---

## 1. Prérequis serveur

| Composant | Version minimale | Rôle |
|-----------|------------------|------|
| **PHP** | 8.1+ | Backend applicatif |
| **MySQL** ou **MariaDB** | 5.7+ / 10.3+ | Base de données |
| **Apache** ou **Nginx** | — | Serveur web |
| **Extensions PHP** | `pdo_mysql`, `mbstring`, `json`, `session`, `openssl` | Obligatoires |

Extensions recommandées : `fileinfo`, `gd` (si traitement d’images futur).

---

## 2. Récupérer le code

```bash
# Cloner ou copier le projet sur le serveur
cd /var/www
git clone <url-du-repo> studyvibe
cd studyvibe
```

Si vous transférez une archive ZIP, décompressez-la dans le répertoire web (ex. `/var/www/studyvibe`).

---

## 3. Base de données

### 3.1 Créer la base et l’utilisateur

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE studyvibe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'studyvibe_user'@'localhost' IDENTIFIED BY 'mot_de_passe_solide';
GRANT ALL PRIVILEGES ON studyvibe.* TO 'studyvibe_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3.2 Importer le schéma

```bash
mysql -u studyvibe_user -p studyvibe < schema.sql
```

Appliquer les migrations dans l’ordre :

```bash
mysql -u studyvibe_user -p studyvibe < schema_v2_migration.sql
mysql -u studyvibe_user -p studyvibe < schema_v3_quiz_answers.sql
mysql -u studyvibe_user -p studyvibe < schema_v4_newsletter.sql
mysql -u studyvibe_user -p studyvibe < schema_v5_cert_reports.sql
mysql -u studyvibe_user -p studyvibe < schema_v6_content_consumed.sql
mysql -u studyvibe_user -p studyvibe < schema_v7_teacher_courses.sql
mysql -u studyvibe_user -p studyvibe < schema_v8_lesson_media.sql
mysql -u studyvibe_user -p studyvibe < schema_v9_features.sql
```

### 3.3 Comptes de démonstration (optionnel)

Si un script `seed.php` existe en environnement de dev, **ne l’exécutez pas en production** (il est bloqué par `.htaccess`).

Créez les comptes via l’interface ou manuellement :

```sql
-- Mot de passe : à générer avec password_hash() en PHP
INSERT INTO users (name, email, password_hash, role) VALUES
('Admin Promoteur', 'promoteur@votredomaine.com', '$2y$10$...', 'promoter');
```

Pour générer un hash :

```bash
php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
```

---

## 4. Configuration `.env`

```bash
cp .env.example .env
nano .env
```

Variables essentielles :

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=studyvibe
DB_USER=studyvibe_user
DB_PASS=mot_de_passe_solide

APP_SECRET=chaine_aleatoire_de_64_caracteres_minimum_tres_importante
APP_URL=https://lms.votredomaine.com

HTTPS_ONLY=true

SMTP_HOST=smtp.votrefournisseur.com
SMTP_PORT=587
SMTP_USER=noreply@votredomaine.com
SMTP_PASS=mot_de_passe_smtp
SMTP_FROM=noreply@votredomaine.com
SMTP_FROM_NAME=StudyVibe
```

**Important :**
- Ne commitez jamais `.env` dans Git.
- `APP_URL` doit correspondre à l’URL publique exacte (sans slash final).
- En production, mettez `HTTPS_ONLY=true` pour forcer le HTTPS.

---

## 5. Permissions des dossiers

```bash
cd /var/www/studyvibe

# Dossier d’upload PDF (écriture web server)
mkdir -p uploads/pdfs uploads/avatars
chown -R www-data:www-data uploads
chmod -R 755 uploads

# Le reste en lecture seule pour le serveur web
chown -R www-data:www-data .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
```

Remplacez `www-data` par l’utilisateur de votre serveur web (`apache`, `nginx`, etc.).

---

## 6. Déploiement avec Apache

### VirtualHost exemple

```apache
<VirtualHost *:80>
    ServerName lms.votredomaine.com
    DocumentRoot /var/www/studyvibe

    <Directory /var/www/studyvibe>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/studyvibe-error.log
    CustomLog ${APACHE_LOG_DIR}/studyvibe-access.log combined
</VirtualHost>
```

Activez `mod_rewrite` :

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### HTTPS avec Certbot (Let’s Encrypt)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d lms.votredomaine.com
```

---

## 7. Déploiement avec Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name lms.votredomaine.com;
    root /var/www/studyvibe;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.env {
        deny all;
    }

    location ^~ /lib/ {
        deny all;
    }

    location ~* \.(sql|md)$ {
        deny all;
    }
}
```

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## 8. Test en local (développement)

```bash
cd /chemin/vers/LMS_AGY
cp .env.example .env
# Configurer DB locale

php -S 127.0.0.1:8000
```

Ouvrez `http://127.0.0.1:8000` et connectez-vous.

---

## 9. Vérifications post-déploiement

| Test | URL / action |
|------|----------------|
| Page d’accueil | `https://lms.votredomaine.com/` |
| Connexion promoteur | `/promoter/dashboard.php` |
| Connexion enseignant | `/teacher/dashboard.php` |
| Connexion apprenant | `/student/dashboard.php` |
| Export Excel | Promoteur → **Exports Excel** (9 feuilles disponibles) |
| Notes enseignant | Enseignant → cours sélectionné → **Notes & Évaluations** |
| Upload PDF leçon | Enseignant → ajouter une leçon PDF |
| API REST | `GET /api/v1/courses` avec en-tête `X-API-Key` |

---

## 10. Sauvegardes

Un script de sauvegarde MySQL est fourni :

```bash
chmod +x scripts/backup-db.sh
./scripts/backup-db.sh
```

Planifiez une sauvegarde quotidienne via cron :

```cron
0 2 * * * /var/www/studyvibe/scripts/backup-db.sh >> /var/log/studyvibe-backup.log 2>&1
```

Sauvegardez aussi :
- le fichier `.env`
- le dossier `uploads/pdfs/`

---

## 11. Sécurité en production

1. **HTTPS obligatoire** — `HTTPS_ONLY=true`
2. **`.env` inaccessible** — vérifié par `.htaccess` / config Nginx
3. **Supprimer `seed.php`** ou le laisser bloqué
4. **Mots de passe forts** pour promoteurs et enseignants
5. **Mettre à jour PHP** régulièrement
6. **Limiter les tentatives de connexion** — déjà géré (`LOGIN_MAX_ATTEMPTS` dans `.env`)

---

## 12. Dépannage courant

### Erreur « Fichier .env introuvable »
→ Copiez `.env.example` en `.env` et remplissez les valeurs.

### Page blanche / erreur 500
→ Consultez les logs : `/var/log/apache2/error.log` ou `journalctl -u php8.2-fpm`
→ Activez temporairement les erreurs PHP en dev uniquement.

### Connexion base de données refusée
→ Vérifiez `DB_HOST`, `DB_USER`, `DB_PASS` dans `.env`
→ Test : `mysql -u studyvibe_user -p studyvibe`

### Les PDF ne se téléchargent pas
→ Vérifiez que l’extension `mbstring` est activée : `php -m | grep mbstring`

### Les emails ne partent pas
→ Configurez SMTP dans `.env`
→ Test depuis le tableau de bord promoteur : **Tester SMTP**

### Export Excel vide ou corrompu
→ Vérifiez que la base contient des données
→ Ouvrez le fichier avec Excel ou LibreOffice Calc (format `.xls` SpreadsheetML)

---

## 13. Structure des exports (promoteur)

| Fichier | Description |
|---------|-------------|
| `promoter/export-excel.php?type=all` | Classeur complet (9 feuilles) |
| `promoter/export-excel.php?type=audit_logs` | Journal d'audit |
| `teacher/export-grades.php?course_id=N` | Notes leçons + certif cours + certif module |

Bibliothèques internes (sans Composer) :
- `lib/SpreadsheetExporter.php` — génération Excel
- `lib/PromoterExportService.php` — exports promoteur
- `lib/TeacherGradesService.php` — notes enseignant

---

## 14. Mise à jour de l’application

```bash
cd /var/www/studyvibe
git pull origin main

# Appliquer les nouvelles migrations SQL si présentes
mysql -u studyvibe_user -p studyvibe < schema_vX_....sql

# Vider le cache navigateur si CSS/JS modifiés
```

---

## 15. Checklist de mise en production

- [ ] Base de données créée et migrée
- [ ] `.env` configuré avec secrets uniques
- [ ] `APP_URL` et `HTTPS_ONLY=true`
- [ ] SMTP configuré et testé
- [ ] Dossier `uploads/pdfs` accessible en écriture
- [ ] Certificat SSL actif
- [ ] Compte promoteur créé
- [ ] Sauvegarde automatique planifiée
- [ ] Test des 3 rôles (promoteur, enseignant, apprenant)

---

**StudyVibe** — Plateforme LMS académique. Pour toute question technique, consultez les fichiers `schema.sql` et `Instructions de developement.md`.
