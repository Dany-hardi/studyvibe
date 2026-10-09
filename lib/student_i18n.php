<?php
declare(strict_types=1);

/**
 * Student area helpers: FR/EN strings (locales/student.php), localized dates and
 * the few inline icons shared by the student pages. Nothing here touches the database.
 */

if (!function_exists('sdLang')) {
    function sdLang(): string
    {
        return TranslationService::getLang() === 'en' ? 'en' : 'fr';
    }

    function sdDict(): array
    {
        static $d = null;
        if ($d === null) {
            $d = require __DIR__ . '/../locales/student.php';
        }
        return $d;
    }

    /** Translated string; `:name` placeholders are replaced from $r. */
    function sd(string $key, array $r = []): string
    {
        $d = sdDict();
        $s = $d[sdLang()][$key] ?? $d['fr'][$key] ?? $key;
        foreach ($r as $k => $v) {
            $s = str_replace(':' . $k, (string)$v, $s);
        }
        return $s;
    }

    /** Translated string, HTML-escaped. */
    function sde(string $key, array $r = []): string
    {
        return htmlspecialchars(sd($key, $r), ENT_QUOTES, 'UTF-8');
    }

    /** All `js_*` strings for the active language (exposed to JS as window.SV_T). */
    function sdJs(): array
    {
        $d = sdDict();
        $out = [];
        foreach ($d['fr'] as $k => $v) {
            if (strpos($k, 'js_') === 0) {
                $out[substr($k, 3)] = $d[sdLang()][$k] ?? $v;
            }
        }
        return $out;
    }

    function sdH(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Localized date. $style: 'day' (7 octobre), 'short' (7 oct.), 'full' (mercredi 7 octobre),
     * 'date' (07/10/2026), 'time' (21:03), 'dt' (jeu. 9 oct. · 21:03), 'year' (7 octobre 2026).
     */
    function sdDate($ts, string $style = 'date'): string
    {
        $t = is_int($ts) ? $ts : (int)strtotime((string)$ts);
        if ($t <= 0) {
            return '';
        }
        $loc = sdLang() === 'en' ? 'en_US' : 'fr_FR';
        $patterns = [
            'day'   => sdLang() === 'en' ? 'MMMM d' : 'd MMMM',
            'short' => sdLang() === 'en' ? 'MMM d' : 'd MMM',
            'full'  => sdLang() === 'en' ? 'EEEE, MMMM d' : 'EEEE d MMMM',
            'year'  => sdLang() === 'en' ? 'MMMM d, yyyy' : 'd MMMM yyyy',
            'date'  => sdLang() === 'en' ? 'MM/dd/yyyy' : 'dd/MM/yyyy',
            'time'  => 'HH:mm',
            'dt'    => sdLang() === 'en' ? "EEE, MMM d '·' HH:mm" : "EEE d MMM '·' HH:mm",
        ];
        $p = $patterns[$style] ?? $patterns['date'];
        if (class_exists('IntlDateFormatter')) {
            $f = new IntlDateFormatter($loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN, $p);
            $r = $f->format($t);
            if ($r !== false) {
                return $r;
            }
        }
        return date('d/m/Y', $t);
    }

    /** "today", "tomorrow", "in 3 days" for a future timestamp, '' when past. */
    function sdRelative($ts): string
    {
        $t = is_int($ts) ? $ts : (int)strtotime((string)$ts);
        $days = (int)floor(($t - strtotime('today')) / 86400);
        if ($days < 0) {
            return '';
        }
        if ($days === 0) {
            return sd('rel_today');
        }
        if ($days === 1) {
            return sd('rel_tomorrow');
        }
        return sd('rel_days', ['n' => $days]);
    }

    function sdIcon(string $name, int $px = 20): string
    {
        $paths = [
            'home'    => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5.5h4V20"/>',
            'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5Z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
            'exam'    => '<rect x="5" y="3.5" width="14" height="17.5" rx="2"/><path d="M9 3.5V2.5h6v1"/><path d="m8.5 12 2 2 4-4.5"/><path d="M9 17.5h6"/>',
            'results' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-4"/><path d="M12 16V8"/><path d="M16 16v-6"/>',
            'cert'    => '<circle cx="12" cy="9" r="5.5"/><path d="m8.5 13.5-1.5 7 5-2.5 5 2.5-1.5-7"/>',
            'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c1-4 4-6 7.5-6s6.5 2 7.5 6"/>',
            'bell'    => '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2H4.5Z"/><path d="M10 21h4"/>',
            'out'     => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
            'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
            'moon'    => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/>',
            'arrow'   => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            'check'   => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'x'       => '<path d="m6 6 12 12M18 6 6 18"/>',
            'list'    => '<path d="M4 6h16M4 12h16M4 18h10"/>',
            'chat'    => '<path d="M4 5h16v11H9l-5 4Z"/>',
            'note'    => '<path d="M5 3.5h10l4 4V20.5H5Z"/><path d="M14.5 3.5v4.5H19"/><path d="M8.5 13h7M8.5 16.5h5"/>',
            'spark'   => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
            'down'    => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
            'print'   => '<path d="M7 8V3.5h10V8"/><rect x="4" y="8" width="16" height="9" rx="2"/><path d="M7 14h10v6.5H7Z"/>',
            'link'    => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
            'play'    => '<path d="M8 5.5v13l11-6.5Z"/>',
            'lock'    => '<rect x="5" y="11" width="14" height="9.5" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        ];
        $p = $paths[$name] ?? '';
        return '<svg class="sd-ic" width="' . $px . '" height="' . $px . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }

    /** Dark-mode early boot + theme toggle button markup shared by pages. */
    function sdThemeBoot(): string
    {
        return "<script>document.documentElement.classList.add('js');try{var s=localStorage.getItem('sv_dark');if(s==='1'||(s===null&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');}catch(e){}</script>";
    }

    function sdFontsLink(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
             . '<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">';
    }

    /** Resolve the 4-letter state of a score for pass/fail wording (never colour only). */
    function sdScore($v): string
    {
        $n = (float)$v;
        return rtrim(rtrim(number_format($n, 1, sdLang() === 'en' ? '.' : ',', ''), '0'), sdLang() === 'en' ? '.' : ',');
    }
}
