<?php
declare(strict_types=1);

/**
 * StudyVibe brand entry point. EVERY page, email and generated document must get
 * the logo through this class so the mark can change in one place. Full guide: docs/BRAND.md
 *
 * The logo: "StudyVibe" in a rounded monoline wordmark (outlined paths, no webfont needed anywhere):
 * "study" in ink, "vibe" in terracotta where the V is a check mark, and an orange spark as the i-dot.
 * The symbol (favicon, avatar, app icon) is the white check on a terracotta rounded square with the spark.
 * Palette: terracotta/clay #B5482A, word ink #14151C, spark #FF6A3D, page ink #1E1B16, paper #F5F0E6, ochre #D9A23B.
 *
 * WEB (inline SVG, follows the page theme through --ink / --clay tokens)
 *   Brand::headLinks()             in <head>: favicon, apple-touch-icon, brand.css, once-per-session flag
 *   Brand::logo('md')              wordmark. sizes: sm | md | lg | xl
 *   Brand::logo('lg', true)        same + intro: types itself on, then one shine sweep. Plays once per browser session.
 *   Every logo is alive: every 60 s the letters hop in a small wave; on hover they hop higher and a band of light sweeps
 *   across them. Nothing moves with prefers-reduced-motion. All in assets/css/brand.css (no JavaScript).
 *   Brand::mark(24)                the bookmark symbol only (img tag)
 *
 * FILES (absolute paths, for PDF / mail / office libraries)
 *   Brand::svgFile($variant)       color | dark | mono | mark   -> SVG
 *   Brand::pngFile($variant)       color | mono | email | mark | icon -> PNG on disk
 *   Brand::pdfPng($mono = false)   PNG for PDFs (1024 px wide, transparent)
 *   Brand::url($file)              absolute https URL of an asset (uses APP_URL), for HTML email
 *   Brand::emailHeader($bg)        ready-made <img> logo block for HTML email (hosted PNG + alt)
 *   Brand::dataUri($variant)       base64 data URI of a PNG (self-contained HTML/print views)
 *
 * VECTOR
 *   Brand::wordmarkSvg($variant)   standalone <svg> string: color | dark | mono
 *   Brand::inlineSvg($variant, $heightPx)  same, sized, for HTML/print where you want vector
 *   Brand::pdfOps($x,$y,$w,$mono)  native PDF vector operators of the wordmark (raw PDF writers, no image)
 *   Brand::latexPreamble()         LaTeX snippet (xcolor definitions + \svlogo macro using logo.png)
 *
 * COLOURS (documents must use these, never the old green)
 *   Brand::CLAY, INK, PAPER, OCHRE, LINE, INK2, PINE
 *
 * Regenerate the assets with the build in docs/BRAND.md (lib/BrandData.php is generated).
 */
final class Brand
{
    public const NAME  = 'StudyVibe';
    public const CLAY  = '#B5482A';
    public const CLAY_PRESS = '#96391E';
    public const INK   = '#1E1B16';
    public const INK2  = '#4A453C';
    public const PAPER = '#F5F0E6';
    public const CARD  = '#FBF8F2';
    public const LINE  = '#DDD5C3';
    public const OCHRE = '#D9A23B';
    public const PINE  = '#24402F';
    /** The i-dot of the wordmark ("spark"). */
    public const SPARK = '#FF6A3D';
    /** Dark ink of the wordmark letters ("study"). */
    public const WORD  = '#14151C';

    private static ?array $data = null;
    private static int $uid = 0;

    private static function img(): string { return dirname(__DIR__) . '/assets/img/'; }

    private static function data(): array
    {
        return self::$data ??= require __DIR__ . '/BrandData.php';
    }

    public static function headLinks(): string
    {
        return '<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">'
             . '<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon.png">'
             . '<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">'
             . '<meta name="theme-color" content="' . self::PAPER . '">'
             . '<link rel="manifest" href="/manifest.webmanifest">'
             . '<link rel="stylesheet" href="/assets/css/brand.css?v=' . (int)@filemtime(dirname(__DIR__) . '/assets/css/brand.css') . '">'
             // intro plays once per session: mark the session as "seen" after the animation, skip it on later loads
             . '<script>(function(){try{var d=document.documentElement;if(sessionStorage.getItem("sv_brand_seen")){d.classList.add("sv-brand-seen")}else{setTimeout(function(){try{sessionStorage.setItem("sv_brand_seen","1")}catch(e){}},4200)}}catch(e){}})();</script>'
             // installable app: the service worker keeps static files and shows an offline page (see /sw.js)
             . '<script>if("serviceWorker"in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/sw.js").catch(function(){})})}</script>';
    }

    public static function mark(int $px = 24): string
    {
        return '<img class="sv-brand-mark" src="/assets/img/logo-mark.svg" alt="" width="' . $px . '" height="' . $px . '">';
    }

    /** @param string $size sm|md|lg|xl   @param bool $animate play the intro animation (once per session) */
    public static function logo(string $size = 'md', bool $animate = false): string
    {
        $d = self::data();
        [$vx, $vy, $vw, $vh] = $d['vb'];
        $size = in_array($size, ['sm', 'md', 'lg', 'xl'], true) ? $size : 'md';
        $cls = 'sv-brand sv-brand--' . $size . ($animate ? ' sv-brand--animate' : '');

        // k: i = ink letters ("study"), c = terracotta ("vibe", the check-mark V first), s = the spark i-dot
        $u = ++self::$uid;
        $paths = '';
        $clip = '';
        foreach ($d['letters'] as $i => $l) {
            $k = ['i' => 'sv-l sv-l--i', 'c' => 'sv-l sv-l--c', 's' => 'sv-l sv-l--s'][$l['k']] ?? 'sv-l sv-l--i';
            $fill = ['i' => self::WORD, 'c' => self::CLAY, 's' => self::SPARK][$l['k']] ?? self::WORD;
            $paths .= '<path id="svp' . $u . '-' . $i . '" class="' . $k . '" style="--i:' . $i . '" fill="' . $fill . '" fill-rule="evenodd" d="' . $l['d'] . '"/>';
            $clip .= '<use href="#svp' . $u . '-' . $i . '" clip-rule="evenodd"/>';
        }
        // The shine: a soft diagonal light band clipped to the letters (so it never lights the page behind). Hidden until hovered,
        // see brand.css. The letters are referenced, not copied, so the markup stays small.
        $band = (int)round($vw * 0.26);
        $shine = '<defs><linearGradient id="svg' . $u . '" gradientTransform="rotate(20 .5 .5)">'
               . '<stop offset="0" class="sv-sh" stop-opacity="0"/><stop offset=".5" class="sv-sh" stop-opacity=".95"/><stop offset="1" class="sv-sh" stop-opacity="0"/></linearGradient>'
               . '<clipPath id="svc' . $u . '">' . $clip . '</clipPath></defs>'
               . '<g clip-path="url(#svc' . $u . ')" aria-hidden="true"><rect class="sv-shine" x="' . $vx . '" y="' . $vy . '" width="' . $band . '" height="' . $vh . '" fill="url(#svg' . $u . ')"/></g>';
        return '<span class="' . $cls . '" role="img" aria-label="StudyVibe">'
             . '<svg class="sv-wm" viewBox="' . "$vx $vy $vw $vh" . '" aria-hidden="true" focusable="false">'
             . $paths . $shine . '</svg></span>';
    }

    /** Standalone SVG document for variant color|dark|mono. */
    public static function wordmarkSvg(string $variant = 'color'): string
    {
        return (string)file_get_contents(self::svgFile($variant));
    }

    /** Vector wordmark ready to drop in HTML (fixed colours, no CSS dependency). */
    public static function inlineSvg(string $variant = 'color', int $heightPx = 32): string
    {
        $svg = preg_replace('/^<svg /', '<svg style="height:' . $heightPx . 'px;width:auto;display:block" ', self::wordmarkSvg($variant), 1);
        return $svg;
    }

    public static function svgFile(string $variant = 'color'): string
    {
        $map = ['color' => 'logo-wordmark.svg', 'dark' => 'logo-wordmark-dark.svg', 'mono' => 'logo-mono.svg', 'mark' => 'logo-mark.svg'];
        return self::img() . ($map[$variant] ?? $map['mark']);
    }

    public static function pngFile(string $variant = 'mark'): string
    {
        $map = ['color' => 'logo.png', 'mono' => 'logo-mono.png', 'email' => 'logo-email.png', 'mark' => 'favicon.png', 'icon' => 'apple-touch-icon.png'];
        return self::img() . ($map[$variant] ?? $map['mark']);
    }

    /** PNG of the full wordmark for PDFs (1024 px wide, transparent). One-colour ink version when $mono. */
    public static function pdfPng(bool $mono = false): string
    {
        return self::pngFile($mono ? 'mono' : 'color');
    }

    public static function url(string $file = 'logo-email.png'): string
    {
        $base = defined('APP_URL') ? rtrim((string)APP_URL, '/') : '';
        return $base . '/assets/img/' . ltrim($file, '/');
    }

    public static function dataUri(string $variant = 'color'): string
    {
        $f = self::pngFile($variant);
        return is_file($f) ? 'data:image/png;base64,' . base64_encode((string)file_get_contents($f)) : self::url(basename($f));
    }

    /** HTML-email logo block (an <img> hosted on APP_URL with alt text). Table-safe, no CSS needed. */
    public static function emailHeader(string $bg = self::PAPER, int $width = 190): string
    {
        $vb = self::data()['vb'];
        $h = (int)round($width * $vb[3] / $vb[2]);
        return "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' style='background:{$bg};'><tr><td style='padding:26px 36px 18px 36px;'>"
             . "<a href='" . htmlspecialchars(self::url(''), ENT_QUOTES) . "' style='text-decoration:none;'>"
             . "<img src='" . htmlspecialchars(self::url('logo-email.png'), ENT_QUOTES) . "' width='{$width}' height='{$h}' alt='StudyVibe' style='display:block;border:0;outline:none;width:{$width}px;height:{$h}px;color:" . self::INK . ";font-family:Arial,sans-serif;font-size:22px;'>"
             . "</a></td></tr></table>";
    }


    /** RGB triplet 0..1 for a #RRGGBB colour (PDF operators). */
    public static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }

    /** Height in points of the wordmark when drawn $width wide. */
    public static function pdfHeight(float $width): float
    {
        $vb = self::data()['vb'];
        return $width * $vb[3] / $vb[2];
    }

    /**
     * Wordmark as native PDF vector operators (no image, no font): paste into a page content stream.
     * $x,$y = bottom-left corner in PDF points, $width in points. $mono = one colour (ink) for b/w print.
     */
    public static function pdfOps(float $x, float $y, float $width, bool $mono = false): string
    {
        $d = self::data();
        [$vx, $vy, $vw, $vh] = $d['vb'];
        $sc = $width / $vw;
        $out = "q\n";
        foreach ($d['letters'] as $l) {
            $hex = ($mono || $l['k'] === 'i') ? self::WORD : ($l['k'] === 's' ? self::SPARK : self::CLAY);
            $c = self::rgb($hex);
            $out .= sprintf("%.4F %.4F %.4F rg\n", $c[0], $c[1], $c[2]);
            $out .= self::svgPathToPdf($l['d'], $x, $y, $sc, $vx, $vy, $vh) . "f*\n";
        }
        return $out . "Q\n";
    }

    /** Converts M/L/H/V/Q/C/Z absolute SVG path data to PDF path construction operators. */
    private static function svgPathToPdf(string $dd, float $x, float $y, float $sc, float $vx, float $vy, float $vh): string
    {
        preg_match_all('/([MLHVQCZ])([^MLHVQCZ]*)/', $dd, $m, PREG_SET_ORDER);
        $px = fn(float $X): float => $x + ($X - $vx) * $sc;
        $py = fn(float $Y): float => $y + ($vy + $vh - $Y) * $sc;
        $o = ''; $cx = 0.0; $cy = 0.0; $sx = 0.0; $sy = 0.0;
        foreach ($m as [, $cmd, $args]) {
            preg_match_all('/-?\d*\.?\d+(?:e-?\d+)?/i', $args, $nm);
            $n = array_map('floatval', $nm[0]);
            switch ($cmd) {
                case 'M': $cx = $sx = $n[0]; $cy = $sy = $n[1]; $o .= sprintf("%.2F %.2F m\n", $px($cx), $py($cy)); for ($i = 2; $i + 1 < count($n); $i += 2) { $cx = $n[$i]; $cy = $n[$i + 1]; $o .= sprintf("%.2F %.2F l\n", $px($cx), $py($cy)); } break;
                case 'L': for ($i = 0; $i + 1 < count($n); $i += 2) { $cx = $n[$i]; $cy = $n[$i + 1]; $o .= sprintf("%.2F %.2F l\n", $px($cx), $py($cy)); } break;
                case 'H': foreach ($n as $v) { $cx = $v; $o .= sprintf("%.2F %.2F l\n", $px($cx), $py($cy)); } break;
                case 'V': foreach ($n as $v) { $cy = $v; $o .= sprintf("%.2F %.2F l\n", $px($cx), $py($cy)); } break;
                case 'Q':
                    for ($i = 0; $i + 3 < count($n); $i += 4) {
                        [$qx, $qy, $ex, $ey] = [$n[$i], $n[$i + 1], $n[$i + 2], $n[$i + 3]];
                        $c1x = $cx + 2 / 3 * ($qx - $cx); $c1y = $cy + 2 / 3 * ($qy - $cy);
                        $c2x = $ex + 2 / 3 * ($qx - $ex); $c2y = $ey + 2 / 3 * ($qy - $ey);
                        $o .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px($c1x), $py($c1y), $px($c2x), $py($c2y), $px($ex), $py($ey));
                        $cx = $ex; $cy = $ey;
                    }
                    break;
                case 'C':
                    for ($i = 0; $i + 5 < count($n); $i += 6) {
                        $o .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c\n", $px($n[$i]), $py($n[$i + 1]), $px($n[$i + 2]), $py($n[$i + 3]), $px($n[$i + 4]), $py($n[$i + 5]));
                        $cx = $n[$i + 4]; $cy = $n[$i + 5];
                    }
                    break;
                case 'Z': $o .= "h\n"; $cx = $sx; $cy = $sy; break;
            }
        }
        return $o;
    }

    /**
     * LaTeX preamble snippet: graphicx + xcolor, brand colours (svclay, svink, svpaper, svochre) and the macros
     * \svlogo[width] (colour) and \svlogomono[width] (one colour, for black and white print).
     * LatexCompiler::compile() stages the images next to document.tex as svlogo.png / svlogo-mono.png.
     */
    public static function latexPreamble(): string
    {
        return "\\usepackage{graphicx}\n\\usepackage{xcolor}\n"
             . "\\definecolor{svclay}{HTML}{B5482A}\n\\definecolor{svink}{HTML}{1E1B16}\n\\definecolor{svpaper}{HTML}{F5F0E6}\n\\definecolor{svochre}{HTML}{D9A23B}\n"
             . "\\newcommand{\\svlogo}[1][4.6cm]{\\includegraphics[width=#1]{svlogo.png}}\n"
             . "\\newcommand{\\svlogomono}[1][4.6cm]{\\includegraphics[width=#1]{svlogo-mono.png}}\n";
    }
}
