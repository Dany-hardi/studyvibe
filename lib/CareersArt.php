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

    /** The big scene of the careers page: a team around a wall dashboard, a presenter, a person at a desk, a colleague with a tablet. */
    public static function hero(string $alt = ''): string
    {
        require_once __DIR__ . '/CareersPeople.php';
        $present = CareersPeople::standing('amara', 'present', -3);
        $seated = CareersPeople::seated('lea', 'type');
        $marc = CareersPeople::standing('marc', 'tablet', 4);
        return self::open('0 0 720 560', 'ca-hero-svg', $alt) . '<defs>' . CareersPeople::defs() . <<<'SVG'
<linearGradient id="hh-wall" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--g-wall-a)"/><stop offset="1" style="stop-color:var(--g-wall-b)"/></linearGradient>
<linearGradient id="hh-floor" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--g-floor-a)"/><stop offset="1" style="stop-color:var(--g-floor-b)"/></linearGradient>
<linearGradient id="hh-desk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--g-desk-a)"/><stop offset="1" style="stop-color:var(--g-desk-b)"/></linearGradient>
<linearGradient id="hh-ui" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4C6FFF"/><stop offset="1" stop-color="#18C29C"/></linearGradient>
<linearGradient id="hh-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--a-clay)" stop-opacity=".35"/><stop offset="1" style="stop-color:var(--a-clay)" stop-opacity="0"/></linearGradient>
<linearGradient id="hh-lid" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E7E2D6"/><stop offset="1" stop-color="#B9B2A2"/></linearGradient>
<radialGradient id="hh-glow" cx=".5" cy=".5" r=".5"><stop offset="0" style="stop-color:var(--a-soft)"/><stop offset="1" style="stop-color:var(--a-soft)" stop-opacity="0"/></radialGradient>
<filter id="hh-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="10" stdDeviation="9" flood-color="#1E1B16" flood-opacity=".18"/></filter>
<filter id="hh-sh2" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="5" stdDeviation="4" flood-color="#1E1B16" flood-opacity=".2"/></filter>
<clipPath id="hh-scr0"><rect x="262" y="104" width="232" height="146" rx="10"/></clipPath>
</defs>
<ellipse cx="360" cy="270" rx="340" ry="250" fill="url(#hh-glow)"/>
<circle class="a-dots" cx="640" cy="54" r="3"/><circle class="a-dots" cx="658" cy="54" r="3"/><circle class="a-dots" cx="676" cy="54" r="3"/><circle class="a-dots" cx="640" cy="72" r="3"/><circle class="a-dots" cx="658" cy="72" r="3"/><circle class="a-dots" cx="676" cy="72" r="3"/>
<path class="a-orbit" d="M30 420C14 320 44 210 120 150"/><path class="a-orbit" d="M700 380c16-80 6-170-34-236"/>

<!-- room: wall, floor, rug -->
<rect x="48" y="34" width="624" height="414" rx="30" fill="url(#hh-wall)"/>
<path d="M48 440H672V478a30 30 0 0 1-30 30H78a30 30 0 0 1-30-30Z" fill="url(#hh-floor)"/>
<path d="M48 440H672" stroke="var(--a-line)" stroke-width="2" opacity=".8"/>
<ellipse cx="370" cy="478" rx="270" ry="22" class="a-rug"/>
<ellipse cx="370" cy="478" rx="214" ry="14" class="a-rug-2"/>

<!-- window on the left wall -->
<g transform="translate(66 96)"><rect width="84" height="136" rx="10" class="a-window"/><rect x="7" y="7" width="70" height="122" rx="6" fill="var(--a-sky)"/><circle cx="54" cy="38" r="12" class="a-ochre"/><path d="M7 108L30 72L46 92L58 78L77 108V129H7Z" class="a-hill-far"/><path d="M7 118L26 96L48 112L77 90V129H7Z" class="a-hill-near"/><path d="M42 7V129M7 68H77" class="a-line" stroke-width="3"/></g>

<!-- wall dashboard -->
<g filter="url(#hh-sh)"><rect x="270" y="74" width="256" height="170" rx="16" fill="#1D2238"/><rect x="282" y="86" width="232" height="146" rx="10" fill="var(--a-screen)"/></g>
<g transform="translate(20 -18)"><g clip-path="url(#hh-scr0)">
  <rect x="262" y="104" width="232" height="22" fill="url(#hh-ui)" opacity=".95"/><circle cx="274" cy="115" r="3.500" fill="#fff" opacity=".9"/><circle cx="286" cy="115" r="3.500" fill="#fff" opacity=".6"/><rect x="420" y="111" width="64" height="8" rx="4" fill="#fff" opacity=".6"/>
  <rect x="274" y="136" width="64" height="38" rx="8" class="a-tile"/><rect x="346" y="136" width="64" height="38" rx="8" class="a-tile"/><rect x="418" y="136" width="64" height="38" rx="8" class="a-tile"/>
  <rect x="282" y="144" width="26" height="5" rx="2.500" class="a-line-f"/><rect x="282" y="154" width="38" height="12" rx="4" class="a-clay"/><rect x="354" y="144" width="26" height="5" rx="2.500" class="a-line-f"/><rect x="354" y="154" width="30" height="12" rx="4" fill="#18C29C"/><rect x="426" y="144" width="26" height="5" rx="2.500" class="a-line-f"/><rect x="426" y="154" width="44" height="12" rx="4" fill="#4C6FFF"/>
  <path d="M274 236L302 214L330 224L362 196L396 210L430 182L482 190V244H274Z" fill="url(#hh-area)"/><path d="M274 236L302 214L330 224L362 196L396 210L430 182L482 190" fill="none" style="stroke:var(--a-clay)" stroke-width="3.500" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="430" cy="182" r="5.500" fill="var(--a-screen)" style="stroke:var(--a-clay)" stroke-width="3"/>
</g></g>
<path d="M340 244L330 282M456 244L466 282" stroke="var(--a-line)" stroke-width="6" stroke-linecap="round"/>

<!-- plant -->
<g transform="translate(70 440)"><path class="a-leaf-d" d="M0 0C-26-26-26-66-8-96C6-70 12-30 0 0Z"/><path class="a-leaf" d="M2 0C22-20 40-54 36-90C16-72 2-36 2 0Z"/><path class="a-leaf" d="M-2 0C-34-8-58-34-58-64C-30-56-4-32-2 0Z"/><path class="a-leaf-l" d="M0 0C-8-30-4-56 4-80C12-52 10-24 0 0Z"/><path d="M-20 0H20L15 -2L-15 -2Z" fill="none"/><path class="a-pot" d="M-24 0H24L19 34Q0 40-19 34Z"/><path class="a-pot-s" d="M-24 0H24L23 8H-23Z"/></g>


<!-- people -->
<g transform="translate(222 470) scale(1.05)">
SVG
        . $present . <<<'SVG'
</g>
<g transform="translate(442 394) scale(1.02)">
SVG
        . '<rect x="-52" y="-190" width="104" height="170" rx="26" class="a-chair"/>' . $seated . <<<'SVG'
</g>
<!-- desk and laptop (in front of the seated person) -->
<g filter="url(#hh-sh2)"><rect x="318" y="394" width="252" height="16" rx="6" fill="url(#hh-desk)"/></g>
<rect x="334" y="410" width="12" height="62" rx="4" class="a-desk-leg"/><rect x="542" y="410" width="12" height="62" rx="4" class="a-desk-leg"/>
<rect x="318" y="394" width="252" height="4" rx="2" fill="#fff" opacity=".35"/>
<g transform="translate(442 394)"><path d="M-48 0L-44-62Q-44-66-40-66H40Q44-66 44-62L48 0Z" fill="url(#hh-lid)"/><path d="M-48 0L-44-62Q-44-66-40-66H40Q44-66 44-62L48 0Z" fill="none" stroke="#fff" stroke-opacity=".5"/><circle cx="0" cy="-34" r="9" class="a-clay"/><path d="M-4-34L-1-31L5-38" fill="none" stroke="#fff" stroke-width="2.600" stroke-linecap="round" stroke-linejoin="round"/><rect x="-54" y="-3" width="108" height="6" rx="3" fill="#A9A292"/></g>
<g transform="translate(520 394)"><path d="M0 0H20L17-22H3Z" class="a-clay"/><path d="M1.500-14H18.500" stroke="#fff" stroke-opacity=".35" stroke-width="2"/><path d="M3-22C-4-38 4-46 0-58M10-22C5-34 14-44 10-54" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="3" stroke-linecap="round" class="a-steam2"/><path d="M20-18Q30-16 28-8Q26-2 19-3" fill="none" stroke="var(--a-clay)" stroke-width="3"/></g>
<g transform="translate(622 470) scale(1)">
SVG
        . $marc . <<<'SVG'
</g>

<!-- floating UI -->
<g transform="translate(150 22) scale(.82)"><g class="a-float a-f1"><rect class="a-card" width="132" height="94" rx="14" filter="url(#hh-sh2)"/><rect class="a-clay" x="14" y="14" width="46" height="8" rx="4"/><rect class="a-line-f" x="14" y="30" width="102" height="5" rx="2.500"/><rect class="a-line-f" x="14" y="42" width="84" height="5" rx="2.500"/><circle class="a-ochre" cx="100" cy="68" r="14"/><path class="a-ribbon" d="M93 77l-4 14 11-6 11 6-4-14z"/><path class="a-check-w" d="M94 67l5 5 8-9"/><rect class="a-line-f" x="14" y="66" width="52" height="5" rx="2.500"/><rect class="a-line-f" x="14" y="76" width="38" height="5" rx="2.500"/></g></g>
<g transform="translate(544 24)"><g class="a-float a-f3"><path class="a-bubble" filter="url(#hh-sh2)" d="M12 0h96a12 12 0 0 1 12 12v38a12 12 0 0 1-12 12H48l-18 15V62H12A12 12 0 0 1 0 50V12A12 12 0 0 1 12 0Z"/><circle class="a-clay" cx="38" cy="31" r="5.500"/><circle class="a-clay" cx="60" cy="31" r="5.500"/><circle class="a-clay" cx="82" cy="31" r="5.500"/></g></g>
<g transform="translate(6 232) scale(.9)"><g class="a-float a-f2"><rect class="a-card" width="132" height="92" rx="14" filter="url(#hh-sh2)"/><rect fill="#2F5740" x="14" y="14" width="15" height="15" rx="4.500"/><path class="a-check-w" d="M17.500 21.500l3.500 3.500 6-7"/><rect class="a-line-f" x="37" y="18" width="78" height="6" rx="3"/><rect fill="#2F5740" x="14" y="40" width="15" height="15" rx="4.500"/><path class="a-check-w" d="M17.500 47.500l3.500 3.500 6-7"/><rect class="a-line-f" x="37" y="44" width="62" height="6" rx="3"/><rect class="a-box" x="14" y="66" width="15" height="15" rx="4.500"/><rect class="a-line-f" x="37" y="70" width="70" height="6" rx="3"/></g></g>
<path class="a-spark" d="M296 40l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/><path class="a-spark a-spark-s" d="M640 214l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/><path class="a-spark a-spark-s" d="M36 330l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }

    /** A badge for each of the four values: learn | craft | care | own. Two-tone glyph on a tinted gradient tile. */
    public static function value(string $name): string
    {
        $g = [
            // open book under a graduation cap
            'learn' => '<path d="M10 40Q22 35 32 40Q42 35 54 40V54Q42 49 32 54Q22 49 10 54Z" fill="#fff" opacity=".95"/><path d="M32 40V54" stroke="#C9BFA9" stroke-width="1.600"/><path d="M14 44Q22 41 28 44M14 49Q22 46 28 49M36 44Q42 41 50 44M36 49Q42 46 50 49" fill="none" stroke="#DDD5C3" stroke-width="1.500" stroke-linecap="round"/><path d="M32 12L54 22L32 32L10 22Z" fill="var(--v-a)"/><path d="M20 27V35Q32 41 44 35V27L32 32Z" fill="var(--v-b)"/><path d="M52 23V34" stroke="#E5B24F" stroke-width="2" stroke-linecap="round"/><circle cx="52" cy="36" r="2.800" fill="#E5B24F"/>',
            // pencil and ruler
            'craft' => '<rect x="12" y="38" width="42" height="12" rx="3" transform="rotate(-24 33 44)" fill="#fff" opacity=".95"/><g transform="rotate(-24 33 44)" stroke="#C9BFA9" stroke-width="1.600" stroke-linecap="round"><path d="M19 38V43M25 38V45M31 38V43M37 38V45M43 38V43M49 38V45"/></g><g transform="rotate(42 34 28)"><rect x="26" y="8" width="16" height="36" rx="3" fill="var(--v-a)"/><rect x="26" y="8" width="7" height="36" rx="3" fill="var(--v-b)" opacity=".55"/><path d="M26 44H42L34 56Z" fill="#F3D9B6"/><path d="M31 52H37L34 56Z" fill="#2B2620"/><rect x="26" y="8" width="16" height="7" rx="3" fill="#E5B24F"/></g>',
            // heart with a small spark
            'care' => '<path d="M32 54S10 41 10 25A12 12 0 0 1 32 19A12 12 0 0 1 54 25C54 41 32 54 32 54Z" fill="var(--v-a)"/><path d="M32 19A12 12 0 0 0 10 25C10 33 15 40 21 45C16 38 15 30 20 25C23 22 28 22 32 26Z" fill="#fff" opacity=".22"/><path d="M36 28Q44 28 46 35" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".8"/><path d="M50 10l2 5 5 2-5 2-2 5-2-5-5-2 5-2z" fill="#E5B24F"/>',
            // flag planted on a peak
            'own' => '<path d="M4 54L24 24L36 40L44 30L60 54Z" fill="var(--v-b)"/><path d="M24 24L36 40L28 54H4Z" fill="var(--v-a)"/><path d="M24 24L19 32L25 31L28 36Z" fill="#fff" opacity=".7"/><path d="M44 30L38 38L43 37L46 41Z" fill="#fff" opacity=".6"/><path d="M24 24V6" stroke="#2B2620" stroke-width="2.600" stroke-linecap="round"/><path d="M24 7H42L37 13L42 19H24Z" fill="#E5B24F"/>',
        ];
        return '<svg class="ca-ic ca-vi ca-vi-' . $name . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">' . ($g[$name] ?? $g['learn']) . '</svg>';
    }

    /** Spot illustration of one hiring step (1..4): apply, careful read, conversation, decision. People, props and a soft backdrop. */
    public static function step(int $n): string
    {
        require_once __DIR__ . '/CareersPeople.php';
        $open = '<svg class="ca-art ca-step-art" viewBox="0 0 200 160" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><defs>' . CareersPeople::defs()
              . '<filter id="sp-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="4" stdDeviation="3.500" flood-color="#1E1B16" flood-opacity=".2"/></filter></defs>';
        switch ($n) {
            case 1:
                return $open . '<circle cx="104" cy="86" r="66" class="a-soft"/><circle cx="26" cy="38" r="3" class="a-dots"/><circle cx="40" cy="38" r="3" class="a-dots"/>'
                    . '<g transform="translate(110 104) scale(.92)">' . CareersPeople::bust('nora', 'doc', -3) . '</g>'
                    . '<g transform="translate(14 26) rotate(-8)"><g filter="url(#sp-sh)"><rect class="a-card" width="62" height="48" rx="9"/><rect class="a-line-f" x="9" y="12" width="30" height="4.500" rx="2.200"/><rect class="a-field" x="9" y="22" width="44" height="7" rx="3.500"/><rect class="a-field" x="9" y="33" width="44" height="7" rx="3.500"/></g></g>'
                    . '<path class="a-spark" d="M168 28l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/></svg>';
            case 2:
                return $open . '<circle cx="96" cy="84" r="66" class="a-soft"/>'
                    . '<g transform="translate(70 106) scale(.88)">' . CareersPeople::bust('idris', 'none', 6) . '</g>'
                    . '<g transform="translate(112 34) rotate(8)"><g filter="url(#sp-sh)"><rect class="a-card" width="66" height="84" rx="8"/><rect class="a-clay" x="10" y="12" width="26" height="5" rx="2.500"/><rect class="a-line-f" x="10" y="25" width="46" height="4" rx="2"/><rect class="a-hl" x="8" y="33" width="50" height="9" rx="3"/><rect class="a-line-f" x="10" y="35.500" width="40" height="4" rx="2"/><rect class="a-line-f" x="10" y="48" width="46" height="4" rx="2"/><rect class="a-line-f" x="10" y="58" width="34" height="4" rx="2"/><rect class="a-line-f" x="10" y="68" width="42" height="4" rx="2"/></g>'
                    . '<g transform="translate(40 40)"><circle r="19" fill="#fff" fill-opacity=".35" class="a-lens"/><circle r="19" fill="none" stroke="#2A2620" stroke-width="5"/><path d="M14 14L30 30" stroke="#2A2620" stroke-width="8" stroke-linecap="round"/><path d="M-10-10Q-4-15 4-14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".8"/></g></g></svg>';
            case 3:
                return $open . '<ellipse cx="100" cy="92" rx="90" ry="62" class="a-soft"/>'
                    . '<g transform="translate(56 118) scale(.74)">' . CareersPeople::bust('lea', 'none', 7) . '</g>'
                    . '<g transform="translate(146 118) scale(.74)">' . CareersPeople::bust('marc', 'none', -7) . '</g>'
                    . '<g transform="translate(26 14)"><g filter="url(#sp-sh)"><path class="a-bubble" d="M8 0H60A8 8 0 0 1 68 8V26A8 8 0 0 1 60 34H30L18 44V34H8A8 8 0 0 1 0 26V8A8 8 0 0 1 8 0Z"/></g><rect class="a-line-f" x="12" y="10" width="42" height="5" rx="2.500"/><rect class="a-line-f" x="12" y="19" width="28" height="5" rx="2.500"/></g>'
                    . '<g transform="translate(106 8)"><g filter="url(#sp-sh)"><path class="a-bubble-c" d="M8 0H60A8 8 0 0 1 68 8V26A8 8 0 0 1 60 34H56L50 44V34H8A8 8 0 0 1 0 26V8A8 8 0 0 1 8 0Z"/></g><circle cx="22" cy="17" r="4.500" fill="#fff"/><circle cx="36" cy="17" r="4.500" fill="#fff"/><circle cx="50" cy="17" r="4.500" fill="#fff"/></g></svg>';
            default:
                return $open . '<circle cx="100" cy="86" r="66" class="a-soft"/>'
                    . '<g transform="translate(104 108) scale(.92)">' . CareersPeople::bust('amara', 'up', 2) . '</g>'
                    . '<g transform="translate(40 96)"><g filter="url(#sp-sh)"><circle r="26" fill="#2F7D4A"/><circle r="26" fill="url(#sp-gl)"/><circle r="20" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2"/><path d="M-10 1L-3 8L11-8" fill="none" stroke="#fff" stroke-width="5.500" stroke-linecap="round" stroke-linejoin="round"/><path d="M-12 22L-18 44L-8 38L-4 46L2 24Z" class="a-ribbon"/><path d="M12 22L18 44L8 38L4 46L-2 24Z" class="a-ribbon-2"/></g></g>'
                    . '<path class="a-conf" d="M30 30l6 3-3 6-6-3z"/><path class="a-conf-2" d="M164 30l5 5-5 5-5-5z"/><circle class="a-conf-3" cx="22" cy="92" r="4"/><path class="a-conf-3" d="M176 58l7 2-2 7-7-2z"/><path class="a-spark" d="M60 22l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>'
                    . '<defs><linearGradient id="sp-gl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient></defs></svg>';
        }
    }

    /** No open position: a person at a telescope on a hill, looking at the horizon. */
    public static function empty(string $alt = ''): string
    {
        require_once __DIR__ . '/CareersPeople.php';
        $nora = CareersPeople::standing('nora', 'scope', -4);
        return self::open('0 0 440 340', 'ca-empty-art', $alt) . '<defs>' . CareersPeople::defs()
            . '<linearGradient id="em-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--g-sky-a)"/><stop offset="1" style="stop-color:var(--g-sky-b)"/></linearGradient>'
            . '<linearGradient id="em-hill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--g-hill-a)"/><stop offset="1" style="stop-color:var(--g-hill-b)"/></linearGradient>'
            . '<clipPath id="em-clip"><rect x="10" y="10" width="420" height="320" rx="30"/></clipPath></defs>'
            . <<<'SVG'
<g clip-path="url(#em-clip)">
<rect x="10" y="10" width="420" height="320" fill="url(#em-sky)"/>
<circle cx="340" cy="86" r="34" class="a-ochre"/><circle cx="340" cy="86" r="54" class="a-ochre" opacity=".16"/>
<path class="a-cloud" d="M60 84a18 18 0 0 1 34-7a14 14 0 0 1 24 10H66a10 10 0 0 1-6-3Z"/><path class="a-cloud" d="M236 54a14 14 0 0 1 26-5a11 11 0 0 1 19 8h-44a8 8 0 0 1-1-3Z"/>
<path d="M120 52l5-4 5 4M138 40l4-3 4 3" fill="none" stroke="var(--a-mut)" stroke-width="2" stroke-linecap="round"/>
<path class="a-hill-far" d="M10 214C60 168 110 160 160 188C210 214 250 150 310 158C360 164 400 196 430 188V330H10Z"/>
<path d="M10 236C70 196 130 200 190 226C250 250 310 214 360 222C390 226 410 238 430 232V330H10Z" fill="url(#em-hill)"/>
<path d="M300 232l-18 0M318 224l-14 0" stroke="var(--a-hill-ln)" stroke-width="2" stroke-linecap="round"/>
<path d="M388 190L388 154M388 172L378 162M388 166L398 158" stroke="#3B5E49" stroke-width="3" stroke-linecap="round" fill="none"/>
<path d="M36 278l8-14 8 14Z" class="a-leaf-d"/><path d="M52 282l6-10 6 10Z" class="a-leaf"/>
<g transform="translate(210 290) scale(.62)">
SVG
            . $nora . <<<'SVG'
<g transform="translate(0 0)"><path d="M62-236L140-306" stroke="#F3EBDD" stroke-width="1"/>
<g transform="rotate(-38 58 -244)"><rect x="40" y="-256" width="108" height="24" rx="8" fill="#E7E2D6"/><rect x="40" y="-256" width="108" height="9" rx="4.500" fill="#fff" opacity=".6"/><rect x="132" y="-262" width="30" height="36" rx="8" fill="#2A2620"/><rect x="92" y="-258" width="9" height="28" fill="#B5482A"/><rect x="40" y="-256" width="14" height="24" rx="5" fill="#B5482A"/><circle cx="162" cy="-244" r="13" fill="#8FD3FF" opacity=".55"/></g>
<path d="M70-232L36 0M70-232L84 0M70-232L118 0" stroke="#2A2620" stroke-width="6" stroke-linecap="round" fill="none"/><circle cx="70" cy="-232" r="9" fill="#2A2620"/></g>
</g>
<ellipse cx="220" cy="296" rx="120" ry="10" fill="#000" opacity=".08"/>
</g>
<rect x="10" y="10" width="420" height="320" rx="30" fill="none" class="a-line" stroke-width="2"/>
<path class="a-spark" d="M96 138l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/><path class="a-spark a-spark-s" d="M400 120l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }

    /** The application is sent: a person celebrating, a paper plane taking off, confetti. */
    public static function sent(string $alt = ''): string
    {
        require_once __DIR__ . '/CareersPeople.php';
        $amara = CareersPeople::standing('amara', 'cheer', 3);
        return self::open('0 0 380 300', 'ca-sent-art', $alt) . '<defs>' . CareersPeople::defs()
            . '<linearGradient id="se-ok" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3FA062"/><stop offset="1" stop-color="#2A7A49"/></linearGradient>'
            . '<filter id="se-sh" x="-20%" y="-20%" width="140%" height="150%"><feDropShadow dx="0" dy="6" stdDeviation="5" flood-color="#1E1B16" flood-opacity=".2"/></filter></defs>'
            . <<<'SVG'
<ellipse cx="190" cy="150" rx="176" ry="132" class="a-soft"/>
<path class="a-orbit" d="M214 120C250 96 286 76 318 56"/>
<path class="a-conf" d="M52 60l8 4-4 8-8-4z"/><path class="a-conf-2" d="M110 30l6 6-6 6-6-6z"/><circle class="a-conf-3" cx="36" cy="150" r="5"/><path class="a-conf-3" d="M300 150l9 3-3 9-9-3z"/><path class="a-conf" d="M326 200l8 4-4 8-8-4z"/><circle class="a-conf-2" cx="270" cy="30" r="4"/><path class="a-conf-2" d="M72 214l7 3-3 7-7-3z"/>
<g class="a-plane2" filter="url(#se-sh)"><g transform="translate(246 36) rotate(-14)"><path d="M0 34L102 0L74 70L50 50Z" class="a-paper"/><path d="M50 50L102 0L34 44Z" class="a-paper-d"/><path d="M50 50L48 70L62 56Z" class="a-paper-s"/></g></g>
<g transform="translate(166 280) scale(.66)">
SVG
            . $amara . <<<'SVG'
</g>
<g transform="translate(300 232)"><g filter="url(#se-sh)"><circle r="30" fill="url(#se-ok)"/><circle r="23" fill="none" stroke="#fff" stroke-opacity=".45" stroke-width="2"/><path d="M-12 1L-4 9L13-9" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></g></g>
<path class="a-spark" d="M42 104l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/><path class="a-spark a-spark-s" d="M322 118l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
</svg>
SVG;
    }

    /** The "open application" band, on the terracotta background: a person handing over an envelope. */
    public static function envelope(string $alt = ''): string
    {
        require_once __DIR__ . '/CareersPeople.php';
        $lea = CareersPeople::standing('lea', 'wave', -2);
        return self::open('0 0 320 250', 'ca-env-art', $alt) . '<defs>' . CareersPeople::defs() . '<clipPath id="en-clip"><rect width="320" height="250" rx="0"/></clipPath></defs>'
            . <<<'SVG'
<g clip-path="url(#en-clip)">
<circle cx="170" cy="132" r="108" fill="#fff" fill-opacity=".12"/><circle cx="170" cy="132" r="76" fill="#fff" fill-opacity=".1"/>
<path class="a-orbit-w" d="M16 214c44 16 80-6 104-38"/><path class="a-spark-w" d="M284 26l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/><path class="a-spark-w a-spark-s" d="M24 52l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
<g transform="translate(112 316) scale(.84)">
SVG
            . $lea . <<<'SVG'
</g>
</g>
</svg>
SVG;
    }
}
