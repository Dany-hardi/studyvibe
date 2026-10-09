<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Brand.php';

require_once __DIR__ . '/lib/Analytics.php';
Analytics::captureSource();
Analytics::hit('view:join');

$courseId = isset($_GET['course']) ? (int)$_GET['course'] : 0;

if ($courseId <= 0) {
    http_response_code(404);
    die('Lien invalide.');
}

$pdo = Database::getInstance();
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.description, c.enrollment_key, c.start_date, c.end_date, c.cover_image,
           m.title AS module_title,
           COALESCE(u.name, 'Non assigné') AS teacher_name
    FROM courses c
    JOIN modules m ON m.id = c.module_id
    LEFT JOIN users u ON u.id = c.teacher_id
    WHERE c.id = :id
");
$stmt->execute(['id' => $courseId]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    die('Cours introuvable.');
}

$loggedIn   = isLoggedIn();
$role       = $loggedIn ? ($_SESSION['user_role'] ?? '') : '';
$userId     = $loggedIn ? (int)$_SESSION['user_id'] : 0;
$hasKey     = !empty($course['enrollment_key']);
$joinUrl    = '/join.php?course=' . $courseId;
$isStudent  = ($role === 'student');
$isEnrolled = false;

if ($isStudent) {
    $eStmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id=:sid AND course_id=:cid");
    $eStmt->execute(['sid' => $userId, 'cid' => $courseId]);
    $isEnrolled = (bool)$eStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr" class="sv-cream">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre — <?= htmlspecialchars($course['title']) ?> — StudyVibe</title>
    <meta name="description" content="Rejoignez le cours <?= htmlspecialchars($course['title']) ?> sur StudyVibe.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= Brand::headLinks() ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?= csrfMetaTag(); ?>
    <style>
        .join-container {
            max-width: 540px;
            margin: 4rem auto;
            padding: 0 1.5rem;
            width: 100%;
        }
        .join-card {
            background: var(--sv-surface, #ffffff);
            border: 1px solid var(--sv-border-strong, #E5E5E7);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04);
        }
        .join-card-header {
            position: relative;
            padding: 2.5rem 2rem 2rem;
            color: #ffffff;
            background-size: cover;
            background-position: center;
        }
        .join-card-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.8) 100%);
            z-index: 1;
        }
        .join-header-content {
            position: relative;
            z-index: 2;
        }
        .join-module {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #A5D6A7;
            margin-bottom: 0.5rem;
        }
        .join-title {
            font-family: 'Fraunces', sans-serif;
            font-size: 1.8rem;
            font-weight: 500;
            line-height: 1.3;
            margin-bottom: 0.75rem;
        }
        .join-desc {
            font-size: 0.875rem;
            opacity: 0.85;
            line-height: 1.6;
            margin-bottom: 1.25rem;
            font-weight: 300;
        }
        .join-meta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .join-badge {
            font-size: 0.7rem;
            font-weight: 500;
            padding: 0.3rem 0.75rem;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .join-body {
            padding: 2.5rem 2rem;
        }
        .join-section-title {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #555555;
            margin-bottom: 1.25rem;
            border-left: 3px solid #B5482A;
            padding-left: 0.5rem;
        }
        .pw-bar-wrap {
            height: 3px;
            background: #E5E5E7;
            border-radius: 2px;
            margin-top: 0.35rem;
        }
        .pw-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }
    </style>
</head>
<body class="sv-landing sv-page">

<!-- Navbar -->
<nav class="sv-navbar" role="navigation" aria-label="Navigation principale">
    <a href="/index.php" class="sv-navbar-brand"><?= Brand::logo('md') ?></a>
</nav>

<div class="join-container">
    <div class="join-card">
        <?php 
        $hasCover = !empty($course['cover_image']);
        $coverUrl = $hasCover ? '/download.php?type=cover&file=' . urlencode($course['cover_image']) : '';
        ?>
        <div class="join-card-header" style="<?= $hasCover ? "background-image: url('{$coverUrl}');" : "background-image: linear-gradient(135deg, #B5482A 0%, #B5482A 100%);" ?>">
            <div class="join-header-content">
                <div class="join-module"><?= htmlspecialchars($course['module_title']) ?></div>
                <h1 class="join-title"><?= htmlspecialchars($course['title']) ?></h1>
                <?php if (!empty($course['description'])): ?>
                    <p class="join-desc"><?= htmlspecialchars($course['description']) ?></p>
                <?php endif; ?>
                <div class="join-meta">
                    <span class="join-badge">Enseignant : <?= htmlspecialchars($course['teacher_name']) ?></span>
                    <?php if ($hasKey): ?>
                        <span class="join-badge">Clé d'inscription requise</span>
                    <?php else: ?>
                        <span class="join-badge">Accès libre</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="join-body">
            <?php if ($isEnrolled): ?>
                <div class="p-4 bg-[#E8F5E9] border border-[#A5D6A7] rounded-sm text-[#1B5E20] text-sm flex flex-col gap-2">
                    <span class="font-semibold">✓ Déjà inscrit à ce cours</span>
                    <p>Vous êtes déjà membre de ce cours. Rendez-vous sur votre espace étudiant pour commencer les cours.</p>
                    <a href="/student/dashboard.php" class="sv-btn-submit text-center block mt-2" style="max-width: 220px;">Accéder au tableau de bord</a>
                </div>

            <?php elseif ($isStudent): ?>
                <h2 class="join-section-title">Inscription au cours</h2>
                <?php if ($hasKey): ?>
                    <div class="p-3 bg-[#FFF8E1] border border-[#FFE082] rounded-sm text-[#5D4037] text-xs mb-4">
                        Ce cours est protégé par une clé d'inscription. Veuillez la renseigner ci-dessous.
                    </div>
                <?php endif; ?>
                <form id="enroll-form" novalidate class="space-y-4">
                    <?php if ($hasKey): ?>
                        <div class="sv-field">
                            <label class="sv-field-label" for="enroll-key">Clé d'inscription</label>
                            <input type="text" id="enroll-key" name="enrollment_key" class="sv-field-input font-mono"
                                   placeholder="CODE2026" autocomplete="off" autocapitalize="characters" required>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="sv-btn-submit" id="enroll-btn">
                        <?= $hasKey ? "S'inscrire avec la clé" : "S'inscrire maintenant" ?>
                    </button>
                </form>

            <?php elseif ($loggedIn && !$isStudent): ?>
                <div class="p-4 bg-[#FFF8E1] border border-[#FFE082] rounded-sm text-[#5D4037] text-sm">
                    Ce lien est configuré pour l'inscription des étudiants. Connectez-vous avec un compte étudiant pour rejoindre ce cours.
                </div>

            <?php else: ?>
                <h2 class="join-section-title">Accéder à ce cours</h2>

                <div class="sv-form-tabs mb-4" role="tablist">
                    <button class="sv-form-tab active" id="tab-register" onclick="showTab('register')">Créer un compte</button>
                    <button class="sv-form-tab" id="tab-login" onclick="showTab('login')">Déjà inscrit</button>
                </div>

                <!-- Register panel -->
                <div class="sv-form-panel active" id="panel-register">
                    <!-- Step indicator -->
                    <div id="reg-step-indicator" class="text-xs text-[#888888] mb-3">
                        Étape <span id="reg-step-num">1</span> sur 2
                    </div>

                    <form id="reg-form" novalidate class="space-y-4">
                        <input type="hidden" name="role" value="student">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($joinUrl) ?>">

                        <!-- Step 1: name + email -->
                        <div id="reg-s1" class="space-y-4">
                            <div class="sv-field">
                                <label class="sv-field-label" for="reg-name">Nom complet</label>
                                <input type="text" id="reg-name" name="name" class="sv-field-input" placeholder="Marie Curie" required>
                            </div>
                            <div class="sv-field">
                                <label class="sv-field-label" for="reg-email">Adresse électronique</label>
                                <input type="email" id="reg-email" name="email" class="sv-field-input" placeholder="vous@exemple.com" required>
                            </div>
                            <button type="button" class="sv-btn-submit" id="reg-next-btn" onclick="regNext()">Continuer</button>
                        </div>

                        <!-- Step 2: password -->
                        <div id="reg-s2" class="space-y-4" style="display:none;">
                            <div class="sv-field">
                                <label class="sv-field-label" for="reg-phone">Numéro de téléphone</label>
                                <input type="tel" inputmode="tel" id="reg-phone" name="phone" class="sv-field-input" placeholder="6 12 34 56 78" autocomplete="tel" maxlength="30" required>
                                <p class="text-[11px] text-[#888888] mt-1">Pour les rappels d'examens. Il sera vérifié par SMS après votre connexion.</p>
                            </div>
                            <div class="sv-field">
                                <label class="sv-field-label" for="reg-pass">Mot de passe <span class="text-[#888888] font-normal">(min. 8 car.)</span></label>
                                <input type="password" id="reg-pass" name="password" class="sv-field-input" placeholder="8 caractères minimum" minlength="8" required>
                                <div class="pw-bar-wrap"><div class="pw-bar" id="reg-pw-bar"></div></div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="sv-btn-submit" onclick="regBack()" style="background:#F5F5F7;color:#333;">Retour</button>
                                <button type="submit" class="sv-btn-submit" id="reg-submit-btn" disabled>Créer mon compte</button>
                            </div>
                        </div>
                    </form>
                    <p class="text-[11px] text-[#888888] mt-3 text-center">En créant un compte, vous acceptez les conditions d'utilisation de StudyVibe.</p>
                </div>

                <!-- Login panel -->
                <div class="sv-form-panel" id="panel-login">
                    <form id="login-form-join" novalidate class="space-y-4">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($joinUrl) ?>">
                        <div class="sv-field">
                            <label class="sv-field-label" for="jn-login-email">Adresse électronique</label>
                            <input type="email" id="jn-login-email" name="email" class="sv-field-input" placeholder="vous@exemple.com" autocomplete="email" required>
                        </div>
                        <div class="sv-field">
                            <label class="sv-field-label" for="jn-login-pass">Mot de passe</label>
                            <input type="password" id="jn-login-pass" name="password" class="sv-field-input" placeholder="Votre mot de passe" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="sv-btn-submit" id="jn-login-btn">Se connecter</button>
                    </form>
                </div>

            <?php endif; ?>
        </div>
    </div>
    <p class="text-center mt-6 text-xs"><a href="/index.php" class="text-[#B5482A] hover:underline font-medium">← Retour à l'accueil StudyVibe</a></p>
</div>

<script src="/assets/js/app.js"></script>
<script>
const COURSE_ID = <?= $courseId ?>;

/* ── Tab switch ── */
function showTab(tab) {
    ['register','login'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('active', t === tab);
        document.getElementById('panel-' + t).classList.toggle('active', t === tab);
    });
}

/* ── Register stepper ── */
function regNext() {
    const name  = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    if (name.length < 2 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (typeof Toast !== 'undefined') Toast.error('Veuillez renseigner un nom valide et une adresse email correcte.');
        return;
    }
    document.getElementById('reg-s1').style.display = 'none';
    document.getElementById('reg-s2').style.display = 'block';
    document.getElementById('reg-step-num').textContent = '2';
    document.getElementById('reg-pass').focus();
}

function regBack() {
    document.getElementById('reg-s1').style.display = 'block';
    document.getElementById('reg-s2').style.display = 'none';
    document.getElementById('reg-step-num').textContent = '1';
}

document.getElementById('reg-pass')?.addEventListener('input', function() {
    const len = this.value.length;
    const bar = document.getElementById('reg-pw-bar');
    const strength = Math.min(100, len * 12 + (/\d/.test(this.value) ? 20 : 0) + (/[A-Z]/.test(this.value) ? 15 : 0));
    if (bar) {
        bar.style.width  = len ? strength + '%' : '0';
        bar.style.background = strength < 40 ? '#D32F2F' : strength < 70 ? '#E6A817' : '#B5482A';
    }
    document.getElementById('reg-submit-btn').disabled = len < 8 || !regPhoneOk();
});
function regPhoneOk() {
    const v = (document.getElementById('reg-phone')?.value || '').trim();
    return v.replace(/\D/g, '').length >= 8 && /^[+\d\s().-]+$/.test(v);
}
document.getElementById('reg-phone')?.addEventListener('input', function() {
    document.getElementById('reg-submit-btn').disabled = (document.getElementById('reg-pass')?.value.length || 0) < 8 || !regPhoneOk();
});

document.getElementById('reg-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('reg-submit-btn');
    btn.disabled = true; btn.textContent = 'Création…';

    const fd = new FormData(e.target);
    try {
        const r = await fetch('/signup-action.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            if (typeof Toast !== 'undefined') Toast.success('Compte créé ! Un email de confirmation a été envoyé.');
            btn.textContent = 'Compte créé';
            setTimeout(() => {
                window.location.href = '/verify-email-pending.php';
            }, 2000);
        } else {
            if (typeof Toast !== 'undefined') Toast.error(d.message);
            btn.disabled = false; btn.textContent = 'Créer mon compte';
        }
    } catch {
        if (typeof Toast !== 'undefined') Toast.error('Erreur réseau. Réessayez.');
        btn.disabled = false; btn.textContent = 'Créer mon compte';
    }
});

/* ── Login ── */
document.getElementById('login-form-join')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('jn-login-btn');
    btn.disabled = true; btn.textContent = 'Connexion…';

    const fd = new FormData(e.target);
    try {
        const r = await fetch('/login-action.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success && d.requires_2fa) {
            window.location.href = '/two-factor.php';   // second step: code from the authenticator app
        } else if (d.success) {
            if (typeof Toast !== 'undefined') Toast.success('Connexion réussie !');
            btn.textContent = 'Connexion réussie…';
            window.location.href = d.redirect;
        } else {
            if (typeof Toast !== 'undefined') Toast.error(d.message);
            btn.disabled = false; btn.textContent = 'Se connecter';
        }
    } catch {
        if (typeof Toast !== 'undefined') Toast.error('Erreur réseau. Réessayez.');
        btn.disabled = false; btn.textContent = 'Se connecter';
    }
});

/* ── Enrollment ── */
document.getElementById('enroll-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('enroll-btn');
    btn.disabled = true; btn.textContent = 'Inscription…';

    const fd = new FormData();
    fd.append('course_id', COURSE_ID);
    const keyInput = document.getElementById('enroll-key');
    if (keyInput) fd.append('enrollment_key', keyInput.value.trim());

    try {
        const r = await fetch('/student/enroll.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            if (typeof Toast !== 'undefined') Toast.success('Inscription validée !');
            setTimeout(() => { window.location.href = '/student/dashboard.php'; }, 1000);
        } else {
            if (typeof Toast !== 'undefined') Toast.error(d.message);
            btn.disabled = false; btn.textContent = document.getElementById('enroll-key') ? "S'inscrire avec la clé" : "S'inscrire maintenant";
        }
    } catch {
        if (typeof Toast !== 'undefined') Toast.error('Erreur réseau. Réessayez.');
        btn.disabled = false;
    }
});
</script>
</body>
</html>
