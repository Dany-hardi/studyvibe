<?php
declare(strict_types=1);

// Si inclus directement ou appelé via GET
$errorCode = $errorCode ?? (int)($_GET['code'] ?? 404);
$errorTitle = $errorTitle ?? (string)($_GET['title'] ?? 'Page introuvable');
$errorMessage = $errorMessage ?? (string)($_GET['message'] ?? 'La ressource que vous recherchez n\'existe pas ou a été déplacée.');
$badgeText = $badgeText ?? (string)($_GET['badge'] ?? 'Erreur');
$backUrl = $backUrl ?? (string)($_GET['back'] ?? '');

if (empty($backUrl)) {
    $backUrl = 'javascript:history.back()';
}

// Définir le code réponse HTTP approprié
if ($errorCode >= 100 && $errorCode < 600) {
    http_response_code($errorCode);
} else {
    http_response_code(404);
}

// Lignes machine à écrire par défaut selon l'erreur
if (!isset($typewriterLines) || empty($typewriterLines)) {
    if ($errorCode === 404) {
        $typewriterLines = [
            '> ERREUR : chemin non résolu',
            '> Recherche dans la base de données… Introuvable.',
            '> Diagnostic : Le code saisi ou l\'URL est incorrect.',
            '> Solution : Vérifiez le lien ou retournez à l\'accueil.',
        ];
    } elseif ($errorCode === 403) {
        $typewriterLines = [
            '> ACCÈS CONTRÔLÉ : Autorisation requise',
            '> Rôle utilisateur insuffisant ou session expirée.',
            '> Protocole : Redirection ou reconnexion requise.',
        ];
    } else {
        $typewriterLines = [
            '> ALERTE SYSTÈME : Une anomalie est survenue',
            '> Enregistrement dans les journaux d\'audit.',
            '> Statut : En cours de résolution par les administrateurs.',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($errorTitle) ?> — StudyVibe</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --cream:   #EAE6DF;
            --green:   #004B23;
            --green2:  #00873F;
            --ink:     #1A1A1A;
            --muted:   #5C5C5C;
            --faint:   #9A9A9A;
            --gold:    #C9A84C;
            --surface: #FFFFFF;
        }

        html, body {
            height: 100%;
            background: var(--cream);
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            overflow: hidden;
        }

        /* ── Grain texture overlay ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* ── Layout ── */
        .page {
            position: relative;
            z-index: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        /* ── Floating books background ── */
        .floating-books {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .book {
            position: absolute;
            opacity: 0;
            animation: floatBook linear infinite;
        }

        @keyframes floatBook {
            0%   { opacity: 0; transform: translateY(0) rotate(var(--r)) scale(0.6); }
            10%  { opacity: 0.18; }
            90%  { opacity: 0.12; }
            100% { opacity: 0; transform: translateY(-110vh) rotate(calc(var(--r) + 40deg)) scale(0.9); }
        }

        /* ── Card ── */
        .card {
            background: var(--surface);
            border: 1px solid rgba(0,75,35,0.15);
            max-width: 1080px;
            width: 100%;
            height: min(640px, 90vh);
            display: flex;
            position: relative;
            box-shadow:
                0 30px 70px rgba(0,0,0,0.08),
                0 10px 30px rgba(0,0,0,0.04);
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--green) 0%, var(--green2) 50%, var(--gold) 100%);
            z-index: 10;
        }

        /* ── Left column (Image) ── */
        .col-image {
            flex: 1.1;
            position: relative;
            background: #0f1c14;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .col-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.9;
        }

        .illustration-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1.5rem;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 60%, transparent 100%);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-family: 'Inter', sans-serif;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            z-index: 2;
        }

        /* ── Right column (Content) ── */
        .col-content {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-y: auto;
            position: relative;
            background: var(--surface);
        }

        /* ── Brand ── */
        .brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-bottom: 1.5rem;
        }

        .brand-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--ink);
        }

        /* ── Status badge ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--green);
            border: 1px solid var(--green);
            padding: 0.3rem 0.75rem;
            align-self: flex-start;
            margin-bottom: 1rem;
        }

        /* ── The big error code absolute background ── */
        .error-code-bg {
            position: absolute;
            right: 2rem;
            top: 1rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 8rem;
            font-weight: 600;
            color: var(--green);
            opacity: 0.05;
            user-select: none;
            pointer-events: none;
            line-height: 1;
        }

        /* ── Story text ── */
        .story-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.6rem, 2.5vw, 2.2rem);
            font-weight: 400;
            line-height: 1.15;
            color: var(--ink);
            margin-bottom: 0.75rem;
        }

        .story-title em {
            font-style: italic;
            color: var(--green);
        }

        .story-body {
            font-size: 0.85rem;
            font-weight: 300;
            line-height: 1.7;
            color: var(--muted);
            margin-bottom: 1.5rem;
            max-width: 44ch;
        }

        /* ── Typewriter container ── */
        #typewriter-line {
            font-family: monospace;
            font-size: 0.72rem;
            color: var(--green);
            background: rgba(0,75,35,0.05);
            border: 1px solid rgba(0,75,35,0.12);
            padding: 0.6rem 0.8rem;
            margin-bottom: 1.5rem;
            display: block;
            border-radius: 1px;
            width: 100%;
        }

        /* ── Animated cursor ── */
        .cursor {
            display: inline-block;
            width: 2px;
            height: 1em;
            background: var(--green);
            margin-left: 2px;
            vertical-align: text-bottom;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

        /* ── Actions ── */
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--ink);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--ink);
            transition: background 0.15s, border-color 0.15s;
        }
        .btn-primary:hover {
            background: var(--green);
            border-color: var(--green);
        }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: var(--ink);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border: 1px solid rgba(0,0,0,0.15);
            transition: border-color 0.15s, color 0.15s;
        }
        .btn-outline:hover {
            border-color: var(--green);
            color: var(--green);
        }

        /* ── Hint ── */
        .hint {
            font-size: 0.68rem;
            color: var(--faint);
            font-weight: 300;
            letter-spacing: 0.02em;
        }

        .hint strong {
            font-weight: 500;
            color: var(--muted);
            font-family: monospace;
        }

        /* ── Footer ── */
        .page-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.06);
            font-size: 0.65rem;
            color: var(--faint);
            letter-spacing: 0.04em;
        }

        /* Responsive Layout Switch */
        @media (max-width: 880px) {
            html, body {
                overflow-y: auto;
                height: auto;
            }
            .page {
                height: auto;
                padding: 1rem;
            }
            .card {
                flex-direction: column;
                height: auto;
                max-width: 520px;
            }
            .col-image {
                height: 260px;
            }
            .col-content {
                padding: 2rem 1.5rem;
            }
            .error-code-bg {
                font-size: 5rem;
                top: 2rem;
                right: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- Floating book particles -->
    <div class="floating-books" id="floating-books" aria-hidden="true"></div>

    <main class="page">
        <div class="card">
            
            <!-- Left column (Immersive Image) -->
            <div class="col-image">
                <img src="/assets/img/404-illustration.png"
                     alt="Un étudiant perdu dans une bibliothèque infinie">
                <div class="illustration-caption">Bibliothèque de StudyVibe…</div>
            </div>

            <!-- Right column (Content details) -->
            <div class="col-content">
                <!-- Absolute error watermark -->
                <div class="error-code-bg"><?= htmlspecialchars((string)$errorCode) ?></div>

                <div>
                    <!-- Brand header -->
                    <a href="/" class="brand">
                        <svg width="22" height="22" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#004B23"/>
                                    <stop offset="100%" stop-color="#00873F"/>
                                </linearGradient>
                            </defs>
                            <path d="M32 52 L8 46 L8 16 L32 22 Z" fill="url(#g1)"/>
                            <path d="M32 52 L56 46 L56 16 L32 22 Z" fill="#003318"/>
                            <path d="M32 22 L10 17 L10 44 L32 49 Z" fill="#EAE6DF"/>
                            <path d="M32 22 L54 17 L54 44 L32 49 Z" fill="#F5F3EF"/>
                            <line x1="32" y1="22" x2="32" y2="52" stroke="#004B23" stroke-width="1.5"/>
                            <path d="M32 10 L33.2 13.8 L37 15 L33.2 16.2 L32 20 L30.8 16.2 L27 15 L30.8 13.8 Z" fill="#C9A84C"/>
                        </svg>
                        <span class="brand-name">StudyVibe</span>
                    </a>

                    <!-- Status badge -->
                    <span class="badge">
                        <svg width="6" height="6" viewBox="0 0 8 8" fill="none" style="margin-right: 2px;">
                            <circle cx="4" cy="4" r="3" fill="#004B23"/>
                        </svg>
                        <?= htmlspecialchars($badgeText) ?>
                    </span>

                    <!-- Title & description -->
                    <h1 class="story-title">
                        <?= htmlspecialchars($errorTitle) ?>
                    </h1>
                    <p class="story-body">
                        <?= htmlspecialchars($errorMessage) ?>
                    </p>
                </div>

                <div>
                    <!-- Typewriter hint -->
                    <div id="typewriter-line">
                        <span id="tw-text"></span><span class="cursor"></span>
                    </div>

                    <!-- Actions -->
                    <div class="actions">
                        <a href="/" class="btn-primary">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Accueil
                        </a>
                        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-outline">
                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Revenir
                        </a>
                    </div>

                    <div class="page-footer">
                        © <?= date('Y') ?> StudyVibe — Académique
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
    // ── Floating book particles ──────────────────────────────
    const booksContainer = document.getElementById('floating-books');
    const bookSVGs = [
        `<svg width="28" height="36" viewBox="0 0 28 36" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="2" width="22" height="32" rx="1" fill="#004B23" opacity="0.7"/><rect x="4" y="2" width="2" height="32" fill="#003318" opacity="0.5"/><rect x="7" y="8" width="12" height="1.5" rx="0.5" fill="#EAE6DF" opacity="0.5"/><rect x="7" y="12" width="10" height="1.5" rx="0.5" fill="#EAE6DF" opacity="0.4"/><rect x="7" y="16" width="11" height="1.5" rx="0.5" fill="#EAE6DF" opacity="0.3"/></svg>`,
        `<svg width="40" height="28" viewBox="0 0 40 28" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 24 L2 20 L2 4 L20 8 Z" fill="#00873F" opacity="0.6"/><path d="M20 24 L38 20 L38 4 L20 8 Z" fill="#004B23" opacity="0.6"/><path d="M20 8 L3 4 L3 20 L20 24 Z" fill="#EAE6DF" opacity="0.7"/><path d="M20 8 L37 4 L37 20 L20 24 Z" fill="#F5F3EF" opacity="0.7"/><line x1="20" y1="8" x2="20" y2="24" stroke="#004B23" stroke-width="1" opacity="0.5"/></svg>`,
        `<svg width="20" height="40" viewBox="0 0 20 40" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="1" width="16" height="38" rx="1" fill="#003318" opacity="0.65"/><rect x="2" y="1" width="3" height="38" fill="#002210" opacity="0.5"/><path d="M7 12 L14 12" stroke="#C9A84C" stroke-width="1.5" opacity="0.6"/><path d="M7 16 L13 16" stroke="#EAE6DF" stroke-width="1" opacity="0.4"/><path d="M7 20 L14 20" stroke="#EAE6DF" stroke-width="1" opacity="0.3"/></svg>`,
        `<svg width="24" height="30" viewBox="0 0 24 30" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 2 L18 2 L22 6 L22 28 L2 28 Z" fill="#F5F3EF" stroke="#D5D0C8" stroke-width="0.8" opacity="0.8"/><path d="M18 2 L18 6 L22 6" fill="none" stroke="#D5D0C8" stroke-width="0.8"/><line x1="5" y1="11" x2="19" y2="11" stroke="#9A9A9A" stroke-width="0.8" opacity="0.5"/><line x1="5" y1="15" x2="16" y2="15" stroke="#9A9A9A" stroke-width="0.8" opacity="0.4"/><line x1="5" y1="19" x2="17" y2="19" stroke="#9A9A9A" stroke-width="0.8" opacity="0.3"/></svg>`,
    ];

    const COUNT = 16;
    for (let i = 0; i < COUNT; i++) {
        const el = document.createElement('div');
        el.className = 'book';
        const left = Math.random() * 100;
        const delay = Math.random() * 18;
        const duration = 12 + Math.random() * 14;
        const rotate = (Math.random() - 0.5) * 60;
        const scale = 0.5 + Math.random() * 0.8;
        el.style.cssText = `
            left: ${left}%;
            bottom: -80px;
            --r: ${rotate}deg;
            animation-duration: ${duration}s;
            animation-delay: ${delay}s;
            transform: scale(${scale});
        `;
        el.innerHTML = bookSVGs[Math.floor(Math.random() * bookSVGs.length)];
        booksContainer.appendChild(el);
    }

    // ── Typewriter messages ──────────────────────────────────
    const messages = <?= json_encode($typewriterLines) ?>;

    let msgIdx = 0;
    let charIdx = 0;
    let deleting = false;
    const twEl = document.getElementById('tw-text');
    let delay = 0;

    function typewrite() {
        if (!messages.length) return;
        const current = messages[msgIdx];
        if (!deleting) {
            twEl.textContent = current.slice(0, charIdx + 1);
            charIdx++;
            if (charIdx === current.length) {
                deleting = true;
                delay = 2200;
            } else {
                delay = 38 + Math.random() * 22;
            }
        } else {
            twEl.textContent = current.slice(0, charIdx - 1);
            charIdx--;
            if (charIdx === 0) {
                deleting = false;
                msgIdx = (msgIdx + 1) % messages.length;
                delay = 400;
            } else {
                delay = 18;
            }
        }
        setTimeout(typewrite, delay);
    }
    setTimeout(typewrite, 800);
    </script>
</body>
</html>
