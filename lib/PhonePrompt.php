<?php
declare(strict_types=1);

require_once __DIR__ . '/Phone.php';

/**
 * The compulsory "add and verify your phone number" window, shown on every dashboard until the account has a verified number.
 *
 *   <?php $needsPhone = PhonePrompt::render($user, $lang, $blockedByAnotherForm); ?>   just before </body>
 *
 * It cannot be dismissed (the only other action is signing out). It talks to /api/phone.php; the work is in assets/js/phone-verify.js.
 * Returns true when it was shown, so a dashboard can hold back other pop-ups (the guided tour) until the number is verified.
 */
final class PhonePrompt
{
    public static function needed(?array $user): bool
    {
        return $user !== null && empty($user['phone_e164']);
    }

    public static function render(?array $user, string $lang, bool $blockedByAnotherForm = false): bool
    {
        if (!self::needed($user) || $blockedByAnotherForm) {
            return false;
        }
        $lang = $lang === 'en' ? 'en' : 'fr';
        $t = self::strings()[$lang];
        $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $cfg = json_encode(['t' => $t, 'prefill' => (string)($user['phone_pending'] ?? ''), 'cc' => Phone::defaultCountry()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        ?>
<link rel="stylesheet" href="/assets/css/phone-verify.css">
<div id="pv-overlay" class="pv-overlay" role="dialog" aria-modal="true" aria-labelledby="pv-title" aria-describedby="pv-lede">
  <div class="pv-card">
    <p class="pv-kicker"><?= $h($t['kicker']) ?></p>
    <h2 id="pv-title" class="pv-title"><?= $h($t['title']) ?></h2>
    <p id="pv-lede" class="pv-lede"><?= $h($t['lede']) ?></p>

    <form id="pv-step-phone" class="pv-step" novalidate>
      <label for="pv-phone" class="pv-label"><?= $h($t['phone_label']) ?></label>
      <input id="pv-phone" class="pv-input" type="tel" inputmode="tel" autocomplete="tel" placeholder="<?= $h($t['phone_ph']) ?>" maxlength="30" required>
      <p class="pv-hint"><?= $h($t['phone_hint']) ?></p>
      <p id="pv-error-phone" class="pv-error" role="alert" hidden></p>
      <button id="pv-send" class="pv-btn" type="submit"><?= $h($t['send']) ?></button>
    </form>

    <form id="pv-step-code" class="pv-step" hidden novalidate>
      <p class="pv-sent" id="pv-sent"></p>
      <label for="pv-code" class="pv-label"><?= $h($t['code_label']) ?></label>
      <input id="pv-code" class="pv-input pv-code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="••••••" required>
      <p id="pv-dev" class="pv-dev" hidden></p>
      <p id="pv-error-code" class="pv-error" role="alert" hidden></p>
      <button id="pv-verify" class="pv-btn" type="submit"><?= $h($t['verify']) ?></button>
      <div class="pv-links">
        <button id="pv-resend" class="pv-link" type="button" disabled><?= $h($t['resend']) ?></button>
        <button id="pv-change" class="pv-link" type="button"><?= $h($t['change']) ?></button>
      </div>
    </form>

    <div id="pv-done" class="pv-step" hidden>
      <p class="pv-ok" role="status"><?= $h($t['done']) ?></p>
    </div>

    <p class="pv-foot"><a href="/logout.php"><?= $h($t['logout']) ?></a></p>
  </div>
</div>
<script>window.PV_CFG = <?= $cfg ?>;</script>
<script src="/assets/js/phone-verify.js" defer></script>
        <?php
        return true;
    }

    /** @return array{fr:array<string,string>,en:array<string,string>} */
    public static function strings(): array
    {
        return [
            'fr' => [
                'kicker' => 'Une dernière étape', 'title' => 'Ajoutez votre numéro de téléphone',
                'lede' => 'Nous l’utilisons pour vous prévenir quand une évaluation en direct est programmée, et pour sécuriser votre compte. Nous vous envoyons un code à 6 chiffres pour vérifier qu’il est bien à vous.',
                'phone_label' => 'Numéro de téléphone', 'phone_ph' => '6 12 34 56 78', 'phone_hint' => 'Sans indicatif, nous ajoutons +237 (Cameroun). Pour un autre pays, tapez le numéro complet, par exemple +33 6 12 34 56 78.',
                'send' => 'Envoyer le code', 'sending' => 'Envoi…',
                'code_label' => 'Code reçu par SMS', 'verify' => 'Vérifier', 'verifying' => 'Vérification…',
                'sent' => 'Code envoyé au :phone. Il est valable 10 minutes.', 'resend' => 'Renvoyer le code', 'resend_in' => 'Renvoyer dans :s s', 'change' => 'Changer de numéro',
                'done' => 'Numéro vérifié. Merci !', 'logout' => 'Se déconnecter',
                'err_network' => 'Connexion impossible. Vérifiez votre réseau et réessayez.', 'err_empty' => 'Saisissez votre numéro.', 'err_code' => 'Saisissez les 6 chiffres du code.',
                'dev' => 'Mode développeur : le code est :code',
            ],
            'en' => [
                'kicker' => 'One last step', 'title' => 'Add your phone number',
                'lede' => 'We use it to let you know when a live evaluation is scheduled, and to protect your account. We send you a 6-digit code to check that the number is yours.',
                'phone_label' => 'Phone number', 'phone_ph' => '6 12 34 56 78', 'phone_hint' => 'Without a country code we add +237 (Cameroon). For another country, type the full number, for example +33 6 12 34 56 78.',
                'send' => 'Send the code', 'sending' => 'Sending…',
                'code_label' => 'Code received by SMS', 'verify' => 'Verify', 'verifying' => 'Checking…',
                'sent' => 'Code sent to :phone. It is valid for 10 minutes.', 'resend' => 'Send the code again', 'resend_in' => 'Send again in :s s', 'change' => 'Use another number',
                'done' => 'Number verified. Thank you!', 'logout' => 'Sign out',
                'err_network' => 'Could not connect. Check your network and try again.', 'err_empty' => 'Enter your number.', 'err_code' => 'Enter the 6 digits of the code.',
                'dev' => 'Developer mode: the code is :code',
            ],
        ];
    }
}
