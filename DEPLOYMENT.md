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
mysql -u studyvibe_user -p studyvibe < schema_v10_quiz_deadline.sql
mysql -u studyvibe_user -p studyvibe < schema_v11_modern_features.sql
mysql -u studyvibe_user -p studyvibe < schema_v12_course_cover.sql
mysql -u studyvibe_user -p studyvibe < schema_v13_compulsory_library.sql
mysql -u studyvibe_user -p studyvibe < schema_live_evaluation.sql
```

> **Les migrations plus récentes sont automatiques.** `Database.php` applique lui-même les changements ajoutés après la v13
> (séances asynchrones, pause, types de questions, options d'intégrité, badges, file d'e-mails, etc.) une seule fois par
> déploiement, sous un verrou MySQL (`GET_LOCK`). Après une mise à jour du code, la première requête les exécute. Pour les
> relancer à la main, supprimez le fichier `studyvibe_schema_*.stamp` du dossier temporaire du serveur.

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

### Tâche planifiée : rappel 15 minutes avant une évaluation en direct

Quinze minutes avant le début d'une évaluation **en direct** (séance activée, non asynchrone), chaque étudiant du cours (compte actif, e-mail confirmé) et chaque personne déjà inscrite dans la salle d'attente avec une adresse e-mail reçoit automatiquement un rappel avec le lien et les consignes. Une évaluation asynchrone n'en reçoit pas. Déplacer l'examen renvoie un rappel 15 minutes avant la nouvelle heure ; un même horaire n'envoie jamais deux fois.

Pour que le rappel parte **à l'heure même si personne ne consulte le site**, planifiez le worker chaque minute :

```
* * * * * www-data php /var/www/studyvibe/lib/email-worker.php >/dev/null 2>&1
```

(L'image Docker le fait déjà : `cron` démarre avec Apache.) Sans cron, le rappel part quand même dès qu'une page connectée est chargée ou qu'un étudiant attend dans la salle (au plus toutes les 20 secondes), mais seule la tâche planifiée l'assure sur un site calme.

### Lecture des PDF pour la vérification des ressemblances (facultatif)

La vérification des ressemblances entre devoirs lit le texte des PDF avec `pdftotext` (paquet `poppler-utils`, déjà dans l'image Docker : `sudo apt install poppler-utils` ailleurs). Sans lui, un lecteur intégré ne comprend que les PDF simples ; les autres sont signalés « illisibles » au lieu d'être comparés sur du texte faux.

### Limites d'envoi de fichiers (packs ZIP)

Envoi maximal : **512 Mo** par fichier. Les valeurs doivent concorder à chaque étage, la plus petite gagne :

| Étage | Réglage | Valeur |
|---|---|---|
| PHP (FPM, CGI) | `.user.ini` | `upload_max_filesize=512M`, `post_max_size=640M` |
| Apache + mod_php | `.htaccess`, `LimitRequestBody` | 512 Mo / 640 Mo |
| Docker | `docker/php-tuning.ini`, `docker/apache-tuning.conf` | idem |
| Nginx | `client_max_body_size` | `640M` |
| Serveur de développement PHP | `./dev-server.sh` (ignore `.user.ini`) | idem |

Si php-fpm est utilisé avec un `php.ini` global qui fixe ces valeurs, le `.user.ini` est relu toutes les 300 s (`user_ini.cache_ttl`).

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

    # Packs ZIP (questions + images) jusqu'à 512 Mo : nginx refuse 1 Mo par défaut (erreur 413)
    client_max_body_size 640M;
    client_body_timeout 120s;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_read_timeout 600s;
        fastcgi_request_buffering on;
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

## 9 bis. Tâche de fond : e-mails de résultats

Les résultats des évaluations en direct sont envoyés en arrière-plan. Le worker démarre tout seul à la fin d'un examen. Par sécurité,
ajoutez-le aussi au cron pour vider la file si un envoi a été interrompu :

```cron
*/5 * * * * php /var/www/studyvibe/lib/live-mail-worker.php >/dev/null 2>&1
```

## 9 quater. SMS, vérification du téléphone et double authentification

Dans `.env` (voir `.env.example`) :

| Variable | Rôle |
|---|---|
| `SMS_DRIVER` | `twilio` ou `africastalking` en production. `log` écrit les SMS dans `uploads/sms_log/` et n'est accepté que si `APP_DEBUG=true` (développement). |
| `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` | Compte Twilio (ou `TWILIO_MESSAGING_SERVICE_SID`). |
| `AT_USERNAME`, `AT_API_KEY` | Compte Africa's Talking. |
| `SMS_DEFAULT_COUNTRY` | Indicatif ajouté aux numéros saisis sans indicatif (`237`). |
| `APP_SECRET` | **Obligatoire, 32 caractères minimum.** Il chiffre les clés de double authentification et signe les codes SMS. Sans lui, la double authentification et la vérification du téléphone refusent de démarrer. |
| `TRUST_PROXY` | `true` derrière Railway, Render ou Cloudflare, pour lire l'adresse réelle du visiteur (limites d'envoi de SMS et de tentatives). |
| `REQUIRE_2FA_ROLES` | Rôles obligés d'activer la double authentification (par exemple `promoter,teacher`). Vide : facultatif. |
| `APP_DEBUG` | `false` en production. |

Les SMS d'annonce d'évaluation partent par une file (`sms_outbox`) traitée en arrière-plan. Ajoutez au cron pour reprendre les envois interrompus :

```cron
*/5 * * * * php /var/www/studyvibe/lib/sms-worker.php >/dev/null 2>&1
```

Fichiers téléversés : ils sont copiés dans la base (tables `media_files` et `media_chunks`) et reviennent automatiquement après un redéploiement qui efface le disque. Pour copier les fichiers déjà présents : `php scripts/backfill-media.php`.

Tests : `php tests/unit/run.php` (rapide, sans base) et, sur une machine de développement avec `APP_DEBUG=true` et `SMS_DRIVER=log`,
`php tests/integration/account_security_flow.php`, `php tests/integration/media_store.php` et `php tests/integration/results_review.php` (revue des résultats, annulation, contestation, annonces par e-mail), `php tests/integration/assignments_flow.php` (devoirs : règles, notation, exports), `php tests/integration/bulk_import.php` (import en lot ZIP) et `php tests/integration/exports_compile.php` (compile tous les PDF avec LaTeX ; exige `pdflatex` avec les paquets `lmodern`, `babel-french`, `booktabs`, `longtable`, `enumitem`, `needspace`, `lastpage`, `microtype` : ils sont dans `lmodern`, `texlive-latex-recommended`, `texlive-latex-extra`, `texlive-fonts-recommended` et `texlive-lang-french` du Dockerfile).

## 9 quinquies. E-mails de fond (annonces d'évaluation, résultats annulés)

Les annonces envoyées aux étudiants quand une séance est activée ou déplacée, et les e-mails d'annulation ou de rétablissement d'un résultat, passent par une file (`email_outbox`) traitée en arrière-plan. Comme pour les SMS, ajoutez au cron pour reprendre les envois interrompus :

```cron
*/5 * * * * php /var/www/studyvibe/lib/email-worker.php >/dev/null 2>&1
```

Le logo est joint à chaque e-mail (image intégrée), il s'affiche donc même quand `APP_URL` n'est pas joignable depuis Internet. Les polices Fraunces et Hanken Grotesk se chargent dans Apple Mail, iOS Mail, Outlook pour Mac et Thunderbird ; Gmail et Outlook Windows affichent Georgia et Helvetica/Arial à la place.

## 9 ter. Tests automatiques

```bash
php tests/unit/run.php
```

La CI (`.github/workflows/ci.yml`) vérifie la syntaxe de tous les fichiers PHP, lance ces tests et refuse un secret réel dans `.env.example`.

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

## The installed app and push notifications

- **HTTPS is required** for the install pop-up and for push (browsers refuse both on plain http, except on localhost). Set `HTTPS_ONLY=true` behind your TLS proxy.
- **Keys:** run `php scripts/generate-vapid.php` once and put the two lines it prints (`VAPID_PRIVATE_PEM_B64`, `VAPID_SUBJECT`) in `.env` or in the host's environment. Keep the private key; if it is lost or changed, every device must subscribe again. Without keys the app still installs, notifications are simply not offered.
- **Sending:** every in-app notification (`Notifications::send`) is also queued in `push_outbox` for the user's devices. The same worker as the e-mails sends them: `php lib/email-worker.php` (cron every minute, see above, and started right after a request). Nothing else to schedule.
- **Devices:** table `push_subscriptions`, one row per phone or browser (8 per person at most). A device that answers 404/410 is deleted by the worker; signing out detaches the device.
- **Migration:** automatic (2.6m) or `schema_v18_web_push.sql`.
- **Check:** `php tests/integration/pwa_push.php` (73 checks: encryption, VAPID, API, queue, worker, manifest).


## Centre de contrôle (console super-administrateur)

Le centre de contrôle (`/ops-5f208f200992fc7ea994/`) est accessible depuis le tableau de bord administrateur (menu « Centre de contrôle », visible seulement pour un propriétaire). Il n'a **pas de mot de passe** : il s'ouvre avec une vérification renforcée en deux preuves données ensemble.

1. **Qui peut le voir.** Un promoteur ; si `CONTROL_OWNER_EMAIL` est renseigné (variable d'environnement ou `.env`, adresses séparées par des virgules), seulement ceux-là. Toute autre personne reçoit un 404 identique à une adresse inexistante.
2. **Ce qu'il faut pour l'ouvrir.** Un code à 8 chiffres envoyé à l'adresse e-mail vérifiée du compte (valable 10 minutes, usage unique, 5 essais, stocké uniquement sous forme d'empreinte) **et** un code courant de l'application d'authentification (les codes de secours sont refusés, un code déjà utilisé ne peut pas être rejoué). Une mauvaise tentative ne dit jamais laquelle des deux preuves est fausse.
3. **Pré-requis (fermé par défaut).** Le compte doit avoir l'authentification à deux facteurs activée et une adresse vérifiée, `APP_SECRET` doit être une vraie valeur (32 caractères ou plus) et un serveur d'e-mail (`SMTP_*`) doit être configuré. Sinon la page l'explique et rien ne s'ouvre. Hors production (`APP_DEBUG=true`, sans serveur d'e-mail), le code s'affiche à l'écran pour les essais locaux.
4. **Une fois ouvert.** Session liée au navigateur et au réseau (IPv4 /24, IPv6 /48), fermée après 20 minutes sans activité et au bout de 2 heures quoi qu'il arrive ; bouton « Verrouiller » ; la déconnexion ferme aussi. Le propriétaire reçoit un e-mail à chaque ouverture.
5. **Traçabilité.** Demande de code, échecs, blocages, ouvertures et verrouillages sont inscrits au journal d'audit (`ops_unlock_*`, `ops_locked`) et apparaissent dans le rapport de traçabilité.

Test : `php tests/integration/ops_unlock.php`.

## Recrutement : lecture automatique (ATS), entretiens et rappels

Le moteur de recrutement travaille 24 h/24 (`lib/CareersWorker.php`) :

| Tâche | Ce qu'elle fait |
|---|---|
| Lecture | Lit chaque candidature (texte du CV extrait des PDF, DOCX et DOC, sans bibliothèque externe), calcule un score de 0 à 100 avec les raisons, place les dossiers très éloignés de l'offre dans la section « Lecture auto ». Relit les dossiers d'une offre dont les critères ont changé. Un CV illisible n'est jamais écarté. |
| Offres | Clôture les offres dont la date limite est passée, prévient les administrateurs 3 jours avant. |
| Entretiens | Rappel la veille et 2 heures avant, relance après 48 h sans réponse, marque les entretiens passés comme terminés. |
| Refus poli | Si l'offre l'a demandé (`auto_decline_days`), refuse poliment un dossier écarté que personne n'a examiné. |
| Conservation | Efface les dossiers de personnes hors processus après 12 mois (24 mois avec consentement au vivier). Chaque effacement est journalisé (`audit_logs`). |
| Point du jour | Un e-mail par jour (dès 07 h 30) aux administrateurs : dossiers en attente, entretiens du jour et du lendemain. |

**Où il tourne.** Le cron de l'image Docker lance déjà `lib/email-worker.php` chaque minute, qui appelle le moteur : rien de plus à planifier. Sur un autre hébergement, ajoutez (ou gardez la ligne du worker d'e-mails ci-dessus) :

```cron
* * * * * www-data php /var/www/studyvibe/lib/careers-worker.php >/dev/null 2>&1
```

Sans cron, le moteur démarre quand même sur les pages Carrières, la page de suivi et l'espace recrutement, au plus une fois par minute, une fois la réponse envoyée.

**Fuseau horaire.** Les horaires d'entretien sont saisis et envoyés dans le fuseau du serveur (`Africa/Douala`, défini dans `config.php`).

**Fichiers.** Les CV sont stockés dans `uploads/career-resumes/` (jamais servis par `download.php`), avec une copie en base comme les autres fichiers, et ne sont lus que par `promoter/careers/resume.php` (administrateurs, téléchargements journalisés).

**Adresse de contact.** `CONTACT_EMAIL` (par défaut `studyvibelms@gmail.com`) sert au pied de page, à la politique de confidentialité et aux conditions d'utilisation. L'expéditeur des e-mails reste `SMTP_FROM`.

**Vérifications.** `php tests/unit/careers_screening.php` (moteur et extraction), `php tests/integration/careers_flow.php` et `php tests/integration/careers_ats.php` (base de données requise).

### Importer des offres depuis des fichiers Markdown

Les offres peuvent s'écrire dans un fichier (`docs/careers/job-template.md`, ou `job-template.fr.md`). Dans l'espace recrutement, **Offres > Importer un fichier Markdown** affiche un aperçu et la liste de tous les problèmes (erreurs bloquantes et remarques), puis l'approbation crée l'offre avec les règles du formulaire. L'équipe est choisie parmi une liste fixe (`Careers::TEAMS`) : engineering, education, product, sales, success, marketing, data, operations, people.

En ligne de commande, sur le serveur (même moteur, publication au nom du premier administrateur actif) :

```bash
php scripts/import-job.php docs/careers/postings/*.md --dry-run   # vérifie sans rien créer
php scripts/import-job.php docs/careers/postings/*.md             # publie (ajoutez --draft pour un brouillon)
```

Trois offres prêtes à publier sont dans `docs/careers/postings/` (ingénierie, ventes, éducation), lecture automatique activée. Relisez-les avant publication : l'organisation, les avantages et la rémunération y sont des propositions à adapter.

## Marking of code questions (Gemini by default, Anthropic optional)

Code questions in live evaluations are read and marked by a model (it never runs the code). The provider is **Gemini** as soon as `GEMINI_API_KEY` is set (the same key as the AI assistant); set `AI_PROVIDER=anthropic` to use Anthropic instead.

| Variable | Meaning |
|---|---|
| `GEMINI_API_KEY` | Google AI key. Set it in the host's environment (Render: Environment), never in the repository or in chat. |
| `GEMINI_API_KEYS` | Optional extra keys, comma separated. The marking is spread over every key and every model (round robin, a key or model that is full or busy rests, the others work). Google counts the quota per project, so keys from the same project do not add up. The state is in the table `ai_key_state` and shown on the teacher's corrections page. |
| `GEMINI_RPM_PER_MODEL` | Calls per minute per key and model, default 5 (free plan). `0` = no limit, for a paid plan. |
| `GEMINI_MARKING_MODEL` | Optional, default `gemini-2.5-flash`. |
| `AI_PROVIDER` | Optional: `gemini` or `anthropic`. Empty = Gemini when its key is set, else Anthropic. |
| `ANTHROPIC_API_KEY` | Key of a dedicated Anthropic project with a monthly spend limit. Set it in the host's environment (Render: Environment), never in the repository or in chat. Empty = the answers wait, nothing is marked wrong. |
| `ANTHROPIC_MODEL` | Optional, default `claude-sonnet-5-5`. |
| `ANTHROPIC_DAILY_CALLS` | Optional ceiling of marking calls per day, default 2000. |

Limits already in place: per student, per address and per session on submissions, a size limit on the code, one marking per identical code, a daily ceiling, back-off when the API is busy, and a hand-over to the teacher after four failed attempts. The cron worker (`lib/email-worker.php`) also marks whatever is waiting.
