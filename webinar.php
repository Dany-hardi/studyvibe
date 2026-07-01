<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    header("Location: /index.php?error=auth_required");
    exit;
}

$user = getCurrentUser();
$webinarId = (int)($_GET['id'] ?? 0);
$pdo = Database::getInstance();

// Récupérer les détails du webinaire
$stmt = $pdo->prepare("
    SELECT w.*, c.title AS course_title, u.name AS teacher_name, u.email AS teacher_email
    FROM webinars w
    JOIN courses c ON w.course_id = c.id
    LEFT JOIN users u ON w.teacher_id = u.id
    WHERE w.id = :id
");
$stmt->execute(['id' => $webinarId]);
$webinar = $stmt->fetch();

if (!$webinar) {
    die("Webinaire introuvable.");
}

// Vérifier l'inscription pour les étudiants
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = :cid AND student_id = :sid");
    $stmt->execute(['cid' => $webinar['course_id'], 'sid' => $user['id']]);
    if (!$stmt->fetch()) {
        die("Accès refusé. Vous n'êtes pas inscrit à ce cours.");
    }
}

// Initialiser ou mettre à jour la session d'assistance (Join log)
try {
    $stmt = $pdo->prepare("
        INSERT INTO webinar_attendance (webinar_id, student_id, joined_at, last_seen_at, total_minutes_present)
        VALUES (:wid, :sid, NOW(), NOW(), 0)
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ");
    $stmt->execute(['wid' => $webinar['id'], 'sid' => $user['id']]);
} catch (Exception $e) {}

// Générer un ID de salon Jitsi unique
$meetingRoomName = "StudyVibe_" . preg_replace('/[^A-Za-z0-9]/', '', $webinar['meeting_id']);
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webinaire Live : <?= htmlspecialchars($webinar['title']); ?> - StudyVibe</title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body {
            background-color: var(--sv-cream);
            color: var(--sv-text);
            font-family: 'Inter', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .webinar-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            height: calc(100vh - 64px);
            overflow: hidden;
        }
        .video-pane {
            background-color: #0b0c10;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-pane {
            border-left: 1px solid rgba(0,0,0,0.08);
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-tabs {
            display: flex;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            background: rgba(0,0,0,0.01);
        }
        .tab-btn {
            flex: 1;
            padding: 1rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--sv-text-muted);
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            color: #004B23;
            border-bottom-color: #004B23;
            background: #fff;
        }
        .tab-panel {
            display: none;
            flex: 1;
            flex-direction: column;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .tab-panel.active {
            display: flex;
        }
        #jitsi-container {
            width: 100%;
            height: 100%;
        }
        .qa-item {
            padding: 1rem;
            background: rgba(0,0,0,0.015);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            transition: transform 0.2s ease;
        }
        .qa-item:hover {
            transform: translateY(-2px);
        }
        .qa-item.answered {
            border-left: 3px solid #004B23;
            background: rgba(0,75,35,0.02);
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <header class="h-16 border-b border-black/5 bg-white px-6 flex items-center justify-between z-10">
        <div class="flex items-center gap-4">
            <a href="/student/dashboard.php" class="text-xs font-semibold uppercase tracking-wider text-black/60 hover:text-black transition-colors flex items-center gap-1">
                ← Quitter la classe
            </a>
            <div class="h-4 w-px bg-black/10"></div>
            <div>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 uppercase tracking-wide mr-2 animate-pulse">
                    ● En Direct
                </span>
                <span class="font-serif text-lg font-light text-black"><?= htmlspecialchars($webinar['title']); ?></span>
                <span class="text-xs text-black/40 ml-2">(<?= htmlspecialchars($webinar['course_title']); ?>)</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-black/60 font-light">Enseignant : <strong><?= htmlspecialchars($webinar['teacher_name'] ?? 'Non assigné'); ?></strong></span>
            <div class="text-xs bg-[#004B23]/10 text-[#004B23] px-3 py-1 rounded-full font-semibold" id="presence-badge">
                Présence : 0 min
            </div>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="webinar-grid">
        <!-- Video Stream area (Jitsi API) -->
        <div class="video-pane">
            <div id="jitsi-container"></div>
        </div>

        <!-- Collaborative panel -->
        <div class="sidebar-pane">
            <div class="sidebar-tabs">
                <div class="tab-btn active" onclick="switchPane('info')">Infos</div>
                <div class="tab-btn" onclick="switchPane('qa')">Questions Q&A</div>
                <div class="tab-btn" onclick="switchPane('eval')">Quiz Live</div>
            </div>

            <!-- Tab 1: Info -->
            <div class="tab-panel active" id="pane-info">
                <h3 class="font-serif text-xl font-light text-black mb-4">À propos de la leçon</h3>
                <p class="text-sm font-light text-black/70 leading-relaxed mb-6">
                    <?= nl2br(htmlspecialchars($webinar['description'] ?? 'Aucune description disponible pour ce cours.')); ?>
                </p>
                <div class="border-t border-black/5 pt-6 space-y-4">
                    <div>
                        <div class="text-xs text-black/40 uppercase tracking-wide mb-1">Durée estimée</div>
                        <div class="text-sm text-black font-semibold"><?= (int)$webinar['duration']; ?> minutes</div>
                    </div>
                    <div>
                        <div class="text-xs text-black/40 uppercase tracking-wide mb-1">Plateforme de diffusion</div>
                        <div class="text-sm text-black font-light">Intégration Jitsi Meet WebRTC (Chiffrement de bout en bout)</div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Q&A -->
            <div class="tab-panel" id="pane-qa">
                <div class="flex flex-col gap-4 h-full">
                    <!-- Ask Form -->
                    <form id="qa-form" class="space-y-2">
                        <textarea id="qa-input" class="w-full p-3 border border-black/10 rounded-lg text-sm outline-none focus:border-[#004B23] transition-colors" placeholder="Posez une question sur le cours..." rows="2" required></textarea>
                        <button type="submit" class="sv-btn sv-btn-primary w-full text-xs font-semibold py-2 rounded-lg">Envoyer la question</button>
                    </form>

                    <div class="h-px bg-black/5"></div>

                    <!-- Questions list -->
                    <div id="qa-list" class="flex-1 overflow-y-auto pr-1">
                        <div class="text-center text-xs text-black/40 py-8">Chargement des questions...</div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Live Quiz -->
            <div class="tab-panel" id="pane-eval">
                <div class="flex flex-col justify-between h-full text-center">
                    <div class="my-auto space-y-4">
                        <div class="w-16 h-16 bg-[#004B23]/10 text-[#004B23] rounded-full flex items-center justify-center mx-auto">
                            ❓
                        </div>
                        <h4 class="font-serif text-lg font-light text-black">Quiz et Évaluations Live</h4>
                        <p class="text-xs text-black/60 max-w-[280px] mx-auto leading-relaxed">
                            Lorsque votre enseignant déclenchera une question ou un sondage de cours en direct, la question apparaîtra ici en temps réel.
                        </p>
                    </div>
                    <div class="border-t border-black/5 pt-4 text-[10px] text-black/40 uppercase tracking-wider">
                        Synchronisation avec live-session.php
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Jitsi & Real-time scripts -->
    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        const roomName = "<?= $meetingRoomName; ?>";
        const userDisplayName = "<?= htmlspecialchars($user['name']); ?>";
        const userEmail = "<?= htmlspecialchars($user['email']); ?>";
        const webinarId = <?= $webinarId; ?>;

        // Initialize Jitsi Meet Iframe
        const domain = "meet.jit.si";
        const options = {
            roomName: roomName,
            parentNode: document.getElementById('jitsi-container'),
            width: '100%',
            height: '100%',
            userInfo: {
                displayName: userDisplayName,
                email: userEmail
            },
            configOverwrite: {
                startWithAudioMuted: true,
                startWithVideoMuted: true,
                disableThirdPartyRequests: true,
                enableWelcomePage: false,
                prejoinPageEnabled: false,
                readOnlyNameShare: true
            },
            interfaceConfigOverwrite: {
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'closedcaptions', 'desktop', 'embedmeeting', 'fullscreen',
                    'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                    'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                    'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
                    'tileview', 'select-background', 'download', 'help', 'mute-everyone',
                    'mute-video-everyone', 'security'
                ]
            }
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        // Sidebar tabs switcher
        function switchPane(pane) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            
            // Find sender
            let idx = 0;
            if (pane === 'qa') idx = 1;
            if (pane === 'eval') idx = 2;
            
            document.querySelectorAll('.tab-btn')[idx].classList.add('active');
            document.getElementById('pane-' + pane).classList.add('active');
            
            if (pane === 'qa') {
                loadQuestions();
            }
        }

        // Q&A actions
        async function loadQuestions() {
            try {
                const res = await fetch('/webinar-actions.php?action=get_qa&webinar_id=' + webinarId);
                const questions = await res.json();
                const list = document.getElementById('qa-list');
                
                if (questions.length === 0) {
                    list.innerHTML = `<div class="text-center text-xs text-black/40 py-12">Aucune question n'a encore été posée.</div>`;
                    return;
                }
                
                list.innerHTML = questions.map(q => `
                    <div class="qa-item ${q.is_answered ? 'answered' : ''}">
                        <div class="flex justify-between items-start">
                            <span class="text-[10px] text-black/50 font-semibold">${escapeHtml(q.student_name)}</span>
                            <span class="text-[9px] text-black/40">${q.time_ago}</span>
                        </div>
                        <p class="text-xs text-black font-light leading-relaxed">${escapeHtml(q.question_text)}</p>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-black/5">
                            <span class="text-[10px] text-[#004B23] font-semibold">
                                ${q.is_answered ? '✓ Répondu' : 'En attente'}
                            </span>
                            <button onclick="voteQuestion(${q.id})" class="text-[10px] hover:text-[#004B23] transition-colors flex items-center gap-1 font-semibold text-black/60">
                                ▲ ${q.votes} vote${q.votes > 1 ? 's' : ''}
                            </button>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                console.error("QA Fetch failed", err);
            }
        }

        async function voteQuestion(qaId) {
            try {
                await fetch('/webinar-actions.php?action=vote_qa&id=' + qaId, { method: 'POST' });
                loadQuestions();
            } catch (e) {}
        }

        document.getElementById('qa-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = document.getElementById('qa-input').value.trim();
            if (!text) return;
            
            const fd = new FormData();
            fd.append('webinar_id', webinarId);
            fd.append('question_text', text);
            
            try {
                const res = await fetch('/webinar-actions.php?action=add_qa', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('qa-input').value = '';
                    loadQuestions();
                }
            } catch (err) {}
        });

        // Attendance Tracking Heartbeat system
        async function runHeartbeat() {
            try {
                const res = await fetch('/webinar-heartbeat.php?webinar_id=' + webinarId, { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('presence-badge').textContent = 'Présence : ' + data.total_minutes + ' min';
                }
            } catch (e) {}
        }

        // Run heartbeat every 30s
        setInterval(runHeartbeat, 30000);
        runHeartbeat(); // Start immediately

        // Auto reload questions every 10s if tab is open
        setInterval(() => {
            if (document.getElementById('pane-qa').classList.contains('active')) {
                loadQuestions();
            }
        }, 10000);

        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
