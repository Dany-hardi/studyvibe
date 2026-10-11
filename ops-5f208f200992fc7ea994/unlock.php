<?php
declare(strict_types=1);

/**
 * The second lock of the control center. Only an owner (see ControlGate::isOwner) gets this page; anybody else gets the usual 404.
 * No password: two proofs given together, a code e-mailed to the account and a code from its authenticator app (lib/ControlUnlock.php).
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../Mailer.php';
require_once __DIR__ . '/../lib/Brand.php';
require_once __DIR__ . '/../lib/ControlGate.php';
require_once __DIR__ . '/../lib/ControlUnlock.php';

$user = ControlGate::enter(false, false);
$pdo = Database::getInstance();
$ip = getClientIp();
$h = fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$uid = (int)$user['id'];

$MSG = [
    'invalid'   => 'Verification failed. Check both codes and try again.',
    'too_many'  => 'Too many attempts. Wait a few minutes before trying again.',
    'too_soon'  => 'A code was just sent. Wait a minute before asking for another.',
    'blocked'   => 'This account cannot open the control center yet. See the checklist below.',
    'mail_failed' => 'The e-mail could not be sent. Try again later.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ControlGate::checkToken()) {
        http_response_code(403);
        exit('Expired page. Reload it.');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'lock') {
        ControlUnlock::lock($user);
        $_SESSION['ops_flash'] = ['ok', 'The control center is locked.'];
    } elseif ($action === 'send') {
        $r = ControlUnlock::sendCode($pdo, $user, $ip);
        $_SESSION['ops_flash'] = $r['ok']
            ? ['ok', 'A code was sent to ' . $r['email_masked'] . '. It is valid ' . ControlUnlock::CODE_MINUTES . ' minutes.']
            : ['bad', $MSG[$r['error']] ?? $MSG['invalid']];
        if (!empty($r['dev_code'])) {
            $_SESSION['ops_flash'][1] .= ' (development, no mail server) code: ' . $r['dev_code'];
        }
        $_SESSION['ops_code_sent'] = $r['ok'] ? time() : null;
    } elseif ($action === 'unlock') {
        $r = ControlUnlock::unlock($pdo, $user, (string)($_POST['email_code'] ?? ''), (string)($_POST['totp'] ?? ''), $ip);
        if ($r['ok']) {
            unset($_SESSION['ops_code_sent']);
            header('Location: index.php');
            exit;
        }
        $_SESSION['ops_flash'] = ['bad', $MSG[$r['error']] ?? $MSG['invalid']];
    }
    header('Location: unlock.php');
    exit;
}

$flash = $_SESSION['ops_flash'] ?? null;
unset($_SESSION['ops_flash']);
$problems = ControlUnlock::problems($pdo, $user);
$open = ControlUnlock::isUnlocked($uid);
$codeSent = !empty($_SESSION['ops_code_sent']) && time() - (int)$_SESSION['ops_code_sent'] < ControlUnlock::CODE_MINUTES * 60;
$token = ControlGate::token();
$v = (string)@filemtime(__DIR__ . '/ops.css');
$CHECK = [
    'weak_secret' => ['APP_SECRET is the placeholder or too short', 'Set a long random APP_SECRET in .env (32 characters or more).'],
    'no_email' => ['No verified e-mail address', 'Verify the e-mail address of this account first.'],
    'no_2fa' => ['No authenticator app on this account', 'Turn on two-factor authentication in your security settings, then come back.'],
];
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Control center · verification</title>
<?= Brand::headLinks() ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="ops.css?v=<?= $v ?>">
<style>
a.btn { text-decoration: none; }
.ul-wrap { min-height: calc(100vh - 56px); display: grid; place-items: center; padding: 28px 16px 48px; }
.ul-card { width: min(520px, 100%); }
.ul-ico { width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; background: color-mix(in srgb, var(--clay) 16%, transparent); color: var(--clay); margin-bottom: 14px; }
.ul-card h1 { font-family: var(--display); font-weight: 500; font-size: 1.9rem; margin: 0 0 .35rem; }
.ul-card .lede { color: var(--dim); margin: 0 0 1.2rem; }
.ul-flash { padding: .7rem .9rem; border-radius: 10px; margin-bottom: 1rem; font-size: .92rem; border: 1px solid var(--line); }
.ul-flash.ok { border-color: color-mix(in srgb, var(--pine) 55%, transparent); color: var(--pine); }
.ul-flash.bad { border-color: color-mix(in srgb, #e58383 60%, transparent); color: #e58383; }
.ul-step { display: flex; gap: .8rem; align-items: flex-start; margin: 1.1rem 0 .4rem; }
.ul-n { flex: none; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: var(--clay); color: #1a0f09; font-weight: 700; font-size: .85rem; display: grid; place-items: center; }
.ul-step b { display: block; } .ul-step span { color: var(--dim); font-size: .88rem; }
.ul-code { letter-spacing: .35em; font-family: var(--mono); font-size: 1.15rem; text-align: center; }
.ul-list { margin: 1rem 0 0; padding: 0; list-style: none; display: grid; gap: .5rem; font-size: .9rem; }
.ul-list li { display: flex; gap: .6rem; color: var(--dim); }
.ul-list li::before { content: "✓"; color: var(--pine); font-weight: 700; }
.ul-todo { margin: 0 0 .5rem; padding: .7rem .9rem; border: 1px dashed var(--line); border-radius: 10px; }
.ul-todo b { color: #e5b24d; } .ul-todo span { display: block; color: var(--dim); font-size: .88rem; }
.ul-row { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; }
</style>
</head>
<body class="ops">
<header class="ops-top">
  <div class="ops-brand"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6z"/></svg><b>StudyVibe</b><span class="ops-pill">Control center</span></div>
  <span></span>
  <div class="ops-who"><span><?= $h($user['name']) ?></span><a class="btn ghost" href="/promoter/dashboard.php">Back to the dashboard</a></div>
</header>

<main class="ul-wrap">
<section class="panel ul-card" aria-labelledby="ul-h">
  <div class="ul-ico"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 018 0v2.5"/><circle cx="12" cy="15.5" r="1.2"/></svg></div>
  <h1 id="ul-h">Verify it is you</h1>
  <p class="lede">This door has no password. It opens with two proofs given together: your authenticator app and your mailbox.</p>

  <?php if ($flash): ?><div class="ul-flash <?= $h($flash[0]) ?>" role="status"><?= $h($flash[1]) ?></div><?php endif; ?>

  <?php if ($open): ?>
    <p>The control center is open (<?= (int)ceil(ControlUnlock::secondsLeft() / 60) ?> min left).</p>
    <div class="ul-row">
      <a class="btn primary" href="index.php">Enter</a>
      <form method="post" style="display:inline"><input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="action" value="lock"><button class="btn" type="submit">Lock now</button></form>
    </div>

  <?php elseif ($problems): ?>
    <?php foreach ($problems as $p): ?><div class="ul-todo"><b><?= $h($CHECK[$p][0]) ?></b><span><?= $h($CHECK[$p][1]) ?></span></div><?php endforeach; ?>
    <?php if (in_array('no_2fa', $problems, true)): ?><div class="ul-row"><a class="btn primary" href="/account/security.php?setup=2fa">Turn on two-factor authentication</a></div><?php endif; ?>

  <?php else: ?>
    <div class="ul-step"><span class="ul-n">1</span><div><b>Get your e-mail code</b><span>Sent to the verified address of this account.</span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="action" value="send">
      <button class="btn<?= $codeSent ? '' : ' primary' ?>" type="submit"><?= $codeSent ? 'Send a new code' : 'Send me a code' ?></button>
    </form>

    <div class="ul-step"><span class="ul-n">2</span><div><b>Enter both codes</b><span>The 8 digits from the e-mail and the 6 digits shown by your authenticator app.</span></div></div>
    <form method="post" autocomplete="off">
      <input type="hidden" name="token" value="<?= $h($token) ?>"><input type="hidden" name="action" value="unlock">
      <label class="f"><span>E-mail code (8 digits)</span><input class="num ul-code" name="email_code" inputmode="numeric" pattern="[0-9 ]{8,9}" maxlength="9" autocomplete="one-time-code" required placeholder="0000 0000"></label>
      <label class="f"><span>Authenticator code (6 digits)</span><input class="num ul-code" name="totp" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="off" required placeholder="000 000"></label>
      <button class="btn primary big" type="submit">Open the control center</button>
    </form>

    <ul class="ul-list">
      <li>Recovery codes do not work here: it has to be the app on your device.</li>
      <li>Open for <?= (int)(ControlUnlock::IDLE_SECONDS / 60) ?> minutes without activity, <?= (int)(ControlUnlock::MAX_SECONDS / 3600) ?> hours at most, tied to this browser and network.</li>
      <li>Every attempt is written to the audit log, and you are told by e-mail each time it opens.</li>
    </ul>
  <?php endif; ?>
</section>
</main>
</body>
</html>
