<?php
declare(strict_types=1);

/**
 * StudyVibe LMS - Landing & Authentication Portal
 * 
 * Serves as the public gateway page. Welcomes visitors, manages locale selection switches, 
 * renders showcase carousels representing system features, and implements security controls 
 * for login, password-reset requests, and multi-step registration (student/teacher profiles).
 * Rediriges logged-in users automatically to their respective roles' dashboards.
 * 
 * @package    StudyVibe
 * @author     Advanced Engineering Team
 */

require_once __DIR__ . '/auth.php';

// =========================================================================
// SECTION 1: USER SESSION REDIRECTION GATE
// =========================================================================

if (isLoggedIn()) {
    $map = [
        'promoter' => '/promoter/dashboard.php', 
        'teacher' => '/teacher/dashboard.php', 
        'student' => '/student/dashboard.php'
    ];
    header('Location: ' . ($map[$_SESSION['user_role']] ?? '/index.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/assets/img/favicon.png">

    <title>StudyVibe — Plateforme Académique</title>
    <meta name="description" content="StudyVibe, la plateforme LMS conçue pour l'enseignement supérieur.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <!-- Injects CSRF token security meta tags for fetch requests -->
    <?= csrfMetaTag(); ?>
    
    <script>document.documentElement.classList.add('js-enabled');</script>
    <style>
        .js-enabled .sv-hero-content, 
        .js-enabled .sv-auth-card, 
        .js-enabled .sv-feature-card, 
        .js-enabled .sv-step-card { 
            opacity: 0; 
        }
        /* Showdown Carousel Custom Styles */
        .sv-showdown-viewport {
            overflow: hidden;
            position: relative;
            width: 100%;
            padding: 2rem 0;
        }
        .sv-showdown-viewport::before, .sv-showdown-viewport::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0;
            width: 160px;
            z-index: 10;
            pointer-events: none;
        }
        .sv-showdown-viewport::before {
            left: 0;
            background: linear-gradient(to right, var(--sv-cream), transparent);
        }
        .sv-showdown-viewport::after {
            right: 0;
            background: linear-gradient(to left, var(--sv-cream), transparent);
        }
        .sv-showdown-track {
            display: flex;
            gap: 4rem;
            width: max-content;
            padding: 0 4rem;
            will-change: transform;
        }
        .sv-showdown-panel {
            width: 850px;
            flex-shrink: 0;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .sv-showdown-panel:hover {
            box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.12);
        }
    </style>
</head>
<body class="sv-landing sv-page">

<!-- =========================================================================
     SECTION 2: NAVIGATION HEADER BAR
     ========================================================================= -->
<nav class="sv-navbar" role="navigation" aria-label="Navigation principale">
    <a href="#accueil" class="sv-navbar-brand">
        <svg class="w-9 h-9" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 36px; height: 36px;">
            <circle cx="50" cy="50" r="46" stroke="#006630" stroke-width="3.5" />
            <line x1="33" y1="31" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <line x1="33" y1="31" x2="49" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <line x1="33" y1="31" x2="14" y2="13" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <line x1="33" y1="31" x2="42" y2="11" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <line x1="33" y1="31" x2="20" y2="53" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <line x1="49" y1="53" x2="62" y2="25" stroke="#111111" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="62" cy="25" r="6" fill="#006630" />
            <circle cx="49" cy="53" r="6" fill="#006630" />
            <circle cx="33" cy="31" r="6" fill="#006630" />
            <circle cx="14" cy="13" r="6" fill="#006630" />
            <circle cx="42" cy="11" r="6" fill="#006630" />
            <circle cx="20" cy="53" r="6" fill="#006630" />
            <path d="M56 10 C52 14, 52 24, 52 29 C52 31, 50 33, 49 33 L45 33 L49 35 C50 37, 51 38, 50 40 C49 41, 47 42, 49 44 C51 45, 54 46, 56 46 C59 46, 65 38, 66 41 C68 46, 60 52, 56 60 C51 68, 50 78, 53 88" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M33 55 C32 52, 32 48, 33 46 C34 44, 36 44, 37 47 C37 50, 37 53, 37 55 C37 51, 38 46, 39 44 C40 42, 42 42, 43 45 C43 48, 43 51, 43 54 C43 51, 44 47, 45 45 C46 43, 48 43, 49 46 C50 49, 51 57, 51 68 C51 75, 49 81, 47 85" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M33 55 C34 61, 35 68, 37 75 C38 81, 39 84, 40 86" stroke="#111111" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        StudyVibe
    </a>
    <div class="sv-navbar-links">
        <a href="/evaluations.php" class="sv-navbar-link" style="color:var(--004B23); font-weight:600;">Évaluations</a>
        <a href="#fonctionnalites" class="sv-navbar-link">Fonctionnalités</a>
        <a href="#roles" class="sv-navbar-link">Pour qui</a>
        <a href="#comment" class="sv-navbar-link">Comment ça marche</a>
        <button type="button" class="sv-btn sv-btn-primary" onclick="openSignup()">Rejoindre</button>
        <button class="sv-dark-toggle" data-dark-toggle title="Mode sombre"></button>
        <div class="relative inline-block text-left">
            <select id="lang-selector" onchange="changeLanguage(this.value)" class="bg-transparent text-xs border border-[#E5E5E7] text-[#555555] rounded-sm py-1 px-2 focus:outline-none focus:border-[#004B23]">
                <option value="fr" <?= TranslationService::getLang() === 'fr' ? 'selected' : ''; ?>>FR</option>
                <option value="en" <?= TranslationService::getLang() === 'en' ? 'selected' : ''; ?>>EN</option>
            </select>
        </div>
    </div>
</nav>

<!-- =========================================================================
     SECTION 3: HERO LAYOUT WITH INTERACTIVE GATEWAY PANEL
     ========================================================================= -->
<section class="sv-hero" id="accueil">
    <div class="sv-hero-grid">

        <!-- Column Left: Messaging -->
        <div class="sv-hero-content">
            <div class="sv-eyebrow">Plateforme académique</div>
            <h1 class="sv-hero-headline">
                Enseigner.<br>Apprendre.<br>Progresser.
            </h1>
            <p class="sv-hero-sub">
                StudyVibe connecte promoteurs, enseignants et étudiants dans un espace pédagogique structuré — conçu pour l'efficacité, la rigueur et le suivi en temps réel.
            </p>
            <div class="sv-hero-actions">
                <button type="button" class="sv-btn sv-btn-primary" onclick="openSignup()">Créer un compte</button>
                <a href="#fonctionnalites" class="sv-btn sv-btn-outline">Découvrir</a>
            </div>
            <div style="display:flex; gap:0.5rem; margin-top:2.5rem; flex-wrap:wrap;">
                <span class="sv-badge sv-badge-accent">Multi-rôles</span>
                <span class="sv-badge">Suivi en temps réel</span>
                <span class="sv-badge">Certifications</span>
            </div>
        </div>

        <!-- Column Right: Dynamic Registration/Login Widget -->
        <div class="sv-auth-card" id="auth-card">
            <div class="sv-auth-card-header">
                <div class="sv-auth-card-title">Accédez à StudyVibe</div>
                <div class="sv-auth-card-sub">Connexion ou inscription en quelques secondes</div>
            </div>

            <div class="sv-form-tabs" role="tablist">
                <button class="sv-form-tab active" id="tab-login" role="tab" aria-selected="true" onclick="switchAuthTab('login')">Connexion</button>
                <button class="sv-form-tab" id="tab-signup" role="tab" aria-selected="false" onclick="switchAuthTab('signup')">Inscription</button>
            </div>

            <!-- Login Panel Form -->
            <div id="panel-login" class="sv-form-panel active" role="tabpanel">
                <form id="login-form" novalidate>
                    <div class="sv-field">
                        <label for="login-email" class="sv-field-label">Adresse électronique</label>
                        <input type="email" id="login-email" name="email" required autocomplete="email"
                               placeholder="vous@exemple.com" class="sv-field-input">
                    </div>
                    <div class="sv-field">
                        <label for="login-password" class="sv-field-label">Mot de passe</label>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password"
                               placeholder="••••••••" class="sv-field-input">
                    </div>
                    <button type="submit" id="login-btn" class="sv-btn-submit">Se connecter</button>
                </form>
                <p class="sv-form-footer" style="margin-top:0.5rem;">
                    <button type="button" onclick="openForgotPassword()" style="font-size:0.8125rem;color:#004B23;background:none;border:none;cursor:pointer;text-decoration:underline;">Mot de passe oublié ?</button>
                </p>
                <div id="forgot-panel">
                    <p class="sv-field-hint" style="margin-bottom:0.75rem;">Entrez votre email pour recevoir un lien de réinitialisation.</p>
                    <div class="sv-field">
                        <label for="forgot-email" class="sv-field-label">Adresse électronique</label>
                        <input type="email" id="forgot-email" class="sv-field-input" placeholder="vous@exemple.com">
                    </div>
                    <button type="button" id="forgot-btn" class="sv-btn-submit" style="margin-top:0.5rem;">Envoyer le lien</button>
                </div>
                <p class="sv-form-footer">Pas encore de compte ? <button type="button" onclick="switchAuthTab('signup')">Créer un compte</button></p>
            </div>

            <!-- Signup Multi-step Form -->
            <div id="panel-signup" class="sv-form-panel" role="tabpanel">
                <form id="signup-form" novalidate>
                    <div class="sv-signup-stepper" aria-label="Étapes d'inscription">
                        <div class="sv-signup-stepper-item active" data-step="1">
                            <span class="sv-signup-stepper-dot">1</span>
                            <span class="sv-signup-stepper-label">Profil</span>
                        </div>
                        <div class="sv-signup-stepper-line" aria-hidden="true"></div>
                        <div class="sv-signup-stepper-item" data-step="2">
                            <span class="sv-signup-stepper-dot">2</span>
                            <span class="sv-signup-stepper-label">Compte</span>
                        </div>
                    </div>

                    <!-- Step 1: Role Selection -->
                    <div class="sv-signup-step active" id="signup-step-1" data-step="1">
                        <p class="sv-signup-intro">Choisissez le profil qui correspond à votre usage.</p>
                        <div class="sv-field sv-signup-role-field">
                            <label for="signup-role" class="sv-field-label">Je m'inscris en tant que</label>
                            <div class="sv-select-wrap">
                                <select id="signup-role" name="role" class="sv-field-input sv-field-select" required>
                                    <option value="" disabled selected>Sélectionnez votre profil</option>
                                    <option value="student">Apprenant — suivre des cours</option>
                                    <option value="teacher">Enseignant — créer et animer</option>
                                </select>
                                <svg class="sv-select-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9l6 6 6-6"/>
                                </svg>
                            </div>
                            <div class="sv-field-hint" id="hint-role">Apprenant ou enseignant — modifiable à l'étape suivante.</div>
                        </div>
                        <button type="button" id="signup-next" class="sv-btn-submit" disabled>Continuer</button>
                    </div>

                    <!-- Step 2: Personal Profile Data -->
                    <div class="sv-signup-step" id="signup-step-2" data-step="2" hidden>
                        <div class="sv-signup-role-chip" id="signup-role-chip">
                            <span id="signup-role-chip-label"></span>
                            <button type="button" id="signup-change-role" class="sv-signup-role-change">Modifier</button>
                        </div>
                        <div class="sv-field">
                            <label for="signup-name" class="sv-field-label">Nom complet</label>
                            <input type="text" id="signup-name" name="name" required placeholder="Marie Curie"
                                   class="sv-field-input" autocomplete="name">
                        </div>
                        <div class="sv-field">
                            <label for="signup-email" class="sv-field-label">Adresse électronique</label>
                            <input type="email" id="signup-email" name="email" required placeholder="vous@exemple.com"
                                   class="sv-field-input" autocomplete="email">
                        </div>
                        <div class="sv-field">
                            <label for="signup-password" class="sv-field-label">Mot de passe</label>
                            <input type="password" id="signup-password" name="password" required minlength="6"
                                   placeholder="6 caractères minimum" class="sv-field-input" autocomplete="new-password">
                            <div class="sv-pw-strength"><div class="sv-pw-strength-bar" id="pw-bar"></div></div>
                            <div class="sv-field-hint" id="hint-password">Minimum 6 caractères</div>
                        </div>
                        <label class="sv-checkbox-field" for="signup-newsletter">
                            <input type="checkbox" id="signup-newsletter" name="newsletter" value="1">
                            <span>Newsletter StudyVibe <em>(optionnel)</em></span>
                        </label>
                        <div class="sv-signup-actions">
                            <button type="button" id="signup-back" class="sv-btn-back">Retour</button>
                            <button type="submit" id="signup-btn" class="sv-btn-submit" disabled>Créer mon compte</button>
                        </div>
                    </div>
                </form>
                <p class="sv-form-footer">Déjà inscrit ? <button type="button" onclick="switchAuthTab('login')">Se connecter</button></p>
            </div>
        </div>

    </div>
</section>

<!-- =========================================================================
     SECTION 4: PARTNERSHIPS LOGO MARQUEE
     ========================================================================= -->
<div class="sv-partners-band" role="region" aria-label="Entreprises de formation">
    <div class="sv-container">
        <p class="sv-partners-eyebrow">Écosystème formation &amp; EdTech</p>
        <div class="sv-partners-marquee" aria-hidden="true">
            <div class="sv-partners-track">
                <?php
                $partners = [
                    ['file' => 'coursera.svg', 'name' => 'Coursera'],
                    ['file' => 'udemy.svg', 'name' => 'Udemy'],
                    ['file' => 'edx.svg', 'name' => 'edX'],
                    ['file' => 'pluralsight.svg', 'name' => 'Pluralsight'],
                    ['file' => 'instructure.svg', 'name' => 'Instructure'],
                    ['file' => '2u.jpeg', 'name' => '2U'],
                    ['file' => 'chegg.svg', 'name' => 'Chegg'],
                    ['file' => 'linkedin.svg', 'name' => 'LinkedIn'],
                    ['file' => 'canva.svg', 'name' => 'Canva'],
                    ['file' => 'google-cloud.jpeg', 'name' => 'Google Cloud'],
                    ['file' => 'apple.svg', 'name' => 'Apple'],
                    ['file' => 'figma.svg', 'name' => 'Figma'],
                    ['file' => 'airbnb.svg', 'name' => 'Airbnb'],
                ];
                $renderLogos = function () use ($partners) {
                    foreach ($partners as $p) {
                        echo '<div class="sv-partner-logo">';
                        echo '<img src="/assets/logos/partners/' . htmlspecialchars($p['file']) . '" alt="' . htmlspecialchars($p['name']) . '" loading="lazy" width="200" height="60">';
                        echo '</div>';
                    }
                };
                $renderLogos();
                $renderLogos();
                ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     SECTION 5: PHILOSOPHY & STORYTELLING
     ========================================================================= -->
<section class="sv-section" id="presentation-story" style="background:#fff;">
    <div class="sv-container" style="display:grid; grid-template-columns:1fr 1fr; gap:4rem; align-items:center;">
        <div>
            <div class="sv-eyebrow">Notre Philosophie</div>
            <h2 class="sv-section-title">L'apprentissage académique, repensé pour la clarté et l'engagement.</h2>
            <p class="sv-section-sub" style="margin-bottom:1.5rem;">Parce que l'éducation mérite mieux que des interfaces encombrées et des processus rigides, nous avons conçu StudyVibe pour allier concentration, interactivité et transparence.</p>
            <p style="font-size:0.875rem; color:var(--sv-text-muted); line-height:1.7; font-weight:300;">
                Nos modules de lecture, nos outils d'évaluation synchrone (QuizBox) et nos classes virtuelles intégrées travaillent de concert pour offrir une expérience d'étude moderne et fluide. Chaque apprenant dispose d'un espace personnel favorisant la prise de notes et le rythme individuel, tandis que les enseignants pilotent leurs promotions grâce à des statistiques de réussite détaillées et des parcours d'apprentissage automatisés.
            </p>
        </div>
        <div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">
            <div class="sv-glass-card" style="padding:2rem; display:flex; gap:1.5rem; align-items:flex-start; background:var(--sv-cream-light);">
                <div style="background:rgba(0,75,35,0.06); color:#004B23; width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </div>
                <div>
                    <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.125rem; font-weight:600; color:var(--sv-text);">Focus & Concentration</h3>
                    <p style="font-size:0.8125rem; color:var(--sv-text-muted); margin-top:0.35rem; line-height:1.5; font-weight:300;">Une liseuse sans distraction, conçue pour minimiser la charge cognitive et optimiser l'ancrage mémoriel.</p>
                </div>
            </div>
            <div class="sv-glass-card" style="padding:2rem; display:flex; gap:1.5rem; align-items:flex-start; background:var(--sv-cream-light);">
                <div style="background:rgba(201,168,76,0.08); color:#C9A84C; width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <div>
                    <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.125rem; font-weight:600; color:var(--sv-text);">Compagnon IA Interactif</h3>
                    <p style="font-size:0.8125rem; color:var(--sv-text-muted); margin-top:0.35rem; line-height:1.5; font-weight:300;">Un assistant virtuel disponible 24h/24 pour synthétiser vos leçons, répondre à vos questions et générer des quiz sur-mesure.</p>
                </div>
            </div>
            <div class="sv-glass-card" style="padding:2rem; display:flex; gap:1.5rem; align-items:flex-start; background:var(--sv-cream-light);">
                <div style="background:rgba(0,0,0,0.05); color:var(--sv-text); width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width: 24px; height: 24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.125rem; font-weight:600; color:var(--sv-text);">Preuve de Compétence</h3>
                    <p style="font-size:0.8125rem; color:var(--sv-text-muted); margin-top:0.35rem; line-height:1.5; font-weight:300;">Des attestations numériques vérifiables instantanément grâce à un identifiant unique sécurisé.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 6: CORE SYSTEM FEATURES LIST
     ========================================================================= -->
<section class="sv-section" id="fonctionnalites">
    <div class="sv-container">
        <div class="sv-eyebrow">Fonctionnalités</div>
        <h2 class="sv-section-title">Une seule plateforme.<br>Tous les outils pédagogiques.</h2>
        <p class="sv-section-sub">De la création de cours à la certification — chaque outil réduit la friction et maximise l'apprentissage.</p>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:1rem; margin-top:3rem;">
            <div class="sv-feature-card">
                <div class="sv-eyebrow" style="margin-bottom:0.75rem;">Gestion des cours</div>
                <h3 class="sv-section-title" style="font-size:1.375rem; margin-bottom:0.75rem;">Créez. Publiez. Gérez.</h3>
                <p style="font-size:0.875rem; color:var(--sv-text-muted); line-height:1.65; font-weight:300;">Structurez vos modules, publiez du contenu mixte et suivez la progression depuis un tableau de bord unique.</p>
            </div>
            <div class="sv-feature-card">
                <div class="sv-eyebrow" style="margin-bottom:0.75rem;">Suivi étudiant</div>
                <h3 class="sv-section-title" style="font-size:1.375rem; margin-bottom:0.75rem;">Progression en temps réel.</h3>
                <p style="font-size:0.875rem; color:var(--sv-text-muted); line-height:1.65; font-weight:300;">Visualisez l'avancement, identifiez les difficultés et intervenez au bon moment.</p>
            </div>
            <div class="sv-feature-card">
                <div class="sv-eyebrow" style="margin-bottom:0.75rem;">Administration</div>
                <h3 class="sv-section-title" style="font-size:1.375rem; margin-bottom:0.75rem;">Pilotez votre établissement.</h3>
                <p style="font-size:0.875rem; color:var(--sv-text-muted); line-height:1.65; font-weight:300;">Configurez les accès, supervisez les cohortes et exportez les certifications.</p>
            </div>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 7: SHOWCASE HORIZONTAL SCROLL CAROUSEL
     ========================================================================= -->
<section class="sv-section" id="aperçu-cours" style="background:var(--sv-cream-light);">
    <div class="sv-container">
        <div class="sv-eyebrow">Aperçu de la Plateforme</div>
        <h2 class="sv-section-title">Découvrez l'Écosystème StudyVibe.<br>Une expérience intégrée en 4 dimensions.</h2>
        <p class="sv-section-sub" style="margin-bottom:3.5rem;">Glissez ou observez le défilement automatique des différentes interfaces conçues pour optimiser chaque étape de l'apprentissage.</p>
    </div>

    <!-- Showdown Viewport Slider -->
    <div class="sv-showdown-viewport">
        <div class="sv-showdown-track">
            
            <!-- Panel 1: Course Reader Preview -->
            <div class="sv-showdown-panel">
                <div class="sv-preview-header">
                    <div class="sv-preview-dot" style="background:#FF5F56;"></div>
                    <div class="sv-preview-dot" style="background:#FFBD2E;"></div>
                    <div class="sv-preview-dot" style="background:#27C93F;"></div>
                    <div class="sv-preview-address">https://app.studyvibe.edu/student/lesson?id=104</div>
                </div>
                <div style="display:grid; grid-template-columns:260px 1fr; min-height:480px; font-family:'Inter', sans-serif;">
                    <!-- Sidebar -->
                    <div style="border-right:1px solid rgba(0,0,0,0.06); padding:1.5rem; background:rgba(0,0,0,0.015); display:flex; flex-direction:column; gap:1.25rem;">
                        <div style="font-weight:700; font-size:0.875rem; color:var(--sv-text);">Algèbre Linéaire</div>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <div style="padding:0.6rem 0.75rem; background:var(--sv-text); color:#fff; font-size:0.75rem; font-weight:600; border-radius:6px;">1. Introduction aux matrices</div>
                            <div style="padding:0.6rem 0.75rem; background:transparent; color:var(--sv-text-muted); font-size:0.75rem; font-weight:500; border-radius:6px;">2. Produit matriciel</div>
                            <div style="padding:0.6rem 0.75rem; background:transparent; color:var(--sv-text-muted); font-size:0.75rem; font-weight:500; border-radius:6px;">3. Déterminants & inverses</div>
                        </div>
                        <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(0,0,0,0.06);">
                            <div style="font-size:0.6875rem; color:var(--sv-text-muted); margin-bottom:0.35rem;">VOTRE SCORE : 82.4%</div>
                            <div style="width:100%; height:4px; background:rgba(0,0,0,0.05); border-radius:2px; overflow:hidden;">
                                <div style="width:82.4%; height:100%; background:#004B23;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Main Area -->
                    <div style="padding:2.5rem; display:flex; flex-direction:column; gap:2rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="sv-badge sv-badge-accent" style="font-size:0.625rem; font-weight:700; border-radius:12px;">LEÇON ACTIVE</span>
                                <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.5rem; font-weight:500; margin-top:0.35rem; color:var(--sv-text);">Matrices et transformations géométriques</h3>
                            </div>
                            <span class="sv-badge" style="border-radius:12px; font-weight:600;">14:32 étudiés</span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 280px; gap:2rem; flex:1;">
                            <div style="border:1px dashed rgba(0,0,0,0.12); border-radius:8px; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:2rem; text-align:center; background:#fafafa;">
                                <svg fill="none" stroke="#004B23" stroke-width="1.5" viewBox="0 0 24 24" width="40" height="40" style="margin-bottom:0.75rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                                <div style="font-size:0.8125rem; font-weight:600; color:var(--sv-text);">Liseuse PDF de cours intégrée</div>
                                <div style="font-size:0.75rem; color:var(--sv-text-muted); max-width:240px; margin-top:0.25rem;">Les cours sont gates et déverrouillés uniquement après complétion des chapitres requis.</div>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:1rem;">
                                <div style="border:1px solid rgba(0,75,35,0.08); background:rgba(0,75,35,0.02); padding:1rem; border-radius:8px;">
                                    <div style="font-size:0.75rem; font-weight:700; color:#004B23; display:flex; align-items:center; gap:0.35rem;">
                                        <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#C9A84C;"></span>
                                        Assistant Pédagogique IA
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.35rem; line-height:1.4;">« Vous avez une question sur cette leçon ? Je peux synthétiser le PDF ou vous proposer un quiz d'entraînement. »</div>
                                </div>
                                <div style="border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px;">
                                    <div style="font-size:0.75rem; font-weight:600; color:var(--sv-text);">Notes personnelles</div>
                                    <div style="font-size:0.6875rem; color:var(--sv-text-muted); margin-top:0.25rem;">Note prise à 04:12 : &quot;Vérifier le déterminant d'une matrice inverse&quot;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Live Quiz Room Preview -->
            <div class="sv-showdown-panel">
                <div class="sv-preview-header">
                    <div class="sv-preview-dot" style="background:#FF5F56;"></div>
                    <div class="sv-preview-dot" style="background:#FFBD2E;"></div>
                    <div class="sv-preview-dot" style="background:#27C93F;"></div>
                    <div class="sv-preview-address">https://app.studyvibe.edu/live-evaluation?code=ALG301</div>
                </div>
                <div style="display:grid; grid-template-columns:260px 1fr; min-height:480px; font-family:'Inter', sans-serif;">
                    <!-- Sidebar stats and status -->
                    <div style="border-right:1px solid rgba(0,0,0,0.06); padding:1.5rem; background:rgba(0,0,0,0.015); display:flex; flex-direction:column; gap:1.25rem;">
                        <div style="font-weight:700; font-size:0.875rem; color:var(--sv-text);">Session : ALG301</div>
                        <div style="font-size:0.75rem; color:var(--sv-text-muted);">
                            <strong>Mode :</strong> Synchrone direct<br>
                            <strong>Candidats actifs :</strong> 48<br>
                            <strong>Question :</strong> 4 sur 10
                        </div>
                        <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(0,0,0,0.06);">
                            <div style="font-size:0.6875rem; color:var(--sv-text-muted); margin-bottom:0.35rem;">TEMPS RESTANT</div>
                            <div style="width:100%; height:4px; background:rgba(0,0,0,0.05); border-radius:2px; overflow:hidden;">
                                <div style="width:45%; height:100%; background:#EF4444;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Main Area Question -->
                    <div style="padding:2.5rem; display:flex; flex-direction:column; gap:2rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="sv-badge" style="background:#EF4444; border-color:#EF4444; color:#fff; font-size:0.625rem; font-weight:700; border-radius:12px;">ÉVALUATION LIVE</span>
                                <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.5rem; font-weight:500; margin-top:0.35rem; color:var(--sv-text);">Algèbre Linéaire & Géométrie</h3>
                            </div>
                            <span style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.5rem; font-weight:700; color:#EF4444;">18s</span>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:1rem; flex:1;">
                            <div style="font-size:0.95rem; font-weight:600; color:var(--sv-text); line-height:1.5;">Question 4/10 : Laquelle des propositions définit une relation d'équivalence ?</div>
                            <div style="display:flex; flex-direction:column; gap:0.6rem; margin-top:0.5rem;">
                                <div style="padding:0.75rem 1rem; border:1px solid #004B23; background:rgba(0,75,35,0.02); font-size:0.75rem; font-weight:600; border-radius:8px; display:flex; align-items:center; gap:0.75rem;">
                                    <span style="width:20px; height:20px; border-radius:50%; background:#004B23; color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.65rem;">A</span>
                                    Une relation réflexive, symétrique et transitive.
                                </div>
                                <div style="padding:0.75rem 1rem; border:1px solid rgba(0,0,0,0.08); font-size:0.75rem; border-radius:8px; display:flex; align-items:center; gap:0.75rem;">
                                    <span style="width:20px; height:20px; border-radius:50%; background:rgba(0,0,0,0.05); color:var(--sv-text-muted); display:flex; align-items:center; justify-content:center; font-size:0.65rem;">B</span>
                                    Une relation transitive et réflexive uniquement.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 3: Teacher Dashboard Preview -->
            <div class="sv-showdown-panel">
                <div class="sv-preview-header">
                    <div class="sv-preview-dot" style="background:#FF5F56;"></div>
                    <div class="sv-preview-dot" style="background:#FFBD2E;"></div>
                    <div class="sv-preview-dot" style="background:#27C93F;"></div>
                    <div class="sv-preview-address">https://app.studyvibe.edu/teacher/dashboard</div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 260px; min-height:480px; font-family:'Inter', sans-serif;">
                    <!-- Left: Main Workspace -->
                    <div style="background:#FAF9F6; padding:2rem; display:flex; flex-direction:column; gap:1.5rem; justify-content:flex-start;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="sv-badge sv-badge-accent" style="font-size:0.625rem; font-weight:700; border-radius:12px;">ESPACE ENSEIGNANT</span>
                                <h4 style="font-size:1.25rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:500; margin-top:0.25rem; color:var(--sv-text);">Suivi de la cohorte : Algorithmique</h4>
                            </div>
                            <span class="sv-badge" style="border-radius:12px; font-weight:600; background:#fff;">24 Apprenants</span>
                        </div>
                        
                        <!-- KPIs -->
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                            <div style="background:#fff; border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px;">
                                <div style="font-size:0.6875rem; color:var(--sv-text-muted); font-weight:700; uppercase">MOYENNE</div>
                                <div style="font-size:1.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#004B23; margin-top:0.25rem;">14.2<span style="font-size:0.875rem;">/20</span></div>
                            </div>
                            <div style="background:#fff; border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px;">
                                <div style="font-size:0.6875rem; color:var(--sv-text-muted); font-weight:700; uppercase">PROGRESSION</div>
                                <div style="font-size:1.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#004B23; margin-top:0.25rem;">78.5%</div>
                            </div>
                            <div style="background:#fff; border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px;">
                                <div style="font-size:0.6875rem; color:var(--sv-text-muted); font-weight:700; uppercase">CERTIFIÉS</div>
                                <div style="font-size:1.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#C9A84C; margin-top:0.25rem;">16<span style="font-size:0.875rem;"> / 24</span></div>
                            </div>
                        </div>

                        <!-- Class Progress list mockup -->
                        <div style="background:#fff; border:1px solid rgba(0,0,0,0.06); border-radius:8px; padding:1.25rem; display:flex; flex-direction:column; gap:0.75rem;">
                            <div style="font-size:0.75rem; font-weight:700; color:var(--sv-text); border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:0.5rem;">Activité récente des apprenants</div>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem;">
                                <span style="font-weight:600;">Sophie V.</span>
                                <span style="color:var(--sv-text-muted);">A complété la leçon : Graphes connexes</span>
                                <span style="background:rgba(0,75,35,0.08); color:#004B23; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700; font-size:0.625rem;">A l'instant</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; border-top:1px solid rgba(0,0,0,0.04); padding-top:0.5rem;">
                                <span style="font-weight:600;">Arthur D.</span>
                                <span style="color:var(--sv-text-muted);">A obtenu 16.5/20 au QCM Chapitre 2</span>
                                <span style="color:var(--sv-text-muted); font-size:0.625rem;">Il y a 10m</span>
                            </div>
                        </div>
                    </div>
                    <!-- Right: Quick actions panel -->
                    <div style="border-left:1px solid rgba(0,0,0,0.06); background:#fff; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem;">
                        <div style="font-weight:700; font-size:0.8125rem; color:var(--sv-text); border-bottom:1px solid rgba(0,0,0,0.06); padding-bottom:0.5rem;">Gestion de Cours</div>
                        <div style="display:flex; flex-direction:column; gap:0.75rem; flex:1;">
                            <div style="padding:0.75rem 1rem; border:1px solid rgba(0,0,0,0.08); border-radius:6px; font-size:0.75rem; font-weight:600; text-align:center; background:#fafafa; cursor:pointer;">
                                ➕ Ajouter un Chapitre
                            </div>
                            <div style="padding:0.75rem 1rem; border:1px solid rgba(0,0,0,0.08); border-radius:6px; font-size:0.75rem; font-weight:600; text-align:center; background:#fafafa; cursor:pointer;">
                                📝 Créer une Évaluation
                            </div>
                            <div style="padding:0.75rem 1rem; border:1px solid #004B23; color:#004B23; background:rgba(0,75,35,0.02); border-radius:6px; font-size:0.75rem; font-weight:700; text-align:center; cursor:pointer;">
                                ⚡ Évaluation en Direct
                            </div>
                            <div style="padding:0.75rem 1rem; border:1px solid rgba(0,0,0,0.08); border-radius:6px; font-size:0.75rem; font-weight:600; text-align:center; background:#fafafa; cursor:pointer; margin-top:auto;">
                                📥 Exporter les Notes (PDF)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 4: Student Dashboard Preview -->
            <div class="sv-showdown-panel">
                <div class="sv-preview-header">
                    <div class="sv-preview-dot" style="background:#FF5F56;"></div>
                    <div class="sv-preview-dot" style="background:#FFBD2E;"></div>
                    <div class="sv-preview-dot" style="background:#27C93F;"></div>
                    <div class="sv-preview-address">https://app.studyvibe.edu/student/dashboard</div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 240px; min-height:480px; font-family:'Inter', sans-serif;">
                    <!-- Left area -->
                    <div style="padding:2.5rem; display:flex; flex-direction:column; gap:1.5rem;">
                        <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.5rem; font-weight:500; color:var(--sv-text);">Bonjour, Arthur</h3>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div style="border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px; display:flex; flex-direction:column; gap:0.5rem; background:rgba(0,0,0,0.005);">
                                <div style="font-size:0.75rem; font-weight:700; color:#004B23;">COURS EN COURS</div>
                                <div style="font-size:0.875rem; font-weight:600; color:var(--sv-text);">Algorithmique & Graphes</div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.6875rem; color:var(--sv-text-muted); margin-top:auto;">
                                    <span>Progression: 75%</span>
                                    <span style="color:#004B23; font-weight:700;">Continuer →</span>
                                </div>
                            </div>
                            <div style="border:1px solid rgba(0,0,0,0.06); padding:1rem; border-radius:8px; display:flex; flex-direction:column; gap:0.5rem; background:rgba(0,0,0,0.005);">
                                <div style="font-size:0.75rem; font-weight:700; color:#C9A84C;">CERTIFICATION</div>
                                <div style="font-size:0.875rem; font-weight:600; color:var(--sv-text);">Physique Atomique</div>
                                <div style="display:flex; align-items:center; font-size:0.6875rem; color:var(--sv-text-muted); margin-top:auto;">
                                    <span style="background:#004B23; color:#fff; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700; font-size:0.55rem;">TÉLÉCHARGER PDF</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Right area stats -->
                    <div style="background:rgba(0,0,0,0.015); border-left:1px solid rgba(0,0,0,0.06); padding:2rem; display:flex; flex-direction:column; gap:1.5rem;">
                        <div style="font-size:0.75rem; font-weight:700; color:var(--sv-text);">PERFORMANCES</div>
                        <div style="display:flex; flex-direction:column; gap:1rem;">
                            <div>
                                <div style="font-size:1.75rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#004B23;">14.5<span style="font-size:0.875rem;">/20</span></div>
                                <div style="font-size:0.6875rem; color:var(--sv-text-muted);">Moyenne Générale</div>
                            </div>
                            <div>
                                <div style="font-size:1.75rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:var(--sv-text);">86%</div>
                                <div style="font-size:0.6875rem; color:var(--sv-text-muted);">Taux d'assiduité</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 8: PORTAL ROLE SWITCHERS
     ========================================================================= -->
<section class="sv-section" id="roles">
    <div class="sv-container">
        <div class="sv-eyebrow">Pour qui</div>
        <h2 class="sv-section-title">Un espace taillé<br>pour chaque rôle.</h2>
        <div style="display:flex; border-bottom:1px solid var(--sv-border-warm); margin-top:2rem; margin-bottom:2rem; gap:0.25rem;">
            <button class="sv-form-tab active" data-role="promoteur" onclick="switchRole('promoteur')" style="flex:0; padding:0.65rem 1.25rem;">Promoteur</button>
            <button class="sv-form-tab" data-role="enseignant" onclick="switchRole('enseignant')" style="flex:0; padding:0.65rem 1.25rem;">Enseignant</button>
            <button class="sv-form-tab" data-role="etudiant" onclick="switchRole('etudiant')" style="flex:0; padding:0.65rem 1.25rem;">Étudiant</button>
        </div>
        <div id="role-promoteur" class="role-panel active" style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Superviser la structure</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">Statistiques, certifications, gestion des utilisateurs.</p></div>
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Contrôle des accès</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">Créez les comptes, organisez les cohortes et les modules.</p></div>
        </div>
        <div id="role-enseignant" class="role-panel" style="display:none; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Création de contenu</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">Leçons, QCM, évaluations finales et clés d'inscription.</p></div>
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Suivi des apprenants</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">Taux de réussite, progression et scores moyens.</p></div>
        </div>
        <div id="role-etudiant" class="role-panel" style="display:none; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Espace personnel</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">Catalogue, liseuse PDF, chronomètre et Q&A par leçon.</p></div>
            <div class="sv-feature-card"><h3 class="sv-section-title" style="font-size:1.125rem;">Certifications</h3><p style="font-size:0.8125rem;color:var(--sv-text-muted);margin-top:0.5rem;font-weight:300;">QCM final chronométré, certificat PDF et vérification en ligne.</p></div>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 9: SYSTEM STATISTICS COUNTERS
     ========================================================================= -->
<section class="sv-section" id="statistiques-insights" style="background:#fff;">
    <div class="sv-container">
        <div class="sv-eyebrow">Mesure de performance</div>
        <h2 class="sv-section-title">Des résultats quantifiables.<br>Une rigueur académique sans compromis.</h2>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px,1fr)); gap:1.5rem; margin-top:3.5rem;">
            <div class="sv-glass-card" style="padding:2.5rem; text-align:center;">
                <div style="font-size:3.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#004B23; line-height:1;" data-counter="98.2" data-suffix="%">0%</div>
                <div style="font-size:0.875rem; font-weight:600; color:var(--sv-text); margin-top:1rem;">Taux de satisfaction</div>
                <div style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.35rem; font-weight:300;">des enseignants utilisant nos suivis de devoirs et certifications automatisées.</div>
            </div>
            <div class="sv-glass-card" style="padding:2.5rem; text-align:center;">
                <div style="font-size:3.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:#C9A84C; line-height:1;" data-counter="1420" data-suffix="+">0</div>
                <div style="font-size:0.875rem; font-weight:600; color:var(--sv-text); margin-top:1rem;">Certifications délivrées</div>
                <div style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.35rem; font-weight:300;">avec code de vérification cryptographique infalsifiable pour les universités.</div>
            </div>
            <div class="sv-glass-card" style="padding:2.5rem; text-align:center;">
                <div style="font-size:3.5rem; font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color:var(--sv-text); line-height:1;" data-counter="84" data-suffix="k">0</div>
                <div style="font-size:0.875rem; font-weight:600; color:var(--sv-text); margin-top:1rem;">Questions de QCM résolues</div>
                <div style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.35rem; font-weight:300;">permettant un recalibrage continu de l'évaluation sur l'intégralité du cursus.</div>
            </div>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 10: GETTING STARTED STEPS
     ========================================================================= -->
<section class="sv-section" id="comment" style="background:var(--sv-cream-light);">
    <div class="sv-container sv-comment-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:4rem; align-items:center;">
        <div>
            <div class="sv-eyebrow">Démarrage rapide</div>
            <h2 class="sv-section-title">Trois étapes.<br>C'est parti.</h2>
            <p class="sv-section-sub">Créez votre compte en choisissant votre rôle — apprenant ou enseignant — directement depuis le formulaire en haut de page.</p>
            <button type="button" class="sv-btn sv-btn-primary" style="margin-top:1.5rem;" onclick="openSignup()">Commencer maintenant</button>
        </div>
        <div style="display:flex; flex-direction:column; gap:1.25rem;">
            <?php
            $steps = [
                ['01', 'Créez votre compte', 'Nom, email et mot de passe — moins d\'une minute.'],
                ['02', 'Explorez le catalogue', 'Inscrivez-vous aux cours et accédez aux leçons.'],
                ['03', 'Validez et certifiez', 'Passez les QCM et obtenez votre certificat officiel.'],
            ];
            foreach ($steps as $s): ?>
            <div class="sv-step-card" style="display:flex; gap:1rem; align-items:flex-start;">
                <span class="sv-step-badge" style="font-size:0.625rem; font-weight:700; letter-spacing:0.1em; background:var(--sv-text); color:#fff; padding:0.35rem 0.6rem; flex-shrink:0;"><?= $s[0]; ?></span>
                <div>
                    <div class="sv-step-title" style="font-size:0.875rem; font-weight:600; color:var(--sv-text);"><?= $s[1]; ?></div>
                    <div class="sv-step-desc" style="font-size:0.8125rem; color:var(--sv-text-muted); margin-top:0.2rem; font-weight:300;"><?= $s[2]; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 11: AI PEDAGOGICAL COMPANION SHOWCASE
     ========================================================================= -->
<section class="sv-section" id="assistant-ia-section" style="background:#fff;">
    <div class="sv-container sv-comment-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:4rem; align-items:center;">
        <div>
            <div class="sv-eyebrow">Compagnon d'Étude IA</div>
            <h2 class="sv-section-title">Un tuteur personnel.<br>Disponible 24h/24, 7j/7.</h2>
            <p class="sv-section-sub">Étudiez plus intelligemment. Notre compagnon pédagogique basé sur l'IA est intégré directement dans votre espace de lecture pour lever tous vos doutes instantanément.</p>
            
            <div style="display:flex; flex-direction:column; gap:1.25rem; margin-top:2rem;">
                <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                    <div style="width:24px; height:24px; border-radius:50%; background:rgba(0,75,35,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.75rem; color:#004B23;">✓</div>
                    <div>
                        <strong style="font-size:0.875rem; color:var(--sv-text);">Synthèse de documents de cours</strong>
                        <p style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.15rem; font-weight:300;">Importez vos cours PDF et demandez à l'IA d'extraire les notions clés et les définitions fondamentales.</p>
                    </div>
                </div>
                <div style="display:flex; gap:0.75rem; align-items:flex-start;">
                    <div style="width:24px; height:24px; border-radius:50%; background:rgba(0,75,35,0.08); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.75rem; color:#004B23;">✓</div>
                    <div>
                        <strong style="font-size:0.875rem; color:var(--sv-text);">Génération de quiz d'entraînement</strong>
                        <p style="font-size:0.75rem; color:var(--sv-text-muted); margin-top:0.15rem; font-weight:300;">L'IA conçoit des questions d'entraînement adaptées à vos faiblesses pour ancrer durablement vos connaissances.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Browser Mockup -->
        <div class="sv-preview-browser" id="ia-companion-mockup">
            <div class="sv-preview-header">
                <div class="sv-preview-dot" style="background:#FF5F56;"></div>
                <div class="sv-preview-dot" style="background:#FFBD2E;"></div>
                <div class="sv-preview-dot" style="background:#27C93F;"></div>
                <div class="sv-preview-address">https://app.studyvibe.edu/student/dashboard#assistant-ia</div>
            </div>
            <div style="position:relative; aspect-ratio:16/9; background:#efeae2; overflow:hidden; display:flex; flex-direction:column; padding:1rem; font-family:'Inter', sans-serif;">
                <!-- Chat Window Container -->
                <div style="display:flex; flex-direction:column; gap:0.75rem; flex:1; overflow-y:auto; font-size:0.75rem;">
                    <!-- AI message -->
                    <div style="align-self:flex-start; background:#fff; padding:0.6rem 0.8rem; border-radius:8px 8px 8px 0px; max-width:80%; box-shadow:0 1px 1px rgba(0,0,0,0.08); line-height:1.4;">
                        Bonjour ! Je suis votre compagnon d'étude IA. De quoi souhaitez-vous discuter aujourd'hui ?
                    </div>
                    <!-- Student message -->
                    <div style="align-self:flex-end; background:#d9fdd3; padding:0.6rem 0.8rem; border-radius:8px 8px 0px 8px; max-width:80%; box-shadow:0 1px 1px rgba(0,0,0,0.08); line-height:1.4;">
                        Peux-tu m'expliquer simplement le principe d'une matrice symétrique ?
                    </div>
                    <!-- AI response -->
                    <div style="align-self:flex-start; background:#fff; padding:0.6rem 0.8rem; border-radius:8px 8px 8px 0px; max-width:85%; box-shadow:0 1px 1px rgba(0,0,0,0.08); line-height:1.4;">
                        Une <strong>matrice symétrique</strong> est une matrice carrée égale à sa transposée (A = Aᵀ). Les coefficients sont symétriques par rapport à la diagonale principale.
                        <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                            <span style="font-size:0.625rem; font-weight:600; background:#004B23; color:#fff; padding:0.2rem 0.5rem; border-radius:4px; cursor:pointer;">📝 Lancer un mini-quiz</span>
                            <span style="font-size:0.625rem; font-weight:600; background:rgba(0,0,0,0.05); color:var(--sv-text-muted); padding:0.2rem 0.5rem; border-radius:4px; cursor:pointer;">📖 Résumer la leçon</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="sv-divider"></div>

<!-- =========================================================================
     SECTION 12: SYSTEM FOOTER BLOCK
     ========================================================================= -->
<footer class="sv-footer" role="contentinfo">
    <div class="sv-container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <div class="sv-footer-brand">StudyVibe</div>
            <div style="font-size:0.75rem; margin-top:0.35rem;">© <?= date('Y') ?> StudyVibe Academic LMS · <a href="/privacy.php" style="color:rgba(255,255,255,0.7); text-decoration:underline; font-weight:300;">Politique de Confidentialité</a></div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <span class="sv-badge" style="border-color:rgba(255,255,255,0.2); color:rgba(255,255,255,0.7); background:transparent;">Promoteur</span>
            <span class="sv-badge" style="border-color:rgba(255,255,255,0.2); color:rgba(255,255,255,0.7); background:transparent;">Enseignant</span>
            <span class="sv-badge" style="border-color:rgba(255,255,255,0.2); color:rgba(255,255,255,0.7); background:transparent;">Étudiant</span>
        </div>
    </div>
</footer>

<!-- =========================================================================
     SECTION 13: JAVASCRIPT ANIMATIONS & SCRIPTS
     ========================================================================= -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
    // Register GSAP plugins
    gsap.registerPlugin(ScrollTrigger);

    window.addEventListener('DOMContentLoaded', () => {
        // Set main containers opacity immediately when script executes to avoid FOUC (Flash of Unstyled Content)
        gsap.set([".sv-hero-content", ".sv-auth-card", ".sv-feature-card", ".sv-step-card"], { opacity: 1 });

        // 1. Hero Reveal Animation Timeline
        const tl = gsap.timeline({ defaults: { ease: "power3.out" } });
        tl.from(".sv-navbar", { y: -45, opacity: 0, duration: 1.1 })
          .from(".sv-hero-content .sv-eyebrow", { y: 25, opacity: 0, duration: 0.65 }, "-=0.6")
          .from(".sv-hero-headline", { y: 35, opacity: 0, duration: 0.8 }, "-=0.45")
          .from(".sv-hero-sub", { y: 25, opacity: 0, duration: 0.8 }, "-=0.6")
          .from(".sv-hero-actions", { y: 25, opacity: 0, duration: 0.8 }, "-=0.6")
          .from(".sv-hero-content .sv-badge", { y: 15, opacity: 0, stagger: 0.08, duration: 0.5 }, "-=0.6")
          .from(".sv-auth-card", { x: 45, opacity: 0, duration: 1.1, ease: "power4.out" }, "-=1.1");

        // 2. ScrollTrigger features stagger reveal
        gsap.from(".sv-feature-card", {
            scrollTrigger: {
                trigger: "#fonctionnalites",
                start: "top 85%",
                toggleActions: "play none none none"
            },
            y: 45,
            opacity: 0,
            duration: 0.85,
            stagger: 0.12,
            ease: "power2.out"
        });

        // 3. ScrollTrigger Course Reader Mockup
        gsap.from(".sv-showdown-viewport", {
            scrollTrigger: {
                trigger: "#aperçu-cours",
                start: "top 80%",
                toggleActions: "play none none none"
            },
            y: 50,
            opacity: 0,
            duration: 1,
            ease: "power3.out"
        });

        // Showdown Horizontal Scroll (Yoyo Loop)
        const track = document.querySelector('.sv-showdown-track');
        if (track) {
            const duration = 24; // 24 seconds total cycle
            const scrollTween = gsap.to(track, {
                x: () => -(track.scrollWidth - track.parentElement.offsetWidth),
                ease: "none",
                duration: duration,
                repeat: -1,
                yoyo: true,
                repeatDelay: 2.5
            });

            // Pause on hover so the user can interact or look closely
            track.addEventListener('mouseenter', () => scrollTween.pause());
            track.addEventListener('mouseleave', () => scrollTween.play());
        }

        // ScrollTrigger Presentation Story
        gsap.from("#presentation-story .sv-glass-card", {
            scrollTrigger: {
                trigger: "#presentation-story",
                start: "top 80%",
                toggleActions: "play none none none"
            },
            x: 50,
            opacity: 0,
            stagger: 0.15,
            duration: 0.9,
            ease: "power2.out"
        });

        // 4. ScrollTrigger Stats Panel (Section B)
        gsap.from("#statistiques-insights .sv-glass-card", {
            scrollTrigger: {
                trigger: "#statistiques-insights",
                start: "top 80%",
                toggleActions: "play none none none"
            },
            y: 40,
            opacity: 0,
            stagger: 0.15,
            duration: 0.9,
            ease: "power2.out"
        });

        // 5. ScrollTrigger steps timeline slide-in
        gsap.from(".sv-step-card", {
            scrollTrigger: {
                trigger: "#comment",
                start: "top 85%",
                toggleActions: "play none none none"
            },
            x: 35,
            opacity: 0,
            duration: 0.85,
            stagger: 0.15,
            ease: "power2.out"
        });

        // 6. ScrollTrigger AI Companion Mockup (Section C)
        gsap.from("#assistant-ia-section #ia-companion-mockup", {
            scrollTrigger: {
                trigger: "#assistant-ia-section",
                start: "top 80%",
                toggleActions: "play none none none"
            },
            x: 50,
            opacity: 0,
            duration: 1,
            ease: "power3.out"
        });
    });
</script>
<script>
    // =========================================================================
    // SECTION 14: INTERACTIVE WIDGET CONTROLLERS
    // =========================================================================

    /* Tab switcher between login and signup card panels */
    function switchAuthTab(tab) {
        const isLogin = tab === 'login';
        document.querySelectorAll('.sv-form-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(isLogin ? 'panel-login' : 'panel-signup').classList.add('active');
        document.getElementById('tab-login').classList.toggle('active', isLogin);
        document.getElementById('tab-signup').classList.toggle('active', !isLogin);
        document.getElementById('tab-login').setAttribute('aria-selected', isLogin);
        document.getElementById('tab-signup').setAttribute('aria-selected', !isLogin);
        if (!isLogin) {
            document.getElementById('forgot-panel').classList.remove('open');
            if (typeof goSignupStep === 'function') goSignupStep(1);
        }
    }

    function openSignup() {
        document.getElementById('auth-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
        switchAuthTab('signup');
        setTimeout(() => document.getElementById('signup-role').focus(), 400);
    }

    function openForgotPassword() {
        const panel = document.getElementById('forgot-panel');
        panel.classList.toggle('open');
        if (panel.classList.contains('open')) {
            document.getElementById('forgot-email').focus();
        }
    }

    document.getElementById('forgot-btn')?.addEventListener('click', async () => {
        const btn = document.getElementById('forgot-btn');
        const email = document.getElementById('forgot-email').value.trim();
        if (!email) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('email', email);
        try {
            const data = await svPost('/forgot-password-action.php', fd);
            Toast[data.success ? 'success' : 'error'](data.message);
        } catch { /* svPost handles errors */ }
        btn.disabled = false;
    });

    function switchRole(role) {
        ['promoteur','enseignant','etudiant'].forEach(r => {
            const panel = document.getElementById('role-' + r);
            const tab   = document.querySelector('[data-role="' + r + '"]');
            const show  = r === role;
            panel.style.display = show ? 'grid' : 'none';
            tab.classList.toggle('active', show);
        });
    }

    // Input fields references
    const signupRole  = document.getElementById('signup-role');
    const signupName  = document.getElementById('signup-name');
    const signupEmail = document.getElementById('signup-email');
    const signupPass  = document.getElementById('signup-password');
    const signupBtn   = document.getElementById('signup-btn');
    const signupNext  = document.getElementById('signup-next');
    const signupBack  = document.getElementById('signup-back');
    const signupChangeRole = document.getElementById('signup-change-role');
    const signupStep1 = document.getElementById('signup-step-1');
    const signupStep2 = document.getElementById('signup-step-2');
    const signupRoleChip = document.getElementById('signup-role-chip');
    const signupRoleChipLabel = document.getElementById('signup-role-chip-label');
    const pwBar       = document.getElementById('pw-bar');
    const hintRole    = document.getElementById('hint-role');

    const roleMeta = {
        student: { label: 'Apprenant', btn: 'Créer mon compte apprenant' },
        teacher: { label: 'Enseignant', btn: 'Créer mon compte enseignant' },
    };

    let signupCurrentStep = 1;

    function goSignupStep(step) {
        signupCurrentStep = step;
        signupStep1.hidden = step !== 1;
        signupStep2.hidden = step !== 2;
        signupStep1.classList.toggle('active', step === 1);
        signupStep2.classList.toggle('active', step === 2);
        document.querySelectorAll('.sv-signup-stepper-item').forEach(el => {
            const n = Number(el.dataset.step);
            el.classList.toggle('active', n === step);
            el.classList.toggle('done', n < step);
        });
        if (step === 2) {
            const meta = roleMeta[signupRole.value];
            signupRoleChipLabel.textContent = meta ? `Inscription en tant qu'${meta.label}` : '';
            signupRoleChip.dataset.role = signupRole.value;
            signupBtn.textContent = meta ? meta.btn : 'Créer mon compte';
            setTimeout(() => signupName.focus(), 200);
        } else {
            setTimeout(() => signupRole.focus(), 200);
        }
        validateSignup();
    }

    // Dynamic Form validation rules for signup inputs
    function validateSignup() {
        const roleOk  = signupRole.value === 'student' || signupRole.value === 'teacher';
        const nameOk  = signupName.value.trim().length >= 2;
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(signupEmail.value.trim());
        const passLen = signupPass.value.length;
        const passOk  = passLen >= 6;

        signupRole.classList.toggle('valid', roleOk);
        signupRole.classList.toggle('invalid', !roleOk && signupCurrentStep === 2);
        hintRole.classList.toggle('error', !roleOk && signupCurrentStep === 2);

        signupName.classList.toggle('valid', nameOk);
        signupName.classList.toggle('invalid', signupName.value && !nameOk);
        signupEmail.classList.toggle('valid', emailOk);
        signupEmail.classList.toggle('invalid', signupEmail.value && !emailOk);

        // Check password complexity strength bar
        const strength = Math.min(100, passLen * 12 + (/\d/.test(signupPass.value) ? 20 : 0) + (/[A-Z]/.test(signupPass.value) ? 15 : 0));
        pwBar.style.width = passLen ? strength + '%' : '0';
        pwBar.style.background = strength < 40 ? '#D32F2F' : strength < 70 ? '#E6A817' : '#004B23';

        signupNext.disabled = !roleOk;
        signupBtn.disabled = !(roleOk && nameOk && emailOk && passOk);
        return roleOk && nameOk && emailOk && passOk;
    }

    signupNext.addEventListener('click', () => {
        if (signupRole.value !== 'student' && signupRole.value !== 'teacher') {
            hintRole.classList.add('error');
            signupRole.classList.add('invalid');
            signupRole.focus();
            return;
        }
        goSignupStep(2);
    });
    signupBack.addEventListener('click', () => goSignupStep(1));
    signupChangeRole.addEventListener('click', () => goSignupStep(1));

    [signupName, signupEmail, signupPass].forEach(el => el.addEventListener('input', validateSignup));
    signupRole.addEventListener('change', validateSignup);

    /* Submit Login Action Handler */
    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('login-btn');
        btn.disabled = true; btn.textContent = 'Connexion…';
        const fd = new FormData();
        fd.append('email',    document.getElementById('login-email').value.trim());
        fd.append('password', document.getElementById('login-password').value);
        
        const urlParams = new URLSearchParams(window.location.search);
        const redirectVal = urlParams.get('redirect');
        if (redirectVal) {
            fd.append('redirect', redirectVal);
        }

        try {
            const data = await svPost('/login-action.php', fd);
            if (data.success) {
                Toast.success('Connexion réussie — redirection…');
                setTimeout(() => { window.location.href = data.redirect; }, 600);
            } else {
                Toast.error(data.message);
                btn.disabled = false; btn.textContent = 'Se connecter';
            }
        } catch { btn.disabled = false; btn.textContent = 'Se connecter'; }
    });

    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'auth_required') {
            if (typeof Toast !== 'undefined') {
                Toast.error("Authentification requise pour accéder à cette évaluation.");
            }
        }
    });

    /* Submit Signup Action Handler */
    document.getElementById('signup-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateSignup()) return;
        const btn = document.getElementById('signup-btn');
        btn.disabled = true; btn.textContent = 'Création…';
        const fd = new FormData();
        fd.append('role',     signupRole.value);
        fd.append('name',     signupName.value.trim());
        fd.append('email',    signupEmail.value.trim());
        fd.append('password', signupPass.value);
        if (document.getElementById('signup-newsletter').checked) {
            fd.append('newsletter', '1');
        }
        try {
            const data = await svPost('/signup-action.php', fd);
            if (data.success) {
                const mailNote = data.mail_sent === false
                    ? ' Vérifiez votre boîte mail (ou SMTP).'
                    : '';
                Toast.success('Compte créé — vérifiez votre email pour activer le compte.' + mailNote);
                setTimeout(() => { window.location.href = data.redirect; }, 800);
            } else {
                Toast.error(data.message);
                btn.disabled = false;
                validateSignup();
            }
        } catch { btn.disabled = false; validateSignup(); }
    });

    /* Adjust roles responsive columns grid */
    const mq = window.matchMedia('(max-width:768px)');
    function fixRoleGrid() {
        document.querySelectorAll('.role-panel').forEach(p => {
            if (p.style.display === 'grid') p.style.gridTemplateColumns = mq.matches ? '1fr' : '1fr 1fr';
        });
    }
    mq.addEventListener('change', fixRoleGrid);
    fixRoleGrid();
</script>
</body>
</html>
