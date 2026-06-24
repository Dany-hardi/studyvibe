<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>Politique de Confidentialité — StudyVibe</title>
    <meta name="description" content="Politique de protection des données personnelles de StudyVibe LMS.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        .privacy-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 2.5rem;
            background: #FFFFFF;
            border: 1px solid rgba(0, 75, 35, 0.15);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
            border-radius: 4px;
            position: relative;
        }
        .privacy-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #004B23 0%, #00873F 50%, #C9A84C 100%);
            border-radius: 4px 4px 0 0;
        }
        .privacy-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.25rem;
            font-weight: 500;
            color: #111111;
            margin-bottom: 0.5rem;
        }
        .privacy-subtitle {
            font-size: 0.875rem;
            color: #555555;
            margin-bottom: 2.5rem;
            font-weight: 300;
            border-bottom: 1px solid #E5E5E7;
            padding-bottom: 1rem;
        }
        .privacy-section {
            margin-bottom: 2rem;
        }
        .privacy-section-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: #004B23;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .privacy-text {
            font-size: 0.875rem;
            color: #333333;
            line-height: 1.6;
            margin-bottom: 1rem;
            font-weight: 300;
        }
        .privacy-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.8125rem;
        }
        .privacy-table th {
            background: #F9F7F4;
            color: #111111;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.75rem;
            border-bottom: 2px solid #004B23;
            text-align: left;
        }
        .privacy-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #E5E5E7;
            color: #444444;
            line-height: 1.5;
        }
        .privacy-table tr:hover {
            background: #F9F9FB;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #004B23;
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 2rem;
            transition: color 0.15s;
        }
        .back-link:hover {
            color: #111111;
        }
        .highlight-box {
            background: rgba(0, 75, 35, 0.03);
            border-left: 3px solid #004B23;
            padding: 1rem 1.25rem;
            margin: 1.5rem 0;
            border-radius: 0 4px 4px 0;
        }
        .highlight-box p {
            margin: 0;
            font-size: 0.8125rem;
            color: #555555;
            line-height: 1.5;
        }
    </style>
</head>
<body class="sv-cream">

<div class="max-w-4xl mx-auto px-4 py-8">
    <a href="/" class="back-link">
        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
        </svg>
        Retour à l'accueil
    </a>

    <div class="privacy-container">
        <h1 class="privacy-title">Politique de Confidentialité</h1>
        <p class="privacy-subtitle">Dernière mise à jour : 24 juin 2026</p>

        <div class="privacy-section">
            <h2 class="privacy-section-title">1. Notre Engagement</h2>
            <p class="privacy-text">
                Chez <strong>StudyVibe</strong>, nous croyons qu'une plateforme d'apprentissage académique doit mériter la confiance absolue de ses étudiants, enseignants et administrateurs. C'est pourquoi nous appliquons une politique de transparence totale : <strong>vos données restent les vôtres</strong>. Nous ne revendons aucune information et n'utilisons aucun traceur publicitaire ou commercial tiers.
            </p>
        </div>

        <div class="privacy-section">
            <h2 class="privacy-section-title">2. Données Collectées & Finalités d'Utilisation</h2>
            <p class="privacy-text">
                Nous collectons uniquement les informations strictement nécessaires à la fourniture et à la sécurité du service LMS. Voici le détail précis de l'utilisation de chaque donnée :
            </p>

            <table class="privacy-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Donnée Collectée</th>
                        <th style="width: 45%;">Finalité & Usage Précis</th>
                        <th style="width: 30%;">Durée de Conservation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Nom & Adresse E-mail</strong></td>
                        <td>Création et sécurisation de votre compte, personnalisation de l'espace de travail, et envoi des certificats officiels ou alertes académiques (ex: newsletters de cours).</td>
                        <td>Tant que votre compte reste actif. Supprimé sous 30 jours après fermeture du compte.</td>
                    </tr>
                    <tr>
                        <td><strong>Mot de passe (crypté)</strong></td>
                        <td>Authentification sécurisée. Les mots de passe sont hachés de manière irréversible via l'algorithme fort <code>bcrypt</code> en base de données.</td>
                        <td>Tant que votre compte est actif.</td>
                    </tr>
                    <tr>
                        <td><strong>Progression Académique</strong></td>
                        <td>Enregistrement du statut de lecture des leçons, logs de sessions d'étude, scores aux quiz de leçons et scores aux tentatives d'examens finaux. Requis pour le calcul du taux de réussite global (seuil de 80%) et la délivrance automatique des certifications.</td>
                        <td>Lié à votre parcours d'études. Conservé pendant toute la durée de vie du compte étudiant.</td>
                    </tr>
                    <tr>
                        <td><strong>Certificats officiels</strong></td>
                        <td>Stockage du code unique du certificat, du module associé, de la date d'attribution et de l'autorité émettrice. Utilisé pour la vérification publique ou le téléchargement au format PDF.</td>
                        <td>À des fins de vérification de diplôme, ces informations sont archivées de façon permanente sauf demande explicite d'effacement.</td>
                    </tr>
                    <tr>
                        <td><strong>Journaux de Sécurité (IP & Audit Logs)</strong></td>
                        <td>Adresses IP lors des tentatives de connexion pour la protection brute-force. Historique d'audit des actions importantes (changements de rôle, émissions de diplômes, modifications de mot de passe) pour assurer la traçabilité.</td>
                        <td>Les journaux d'IP sont nettoyés tous les 90 jours. Les logs d'audit sont conservés 1 an.</td>
                    </tr>
                    <tr>
                        <td><strong>Abonnements Newsletter</strong></td>
                        <td>Adresse de messagerie stockée séparément pour les lettres d'information académiques et les annonces de la communauté.</td>
                        <td>Jusqu'à votre désabonnement en un clic depuis les emails reçus ou votre profil.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="privacy-section">
            <h2 class="privacy-section-title">3. Hébergement, Sécurité & Confidentialité</h2>
            <div class="privacy-text">
                <ul style="list-style-type: square; padding-left: 1.25rem; margin-bottom: 1rem;">
                    <li style="margin-bottom: 0.5rem;"><strong>Souveraineté des données :</strong> Toutes nos bases de données et serveurs sont situés dans des datacenters hautement sécurisés en Union Européenne, garantissant la conformité stricte au Règlement Général sur la Protection des Données (RGPD).</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Zéro Partage :</strong> Nous ne partageons, ne louons et ne vendons jamais vos données personnelles à des régies publicitaires, courtiers de données ou autres tiers.</li>
                    <li style="margin-bottom: 0.5rem;"><strong>Cookies techniques uniquement :</strong> Notre plateforme utilise uniquement des cookies de session strictement techniques (pour maintenir votre session utilisateur active) et des jetons CSRF pour vous prémunir des cyberattaques de type cross-site request forgery.</li>
                </ul>
            </div>
            <div class="highlight-box">
                <p>
                    <strong>Note de sécurité :</strong> Toutes les communications entre votre navigateur et nos serveurs sont cryptées de bout en bout via le protocole sécurisé HTTPS (SSL/TLS). Les pièces jointes et couvertures de cours sont protégées par un système de contrôle d'accès rigoureux.
                </p>
            </div>
        </div>

        <div class="privacy-section">
            <h2 class="privacy-section-title">4. Vos Droits (RGPD) & Contact</h2>
            <p class="privacy-text">
                Conformément à la réglementation sur la protection des données personnelles, vous disposez d'un droit d'accès, de rectification, de portabilité et de suppression de toutes vos données.
            </p>
            <p class="privacy-text">
                Pour toute demande relative à vos données personnelles ou pour fermer définitivement votre compte et effacer vos historiques de progression, vous pouvez contacter directement l'équipe de support ou le Promoteur de la plateforme à l'adresse suivante : <a href="mailto:danielwilfriedtakou@gmail.com" style="color: #004B23; font-weight: 500; text-decoration: underline;">danielwilfriedtakou@gmail.com</a>.
            </p>
        </div>
    </div>
</div>

</body>
</html>
