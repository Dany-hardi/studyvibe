<?php
declare(strict_types=1);

/**
 * The look of every StudyVibe email: light, rounded, friendly.
 *
 *   - a white card with generous rounded corners on a soft warm background, no heavy bars
 *   - the logo at the top, embedded in the message itself (cid:svlogo@studyvibe), so it shows even when the mail client blocks
 *     remote images or the site is not reachable from the internet (a hosted image could not load from a developer machine)
 *   - headlines in Fraunces and text in Hanken Grotesk where the mail client loads web fonts (Apple Mail, iOS Mail, Outlook for
 *     Mac, Thunderbird, Samsung Mail); Gmail and Outlook on Windows ignore web fonts and show Georgia and Helvetica/Arial instead,
 *     which the font stacks are chosen to look good with
 *   - pill badges, rounded buttons, soft callouts, key/value cards, numbered steps, a big "score" block
 *   - built from tables with inline styles, which is what mail clients render reliably; rounded corners are dropped (not broken)
 *     by clients that do not support them
 *
 * Mailer builds each message with these pieces and sends it as multipart (HTML + plain text) with the logo attached inline.
 */
final class EmailTheme
{
    public const LOGO_CID = 'svlogo@studyvibe';

    private const INK   = '#2B2722';
    private const MUTED = '#7A7467';
    private const SOFT  = '#4E483F';
    private const LINE  = '#EFEAE0';
    private const CLAY  = '#B5482A';
    private const PAGE  = '#F7F5F0';
    private const CARD2 = '#FAF8F4';

    private const DISPLAY = "'Fraunces', Georgia, 'Times New Roman', serif";
    private const BODY    = "'Hanken Grotesk', -apple-system, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif";

    /** badge / callout tones: [background, text] */
    private const TONES = [
        'clay'  => ['#FBEDE6', '#96391E'],
        'pine'  => ['#E8F2EA', '#24402F'],
        'ochre' => ['#FBF2DC', '#7A5A12'],
        'ink'   => ['#F1EEE7', '#4E483F'],
        'red'   => ['#FCE9E6', '#9C2B1F'],
    ];

    public static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The page
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * @param string $content   HTML built with the components below
     * @param array{preheader?:string,lang?:string,unsubscribe?:string,footer?:string} $o
     */
    public static function layout(string $content, array $o = []): string
    {
        $lang = ($o['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
        $app = defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
        $pre = (string)($o['preheader'] ?? '');
        $preHtml = $pre !== ''
            ? "<div style='display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:" . self::PAGE . ";opacity:0;'>" . self::h($pre) . str_repeat('&nbsp;&zwnj;', 30) . '</div>'
            : '';
        $t = $lang === 'en'
            ? ['tag' => 'The platform for higher education', 'auto' => 'You are receiving this message because of an action on your StudyVibe account.', 'open' => 'Open StudyVibe', 'unsub' => 'Unsubscribe']
            : ['tag' => 'La plateforme pour l’enseignement supérieur', 'auto' => 'Vous recevez ce message à la suite d’une action sur votre compte StudyVibe.', 'open' => 'Ouvrir StudyVibe', 'unsub' => 'Se désabonner'];
        $footerNote = isset($o['footer']) ? "<p style='margin:0 0 10px 0;'>" . $o['footer'] . '</p>' : "<p style='margin:0 0 10px 0;'>" . self::h($t['auto']) . '</p>';
        $unsub = !empty($o['unsubscribe'])
            ? " &nbsp;·&nbsp; <a href='" . self::h((string)$o['unsubscribe']) . "' style='color:" . self::MUTED . ";text-decoration:underline;'>" . self::h($t['unsub']) . '</a>'
            : '';
        $bd = self::BODY;
        $dp = self::DISPLAY;

        return "<!DOCTYPE html>
<html lang='{$lang}' xmlns='http://www.w3.org/1999/xhtml'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<meta name='color-scheme' content='light only'>
<meta name='supported-color-schemes' content='light only'>
<title>StudyVibe</title>
<link href='https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Hanken+Grotesk:wght@400;600;700&display=swap' rel='stylesheet'>
<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&family=Hanken+Grotesk:wght@400;600;700&display=swap');
body{margin:0;padding:0;background:" . self::PAGE . ";}
a{color:" . self::CLAY . ";}
@media only screen and (max-width:620px){
  .sv-pad{padding-left:22px!important;padding-right:22px!important;}
  .sv-title{font-size:21px!important;}
  .sv-btn a{display:block!important;}
}
</style>
</head>
<body style=\"margin:0;padding:0;background-color:" . self::PAGE . ";font-family:{$bd};-webkit-font-smoothing:antialiased;-webkit-text-size-adjust:100%;\">
{$preHtml}
<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color:" . self::PAGE . ";'>
 <tr><td align='center' style='padding:32px 14px 40px 14px;'>
  <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width:600px;'>
   <tr><td class='sv-pad' style='padding:0 8px 18px 8px;'>
     <a href='" . self::h($app !== '' ? $app : '#') . "' style='text-decoration:none;'><img src='cid:" . self::LOGO_CID . "' width='148' alt='StudyVibe' style='display:block;border:0;outline:none;width:148px;height:auto;font-family:{$dp};font-size:22px;color:" . self::INK . ";'></a>
   </td></tr>
   <tr><td style='background-color:#FFFFFF;border:1px solid " . self::LINE . ";border-radius:24px;'>
     <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'><tr>
       <td class='sv-pad' style=\"padding:28px 32px 28px 32px;font-family:{$bd};font-size:14px;line-height:1.55;color:" . self::SOFT . ";\">
{$content}
       </td></tr></table>
   </td></tr>
   <tr><td class='sv-pad' align='center' style=\"padding:22px 16px 0 16px;font-family:{$bd};font-size:12px;line-height:1.6;color:" . self::MUTED . ";text-align:center;\">
     {$footerNote}
     <p style='margin:0;'><strong style='color:" . self::SOFT . ";'>StudyVibe</strong> &nbsp;·&nbsp; " . self::h($t['tag']) . "</p>
     <p style='margin:8px 0 0 0;'><a href='" . self::h($app !== '' ? $app : '#') . "' style='color:" . self::CLAY . ";text-decoration:none;font-weight:700;'>" . self::h($t['open']) . "</a>{$unsub}</p>
   </td></tr>
  </table>
 </td></tr>
</table>
</body>
</html>";
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Pieces
    // ---------------------------------------------------------------------------------------------------------------

    /** Small rounded label above the headline. */
    public static function badge(string $text, string $tone = 'clay'): string
    {
        [$bg, $fg] = self::TONES[$tone] ?? self::TONES['clay'];
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin:0 0 12px 0;'><tr><td style=\"background-color:{$bg};color:{$fg};border-radius:999px;padding:5px 12px;font-family:" . self::BODY . ";font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;line-height:1;\">" . self::h($text) . '</td></tr></table>';
    }

    public static function title(string $text): string
    {
        return "<h1 class='sv-title' style=\"margin:0 0 10px 0;font-family:" . self::DISPLAY . ";font-weight:500;font-size:24px;line-height:1.2;letter-spacing:-.02em;color:" . self::INK . ";\">" . self::h($text) . '</h1>';
    }

    /** Opening sentence, a little larger and softer than body text. */
    public static function lede(string $html): string
    {
        return "<p style=\"margin:0 0 14px 0;font-size:15px;line-height:1.55;color:" . self::SOFT . ";\">{$html}</p>";
    }

    public static function p(string $html, string $style = ''): string
    {
        return "<p style=\"margin:0 0 10px 0;font-size:14px;line-height:1.55;color:" . self::SOFT . ";{$style}\">{$html}</p>";
    }

    public static function small(string $html): string
    {
        return "<p style=\"margin:14px 0 0 0;font-size:12px;line-height:1.55;color:" . self::MUTED . ";\">{$html}</p>";
    }

    /** Rounded call-to-action. $kind: 'primary' (filled) or 'ghost' (outlined). */
    public static function button(string $url, string $label, string $kind = 'primary'): string
    {
        $u = self::h($url);
        $filled = $kind === 'primary';
        $bg = $filled ? self::CLAY : '#FFFFFF';
        $fg = $filled ? '#FFFFFF' : self::CLAY;
        $bd = $filled ? self::CLAY : '#E4D9CB';
        return "<table role='presentation' class='sv-btn' border='0' cellpadding='0' cellspacing='0' style='margin:6px 10px 6px 0;display:inline-block;'><tr>"
            . "<td align='center' bgcolor='{$bg}' style=\"border-radius:14px;background-color:{$bg};border:1.5px solid {$bd};\">"
            . "<a href='{$u}' target='_blank' style=\"display:inline-block;padding:12px 24px;font-family:" . self::BODY . ";font-size:14px;font-weight:700;line-height:1;color:{$fg};text-decoration:none;border-radius:14px;\">" . self::h($label) . '</a>'
            . '</td></tr></table>';
    }

    /** Soft tinted box for a note, a warning, an expiry. */
    public static function callout(string $html, string $tone = 'clay', ?string $title = null): string
    {
        [$bg, $fg] = self::TONES[$tone] ?? self::TONES['clay'];
        $t = $title !== null ? "<div style=\"font-weight:700;color:{$fg};margin:0 0 4px 0;\">" . self::h($title) . '</div>' : '';
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:12px 0;'><tr>"
            . "<td style=\"background-color:{$bg};border-radius:14px;padding:12px 16px;font-family:" . self::BODY . ";font-size:13px;line-height:1.5;color:" . self::SOFT . ";\">{$t}{$html}</td></tr></table>";
    }

    /**
     * Rounded card of label / value rows (date, time, place, duration...). Values are HTML, labels plain text.
     *
     * @param array<int,array{0:string,1:string}> $rows
     */
    public static function facts(array $rows): string
    {
        $out = "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:12px 0;'><tr><td style=\"background-color:" . self::CARD2 . ";border:1px solid " . self::LINE . ";border-radius:16px;padding:2px 16px;\">"
             . "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>";
        $n = count($rows);
        foreach ($rows as $i => [$label, $value]) {
            $line = $i < $n - 1 ? 'border-bottom:1px solid ' . self::LINE . ';' : '';
            $out .= "<tr><td valign='top' style=\"padding:8px 10px 8px 0;{$line}font-family:" . self::BODY . ";font-size:12px;color:" . self::MUTED . ";width:38%;\">" . self::h($label) . '</td>'
                  . "<td valign='top' style=\"padding:8px 0;{$line}font-family:" . self::BODY . ";font-size:14px;font-weight:600;color:" . self::INK . ";\">{$value}</td></tr>";
        }
        return $out . '</table></td></tr></table>';
    }

    /** A big figure in a rounded block: the mark, the score. */
    public static function stat(string $label, string $value, string $sub = '', string $tone = 'pine'): string
    {
        [$bg, $fg] = self::TONES[$tone] ?? self::TONES['pine'];
        $subHtml = $sub !== '' ? "<div style=\"font-size:12px;color:{$fg};margin-top:4px;\">" . self::h($sub) . '</div>' : '';
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:12px 0;'><tr><td align='center' style=\"background-color:{$bg};border-radius:18px;padding:16px 14px;font-family:" . self::BODY . ";\">"
            . "<div style=\"font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{$fg};\">" . self::h($label) . '</div>'
            . "<div style=\"font-family:" . self::DISPLAY . ";font-size:34px;line-height:1.1;font-weight:500;color:{$fg};margin-top:4px;\">" . self::h($value) . '</div>'
            . $subHtml . '</td></tr></table>';
    }

    /**
     * Numbered steps in rounded circles. Items are HTML; give each as [title, text] or just text.
     *
     * @param array<int,string|array{0:string,1:string}> $items
     */
    public static function steps(array $items): string
    {
        $out = "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:8px 0 2px 0;'>";
        foreach ($items as $i => $it) {
            [$head, $text] = is_array($it) ? $it : ['', $it];
            $n = $i + 1;
            $out .= "<tr><td valign='top' width='36' style='padding:0 0 9px 0;'>"
                  . "<table role='presentation' border='0' cellpadding='0' cellspacing='0'><tr><td align='center' width='24' height='24' style=\"width:24px;height:24px;border-radius:12px;background-color:#FBEDE6;color:#96391E;font-family:" . self::BODY . ";font-size:12px;font-weight:700;line-height:24px;\">{$n}</td></tr></table>"
                  . "</td><td valign='top' style=\"padding:2px 0 9px 0;font-family:" . self::BODY . ";font-size:13.5px;line-height:1.5;color:" . self::SOFT . ";\">"
                  . ($head !== '' ? "<strong style='color:" . self::INK . ";'>" . self::h($head) . '</strong><br>' : '') . $text . '</td></tr>';
        }
        return $out . '</table>';
    }

    /**
     * A rounded box with a small heading and a compact list: "to do" (pine) and "not to do" (red) lists of rules.
     *
     * @param string[] $items HTML
     */
    public static function rules(string $heading, array $items, string $tone = 'pine'): string
    {
        [$bg, $fg] = self::TONES[$tone] ?? self::TONES['pine'];
        $mark = $tone === 'red' ? '&times;' : '&#10003;';
        $rows = '';
        foreach ($items as $it) {
            $rows .= "<tr><td valign='top' width='20' style=\"padding:2px 0 5px 0;font-family:" . self::BODY . ";font-size:13px;font-weight:700;color:{$fg};\">{$mark}</td>"
                   . "<td valign='top' style=\"padding:2px 0 5px 0;font-family:" . self::BODY . ";font-size:13px;line-height:1.5;color:" . self::SOFT . ";\">{$it}</td></tr>";
        }
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:10px 0;'><tr>"
            . "<td style=\"background-color:{$bg};border-radius:14px;padding:12px 16px;\">"
            . "<div style=\"font-family:" . self::BODY . ";font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:{$fg};margin:0 0 6px 0;\">" . self::h($heading) . '</div>'
            . "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>{$rows}</table></td></tr></table>";
    }

    /** A code or key in a rounded chip. */
    public static function code(string $text): string
    {
        return "<span style=\"display:inline-block;background-color:#F1EEE7;border-radius:8px;padding:2px 8px;font-family:'SFMono-Regular',Menlo,Consolas,monospace;font-size:12px;font-weight:700;color:" . self::INK . ";letter-spacing:.03em;\">" . self::h($text) . '</span>';
    }

    /** A message written by a person (direct message), in a soft rounded quote. */
    public static function quote(string $plainText): string
    {
        return "<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin:12px 0;'><tr><td style=\"background-color:" . self::CARD2 . ";border-radius:14px;padding:14px 18px;font-family:" . self::BODY . ";font-size:14px;line-height:1.6;color:" . self::INK . ";white-space:pre-wrap;\">" . self::h($plainText) . '</td></tr></table>';
    }

    public static function divider(): string
    {
        return "<div style='height:1px;line-height:1px;font-size:0;background-color:" . self::LINE . ";margin:16px 0;'>&nbsp;</div>";
    }

    public static function sectionTitle(string $text): string
    {
        return "<h2 style=\"margin:18px 0 8px 0;font-family:" . self::DISPLAY . ";font-weight:500;font-size:17px;line-height:1.25;color:" . self::INK . ";\">" . self::h($text) . '</h2>';
    }

    /** The link written out, for clients that hide buttons. */
    public static function linkFallback(string $url, string $lang = 'fr'): string
    {
        $lead = $lang === 'en' ? 'The button does not show? Copy this link into your browser:' : 'Le bouton ne s’affiche pas ? Copiez ce lien dans votre navigateur :';
        return "<p style=\"margin:12px 0 2px 0;font-size:12px;color:" . self::MUTED . ";\">" . self::h($lead) . "</p><p style='margin:0;font-size:12px;word-break:break-all;'><a href='" . self::h($url) . "' style='color:" . self::CLAY . ";'>" . self::h($url) . '</a></p>';
    }

    public static function signature(string $lang = 'fr', string $who = ''): string
    {
        $who = $who !== '' ? $who : ($lang === 'en' ? 'The StudyVibe team' : 'L’équipe StudyVibe');
        $bye = $lang === 'en' ? 'Warm regards,' : 'Chaleureusement,';
        return "<p style=\"margin:16px 0 0 0;font-size:14px;line-height:1.55;color:" . self::SOFT . ";\">" . self::h($bye) . '<br><strong style="color:' . self::INK . ';">' . self::h($who) . '</strong></p>';
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Plain text
    // ---------------------------------------------------------------------------------------------------------------

    /** The same message as plain text, for the text/plain part (and for clients that show no HTML). */
    public static function toText(string $html): string
    {
        $html = preg_replace('/<(style|head|script)\b.*?<\/\1>/is', '', $html) ?? $html;
        $html = preg_replace_callback('/<a\b[^>]*href=([\'"])(.*?)\1[^>]*>(.*?)<\/a>/is', function ($m) {
            $label = trim(strip_tags($m[3]));
            if ($label === '') {
                return '';   // a link around an image (the logo): nothing to say in plain text
            }
            return $label !== $m[2] ? $label . ' (' . $m[2] . ')' : $m[2];
        }, $html) ?? $html;
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|h1|h2|h3|tr|div|table)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/td>/i', '  ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/ ?\n ?/", "\n", $text) ?? $text;
        return trim((string)preg_replace("/\n{3,}/", "\n\n", $text)) . "\n";
    }
}
