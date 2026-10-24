<?php
declare(strict_types=1);

require_once __DIR__ . '/Brand.php';

/**
 * The frame shared by the public pages: head tags, top bar and footer.
 *
 *   SiteChrome::head(['title' => ..., 'desc' => ..., 'css' => ['/assets/css/careers.css']])
 *   SiteChrome::header($lang, 'careers')      // $active: docs | careers | verify | privacy | ''
 *   SiteChrome::footer($lang)                 // the footer is also used by the landing page and the privacy page
 *   SiteChrome::scripts()                     // language switch, theme, mobile menu
 *
 * Every link of the footer lives here, so adding a page to the site is a one-line change in footer().
 */
final class SiteChrome
{
    /** Where "Contact us" goes. Set CONTACT_EMAIL in .env (or the host's variables) to change it. */
    public static function contactEmail(): string
    {
        return defined('CONTACT_EMAIL') && (string)CONTACT_EMAIL !== '' ? (string)CONTACT_EMAIL : 'studyvibelms@gmail.com';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /** @param array{title:string,desc?:string,css?:string[],og?:string,robots?:string,path?:string,jsonld?:array} $o */
    public static function head(array $o): string
    {
        $h = [self::class, 'h'];
        $out = '<meta charset="UTF-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>' . $h($o['title']) . '</title>' . "\n";
        require_once __DIR__ . '/SocialMeta.php';
        require_once __DIR__ . '/Seo.php';
        if (!empty($o['desc'])) {
            $out .= '<meta name="description" content="' . $h($o['desc']) . '">' . "\n";
        }
        // Open Graph + Twitter card with absolute addresses (crawlers ignore relative ones), then canonical, robots and structured data
        $out .= SocialMeta::tags($o['title'], (string)($o['desc'] ?? ''), $o['path'] ?? '/', $o['og'] ?? 'og-default.png')
              . '<meta name="theme-color" content="#F5F0E6">' . "\n"
              . Seo::head($o['path'] ?? '/', array_filter(['robots' => !empty($o['robots']) ? $o['robots'] : null, 'jsonld' => $o['jsonld'] ?? null]));
        $out .= Brand::headLinks() . "\n"
              . '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
              . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
              . '<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n"
              . '<link rel="stylesheet" href="/assets/css/sv2.css">' . "\n"
              . '<link rel="stylesheet" href="/assets/css/site.css">' . "\n";
        foreach ($o['css'] ?? [] as $css) {
            $out .= '<link rel="stylesheet" href="' . $h($css) . '">' . "\n";
        }
        $out .= "<script>\n  document.documentElement.classList.add('js');\n"
              . "  try { var s = localStorage.getItem('sv_dark'); if (s === '1' || (s === null && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark'); } catch (e) {}\n</script>\n";
        return $out;
    }

    /** Top bar. $active marks the current section in the menu. */
    public static function header(string $lang, string $active = ''): string
    {
        $en = $lang === 'en';
        $h = [self::class, 'h'];
        $links = [
            'docs'    => ['/documentation.php', $en ? 'Documentation' : 'Documentation'],
            'careers' => ['/careers.php', $en ? 'Careers' : 'Carrières'],
            'verify'  => ['/verify.php', $en ? 'Verify a document' : 'Vérifier un document'],
        ];
        $nav = '';
        foreach ($links as $key => [$href, $label]) {
            $nav .= '<a href="' . $href . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . $h($label) . '</a>';
        }
        $theme = $en ? 'Switch theme' : 'Changer de thème';
        $menu = $en ? 'Menu' : 'Menu';
        ob_start(); ?>
<a class="sc-skip" href="#main"><?= $en ? 'Skip to content' : 'Aller au contenu' ?></a>
<header class="sc-top" id="sc-top">
  <div class="sc-wrap sc-top-in">
    <a class="sc-logo" href="/index.php" aria-label="StudyVibe"><?= Brand::logo('md') ?></a>
    <nav class="sc-nav" aria-label="<?= $en ? 'Main' : 'Principal' ?>"><?= $nav ?></nav>
    <div class="sc-tools">
      <div class="seg" role="group" aria-label="Language">
        <a href="#" data-lang="fr" <?= $lang === 'fr' ? 'aria-current="true"' : '' ?>>FR</a>
        <a href="#" data-lang="en" <?= $lang === 'en' ? 'aria-current="true"' : '' ?>>EN</a>
      </div>
      <button class="chip" type="button" data-theme-toggle aria-label="<?= $h($theme) ?>"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 2a6 6 0 0 1 0 12z" fill="currentColor"/></svg></button>
      <a class="btn btn-ghost sc-hide-sm" href="/index.php?auth=login"><?= $en ? 'Sign in' : 'Se connecter' ?></a>
      <a class="btn btn-primary sc-hide-xs" href="/index.php?auth=signup"><?= $en ? 'Create account' : 'Créer un compte' ?></a>
      <details class="sc-menu">
        <summary class="chip" aria-label="<?= $h($menu) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></summary>
        <div class="sc-menu-pop">
          <?= $nav ?>
          <a href="/index.php?auth=login"><?= $en ? 'Sign in' : 'Se connecter' ?></a>
          <a href="/index.php?auth=signup" class="sc-menu-cta"><?= $en ? 'Create account' : 'Créer un compte' ?></a>
        </div>
      </details>
    </div>
  </div>
</header>
<?php
        return (string)ob_get_clean();
    }

    /** The footer: brand, three columns of links, and the bottom line. */
    public static function footer(string $lang): string
    {
        $en = $lang === 'en';
        $h = [self::class, 'h'];
        $mail = self::contactEmail();
        $cols = $en ? [
            'Platform' => [
                ['/index.php#parcours', 'How it works'], ['/index.php#direct', 'Live evaluations'], ['/index.php#roles', 'Who it is for'],
                ['/evaluations.php', 'Evaluations'], ['/verify.php', 'Verify a document'],
            ],
            'Resources' => [
                ['/documentation.php', 'Documentation'], ['/documentation.php#students', 'Student guide'], ['/documentation.php#teachers', 'Teacher guide'],
                ['/documentation.php#faq', 'Questions and answers'], ['mailto:' . $mail . '?subject=StudyVibe%20support', 'Contact support'],
            ],
            'Company' => [
                ['/careers.php', 'Careers'], ['/careers.php#roles', 'Open positions'], ['/terms.php', 'Terms of use'], ['/privacy.php', 'Privacy'], ['/documentation.php#security', 'Security'],
                ['mailto:' . $mail, 'Contact us'],
            ],
        ] : [
            'Plateforme' => [
                ['/index.php#parcours', 'Le parcours'], ['/index.php#direct', 'Évaluations en direct'], ['/index.php#roles', 'Pour qui'],
                ['/evaluations.php', 'Évaluations'], ['/verify.php', 'Vérifier un document'],
            ],
            'Ressources' => [
                ['/documentation.php', 'Documentation'], ['/documentation.php#students', 'Guide de l’étudiant'], ['/documentation.php#teachers', 'Guide de l’enseignant'],
                ['/documentation.php#faq', 'Questions fréquentes'], ['mailto:' . $mail . '?subject=Assistance%20StudyVibe', 'Contacter l’assistance'],
            ],
            'Entreprise' => [
                ['/careers.php', 'Carrières'], ['/careers.php#roles', 'Postes ouverts'], ['/terms.php', 'Conditions d’utilisation'], ['/privacy.php', 'Confidentialité'], ['/documentation.php#security', 'Sécurité'],
                ['mailto:' . $mail, 'Nous contacter'],
            ],
        ];
        ob_start(); ?>
<footer class="sc-foot" id="sc-foot">
  <div class="sc-wrap">
    <div class="sc-foot-grid">
      <div class="sc-foot-brand">
        <a href="/index.php" aria-label="StudyVibe"><img src="/assets/img/logo-wordmark-white.svg" alt="StudyVibe" width="156" height="48" loading="lazy"></a>
        <p><?= $en ? 'Courses, assignments, live evaluations marked without hand grading, and a certificate anyone can verify.' : 'Des cours, des devoirs, des évaluations en direct notées sans correction à la main, et un certificat que chacun peut vérifier.' ?></p>
        <a class="sc-foot-mail" href="mailto:<?= $h($mail) ?>" title="<?= $h($mail) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><span><?= $en ? 'Contact us' : 'Nous contacter' ?></span></a>
      </div>
      <?php foreach ($cols as $title => $items): ?>
        <nav class="sc-foot-col" aria-label="<?= $h($title) ?>">
          <h2><?= $h($title) ?></h2>
          <ul>
            <?php foreach ($items as [$href, $label]): ?>
              <li><a href="<?= $h($href) ?>"><?= $h($label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </nav>
      <?php endforeach; ?>
    </div>
    <div class="sc-foot-bar">
      <span>© <?= date('Y') ?> StudyVibe. <?= $en ? 'All rights reserved.' : 'Tous droits réservés.' ?></span>
      <span class="sc-foot-legal">
        <a href="/terms.php"><?= $en ? 'Terms' : 'Conditions' ?></a>
        <a href="/privacy.php"><?= $en ? 'Privacy' : 'Confidentialité' ?></a>
        <a href="/documentation.php"><?= $en ? 'Documentation' : 'Documentation' ?></a>
        <a href="/careers.php"><?= $en ? 'Careers' : 'Carrières' ?></a>
        <a href="#top" class="sc-foot-up" data-scroll-top><?= $en ? 'Back to top' : 'Haut de page' ?> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5"/><path d="m6 11 6-6 6 6"/></svg></a>
      </span>
    </div>
  </div>
</footer>
<?php
        return (string)ob_get_clean();
    }

    public static function scripts(): string
    {
        return '<script src="/assets/js/site.js" defer></script>';
    }
}
