<?php
declare(strict_types=1);

/**
 * Illustrations of the careers pages, drawn as inline SVG so they follow the theme (colours are CSS variables defined in
 * assets/css/careers.css, with a dark-mode set) and weigh a few kilobytes each. Decorative: aria-hidden unless an $alt is given.
 */
final class CareersArt
{
    private static function open(string $viewBox, string $class, string $alt): string
    {
        $a = $alt !== '' ? 'role="img" aria-label="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' : 'aria-hidden="true"';
        return '<svg class="ca-art ' . $class . '" viewBox="' . $viewBox . '" ' . $a . ' xmlns="http://www.w3.org/2000/svg" focusable="false">';
    }

    /** The big scene of the careers page: two colleagues at a desk, with a certificate, a checklist and a chat bubble around them. */
    public static function hero(string $alt = ''): string
    {
        return self::open('0 0 640 520', 'ca-hero-art', $alt) . <<<'SVG'
<path class="a-soft" d="M96 262C78 150 176 58 318 54c140-4 250 66 262 196 12 132-84 222-262 222C146 472 114 372 96 262Z"/>
<circle class="a-dots" cx="548" cy="86" r="3"/><circle class="a-dots" cx="566" cy="86" r="3"/><circle class="a-dots" cx="584" cy="86" r="3"/>
<circle class="a-dots" cx="548" cy="104" r="3"/><circle class="a-dots" cx="566" cy="104" r="3"/><circle class="a-dots" cx="584" cy="104" r="3"/>
<path class="a-orbit" d="M60 400C40 300 80 190 170 140"/>
<path class="a-orbit" d="M596 360c18-70 8-150-32-210"/>

<!-- window -->
<g transform="translate(168 118)">
  <rect class="a-card" width="164" height="128" rx="14"/>
  <path class="a-line" d="M82 0v128M0 64h164"/>
  <circle class="a-ochre" cx="122" cy="34" r="15"/>
  <path class="a-leaf-l" d="M10 118l36-38 26 20 28-32 54 50z"/>
</g>

<!-- desk -->
<rect class="a-pine" x="96" y="376" width="448" height="16" rx="8"/>
<rect class="a-pine-d" x="128" y="392" width="12" height="82" rx="4"/>
<rect class="a-pine-d" x="500" y="392" width="12" height="82" rx="4"/>
<ellipse class="a-shadow" cx="320" cy="478" rx="236" ry="9"/>

<!-- person on the left -->
<g>
  <rect class="a-clay" x="148" y="306" width="74" height="76" rx="28"/>
  <path class="a-skin1" d="M176 306h18v-10h-18z"/>
  <circle class="a-skin1" cx="185" cy="274" r="25"/>
  <path class="a-hair1" d="M160 272c-2-22 14-34 28-34 18 0 30 14 26 34-6-12-14-17-26-17-12 0-22 5-28 17Z"/>
  <path class="a-skin1-s" d="M212 340c14 6 26 14 38 20" fill="none" stroke-width="12" stroke-linecap="round"/>
  <circle cx="177" cy="276" r="2.2" class="a-ink"/><circle cx="193" cy="276" r="2.2" class="a-ink"/>
  <path class="a-mouth" d="M180 287c3 3 8 3 11 0"/>
</g>

<!-- laptop -->
<rect class="a-ink" x="246" y="284" width="148" height="92" rx="9"/>
<rect class="a-screen" x="254" y="292" width="132" height="76" rx="5"/>
<circle class="a-clay" cx="278" cy="318" r="14"/>
<path class="a-check" d="M271 318l5 5 9-10"/>
<rect class="a-line-f" x="302" y="308" width="64" height="6" rx="3"/>
<rect class="a-line-f" x="302" y="320" width="48" height="6" rx="3"/>
<rect class="a-line-f" x="266" y="342" width="100" height="6" rx="3"/>
<rect class="a-line-f" x="266" y="354" width="72" height="6" rx="3"/>
<rect class="a-base" x="236" y="374" width="168" height="6" rx="3"/>

<!-- person on the right -->
<g>
  <rect class="a-pine-b" x="430" y="302" width="76" height="80" rx="28"/>
  <path class="a-skin2" d="M458 302h18v-10h-18z"/>
  <circle class="a-skin2" cx="467" cy="268" r="25"/>
  <path class="a-hair2" d="M442 266c0-20 12-32 26-32s26 12 26 30c-6-10-14-14-26-14-12 0-20 4-26 16Z"/>
  <circle class="a-hair2" cx="468" cy="232" r="11"/>
  <path class="a-skin2-s" d="M436 336c-14 2-28 6-40 14" fill="none" stroke-width="12" stroke-linecap="round"/>
  <circle cx="459" cy="270" r="2.2" class="a-ink"/><circle cx="475" cy="270" r="2.2" class="a-ink"/>
  <path class="a-mouth" d="M462 281c3 3 8 3 11 0"/>
</g>

<!-- mug and plant -->
<g transform="translate(104 346)">
  <rect class="a-ochre" x="0" y="0" width="28" height="30" rx="6"/>
  <path class="a-mug-h" d="M28 8h6a6 6 0 0 1 0 14h-6"/>
  <path class="a-steam" d="M8 -6c-4-6 4-8 0-14M18 -6c-4-6 4-8 0-14"/>
</g>
<g transform="translate(512 332)">
  <path class="a-leaf" d="M16 20C6 8 8-8 18-20c8 12 8 28-2 40Z"/>
  <path class="a-leaf-d" d="M18 22C30 12 40 0 38-14c-14 6-24 18-20 36Z"/>
  <path class="a-leaf" d="M14 22C2 18-8 8-8-4c12 0 22 10 22 26Z"/>
  <rect class="a-clay-d" x="4" y="22" width="30" height="22" rx="5"/>
</g>

<!-- floating cards -->
<g transform="translate(52 150)"><g class="a-float a-f1">
  <rect class="a-card a-shadow-card" width="124" height="86" rx="12"/>
  <rect class="a-clay" x="14" y="14" width="44" height="7" rx="3.5"/>
  <rect class="a-line-f" x="14" y="30" width="96" height="5" rx="2.5"/>
  <rect class="a-line-f" x="14" y="42" width="78" height="5" rx="2.5"/>
  <circle class="a-ochre" cx="94" cy="64" r="12"/>
  <path class="a-ribbon" d="M88 72l-4 12 10-5 10 5-4-12z"/>
  <path class="a-check-w" d="M89 63l4 4 7-8"/>
</g></g>
<g transform="translate(458 118)"><g class="a-float a-f2">
  <rect class="a-card a-shadow-card" width="132" height="104" rx="12"/>
  <rect class="a-pine-b" x="14" y="14" width="14" height="14" rx="4"/><path class="a-check-w" d="M17 21l3 3 5-6"/>
  <rect class="a-line-f" x="36" y="18" width="72" height="6" rx="3"/>
  <rect class="a-pine-b" x="14" y="40" width="14" height="14" rx="4"/><path class="a-check-w" d="M17 47l3 3 5-6"/>
  <rect class="a-line-f" x="36" y="44" width="58" height="6" rx="3"/>
  <rect class="a-box" x="14" y="66" width="14" height="14" rx="4"/>
  <rect class="a-line-f" x="36" y="70" width="66" height="6" rx="3"/>
</g></g>
<g transform="translate(330 44)"><g class="a-float a-f3">
  <path class="a-bubble a-shadow-card" d="M10 0h96a10 10 0 0 1 10 10v36a10 10 0 0 1-10 10H44l-16 14V56H10A10 10 0 0 1 0 46V10A10 10 0 0 1 10 0Z"/>
  <circle class="a-clay" cx="36" cy="28" r="5"/><circle class="a-clay" cx="58" cy="28" r="5"/><circle class="a-clay" cx="80" cy="28" r="5"/>
</g></g>

<!-- sparkles -->
<path class="a-spark" d="M120 96l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/>
<path class="a-spark a-spark-s" d="M560 300l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
<path class="a-spark a-spark-s" d="M80 330l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }

    /** A round icon for the four values: learn | craft | care | own. */
    public static function value(string $name): string
    {
        $paths = [
            // an open book with a spark
            'learn' => '<path d="M6 8c3-1.6 6-1.6 9 0v13c-3-1.6-6-1.6-9 0z"/><path d="M15 8c3-1.6 6-1.6 9 0v13c-3-1.6-6-1.6-9 0z"/><path d="M26 6l1 2.4 2.4 1-2.4 1L26 13l-1-2.6-2.4-1 2.4-1z" class="ca-ic-fill"/>',
            // a pencil on a ruler
            'craft' => '<path d="M7 24l1.4-5.6L20.8 6A2.6 2.6 0 0 1 24.5 6l1 1a2.6 2.6 0 0 1 0 3.7L13.2 23.2z"/><path d="M18.6 8.4l4.6 4.6"/><path d="M6 27h20"/>',
            // a heart
            'care' => '<path d="M16 26S5 19.4 5 12.3A5.8 5.8 0 0 1 16 9.6a5.8 5.8 0 0 1 11 2.7C27 19.4 16 26 16 26z"/>',
            // a flag
            'own' => '<path d="M8 27V5"/><path d="M8 7h14l-3 5 3 5H8"/>',
        ];
        $p = $paths[$name] ?? $paths['learn'];
        return '<svg class="ca-ic" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }

    /** Icons of the four hiring steps (1..4): form, magnifier, chat, check. */
    public static function step(int $n): string
    {
        $paths = [
            1 => '<rect x="7" y="4" width="18" height="24" rx="3"/><path d="M12 11h8M12 16h8M12 21h5"/>',
            2 => '<circle cx="14" cy="14" r="8"/><path d="m20 20 7 7"/><path d="M11 14l2.4 2.4L17.5 12"/>',
            3 => '<path d="M5 8a3 3 0 0 1 3-3h16a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H15l-6 5v-5H8a3 3 0 0 1-3-3z"/><path d="M11 12h10M11 16h6"/>',
            4 => '<circle cx="16" cy="16" r="11"/><path d="m10.5 16.5 3.8 3.8 7.4-8"/>',
        ];
        return '<svg class="ca-ic" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$n] ?? $paths[1]) . '</svg>';
    }

    /** No open position: a calm desk and a telescope looking at the horizon. */
    public static function empty(string $alt = ''): string
    {
        return self::open('0 0 420 320', 'ca-empty-art', $alt) . <<<'SVG'
<ellipse class="a-soft" cx="210" cy="170" rx="190" ry="132"/>
<circle class="a-ochre" cx="318" cy="84" r="22"/>
<path class="a-cloud" d="M70 98a16 16 0 0 1 30-6 13 13 0 0 1 22 10H72a9 9 0 0 1-2-4Z"/>
<path class="a-cloud" d="M280 150a12 12 0 0 1 22-4 10 10 0 0 1 17 8h-37a8 8 0 0 1-2-4Z"/>
<path class="a-hill" d="M18 222c50-44 108-52 160-30 40 16 76 14 106-6 40-26 90-14 118 36v52H18z"/>
<rect class="a-pine" x="58" y="236" width="304" height="14" rx="7"/>
<rect class="a-pine-d" x="86" y="250" width="10" height="56" rx="4"/><rect class="a-pine-d" x="324" y="250" width="10" height="56" rx="4"/>
<g transform="translate(150 150)">
  <path class="a-line-s" d="M52 86L34 140M52 86l18 54M52 86v54" fill="none" stroke-width="5" stroke-linecap="round"/>
  <g transform="rotate(-24 52 82)">
    <rect class="a-clay" x="4" y="60" width="96" height="26" rx="7"/>
    <rect class="a-clay-d" x="84" y="56" width="26" height="34" rx="6"/>
    <rect class="a-ochre" x="30" y="58" width="8" height="30"/>
    <rect class="a-ochre" x="62" y="58" width="8" height="30"/>
  </g>
</g>
<g transform="translate(266 192)">
  <path class="a-leaf" d="M20 40C8 26 10 6 22 -8c10 14 10 34-2 48Z"/>
  <path class="a-leaf-d" d="M24 40C38 30 46 14 44 -2c-16 6-26 22-20 42Z"/>
  <rect class="a-clay-d" x="8" y="38" width="36" height="8" rx="3"/>
</g>
<path class="a-spark" d="M92 160l3 8 8 3-8 3-3 8-3-8-8-3 8-3z"/>
<path class="a-spark a-spark-s" d="M340 190l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }

    /** The application is sent: a paper plane leaving with a check mark. */
    public static function sent(string $alt = ''): string
    {
        return self::open('0 0 360 280', 'ca-sent-art', $alt) . <<<'SVG'
<circle class="a-soft" cx="180" cy="144" r="118"/>
<path class="a-orbit" d="M36 224C84 236 120 214 150 178"/>
<path class="a-orbit" d="M270 66c26-14 50-8 64 10"/>
<g class="a-plane" transform="translate(96 74)">
  <path class="a-paper" d="M0 66L156 0 112 128 76 88z"/>
  <path class="a-paper-d" d="M76 88L156 0 56 96z"/>
  <path class="a-paper-s" d="M76 88l-2 34 20-24z"/>
</g>
<circle class="a-clay" cx="262" cy="206" r="30"/>
<path class="a-check-w" d="M248 207l10 10 18-20" stroke-width="6"/>
<path class="a-spark" d="M64 94l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/>
<path class="a-spark a-spark-s" d="M312 120l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
<circle class="a-ochre" cx="90" cy="206" r="6"/><circle class="a-pine-b" cx="300" cy="44" r="5"/>
</svg>
SVG;
    }

    /** A letter taking off: used on the "open application" band. */
    public static function envelope(string $alt = ''): string
    {
        return self::open('0 0 300 220', 'ca-env-art', $alt) . <<<'SVG'
<ellipse class="a-soft-w" cx="150" cy="116" rx="132" ry="88"/>
<g transform="translate(58 62) rotate(-8 92 56)"><g class="a-float a-f2">
  <rect class="a-card" width="184" height="116" rx="12"/>
  <path class="a-env-flap" d="M6 10l86 62 86-62"/>
  <path class="a-line" d="M8 108l62-52M176 108l-62-52"/>
  <circle class="a-clay" cx="92" cy="72" r="14"/>
  <path class="a-check-w" d="M85 72l5 5 9-11"/>
</g></g>
<path class="a-orbit-w" d="M20 176c34 12 62-2 78-26"/>
<path class="a-spark-w" d="M238 40l4 9 9 4-9 4-4 9-4-9-9-4 9-4z"/>
<path class="a-spark-w a-spark-s" d="M36 56l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }
}
