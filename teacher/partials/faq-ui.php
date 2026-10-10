<?php
/**
 * Shared design of the teacher guides (marking guide, import guide): section illustrations and the section renderer.
 * Plain inline SVG, no external file. Colours come from the page variables so dark mode follows.
 */
if (!function_exists('faqArt')) {
    /** A flat illustration: one person and a prop that matches the theme. */
    function faqArt(string $theme): string
    {
        $skin = ['auto' => '#e9b895', 'assign' => '#c98f6b', 'talk' => '#f1c9a5', 'prep' => '#a8734f', 'zip' => '#e3ad8a', 'fix' => '#d9a07c'][$theme] ?? '#e9b895';
        $hair = ['auto' => '#2b2118', 'assign' => '#1c1612', 'talk' => '#7a4a2a', 'prep' => '#1c1612', 'zip' => '#3a2a1e', 'fix' => '#5a3a24'][$theme] ?? '#2b2118';
        $shirt = ['auto' => '#b5472b', 'assign' => '#3f6b5a', 'talk' => '#c98a2b', 'prep' => '#3b5b7a', 'zip' => '#7a4b8a', 'fix' => '#b5472b'][$theme] ?? '#b5472b';
        $person = '<g class="fa-person">'
            . '<path d="M58 150c0-26 14-40 34-40s34 14 34 40z" fill="' . $shirt . '"/>'
            . '<rect x="84" y="92" width="16" height="22" rx="7" fill="' . $skin . '"/>'
            . '<circle cx="92" cy="74" r="22" fill="' . $skin . '"/>'
            . '<path d="M70 72c-2-18 10-30 24-30 16 0 26 12 22 30-6-8-14-12-24-12-8 0-16 4-22 12z" fill="' . $hair . '"/>'
            . '<circle cx="85" cy="76" r="2.2" fill="#2b2118"/><circle cx="100" cy="76" r="2.2" fill="#2b2118"/>'
            . '<path d="M86 85q6 5 12 0" stroke="#2b2118" stroke-width="2" fill="none" stroke-linecap="round"/>'
            . '</g>';
        $props = [
            // ticking a score sheet with a percentage ring
            'auto' => '<rect x="132" y="38" width="104" height="92" rx="12" fill="#fff" stroke="#d9d2c3"/>'
                . '<circle cx="168" cy="82" r="22" fill="none" stroke="#e7dfce" stroke-width="9"/>'
                . '<path d="M168 60a22 22 0 1 1-20 31" fill="none" stroke="#b5472b" stroke-width="9" stroke-linecap="round"/>'
                . '<text x="168" y="87" text-anchor="middle" font-size="14" font-weight="700" fill="#2b2118" font-family="sans-serif">80%</text>'
                . '<rect x="198" y="62" width="28" height="6" rx="3" fill="#e7dfce"/><rect x="198" y="76" width="22" height="6" rx="3" fill="#e7dfce"/><rect x="198" y="90" width="26" height="6" rx="3" fill="#e7dfce"/>'
                . '<path d="M146 112l6 6 12-14" stroke="#3f6b5a" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
            // marking a paper with a red pen
            'assign' => '<rect x="136" y="34" width="84" height="104" rx="8" fill="#fff" stroke="#d9d2c3" transform="rotate(4 178 86)"/>'
                . '<rect x="130" y="40" width="84" height="104" rx="8" fill="#fff" stroke="#d9d2c3"/>'
                . '<rect x="142" y="54" width="48" height="6" rx="3" fill="#cfc6b3"/><rect x="142" y="68" width="60" height="5" rx="2.5" fill="#e7dfce"/><rect x="142" y="80" width="54" height="5" rx="2.5" fill="#e7dfce"/><rect x="142" y="92" width="40" height="5" rx="2.5" fill="#e7dfce"/>'
                . '<circle cx="196" cy="118" r="17" fill="none" stroke="#b5472b" stroke-width="3.5"/><text x="196" y="124" text-anchor="middle" font-size="16" font-weight="700" fill="#b5472b" font-family="sans-serif">16</text>'
                . '<path d="M222 52l10 10-30 30-12 2 2-12z" fill="#b5472b"/><path d="M222 52l10 10" stroke="#8a3520" stroke-width="3"/>',
            // two speech bubbles
            'talk' => '<rect x="134" y="36" width="92" height="42" rx="14" fill="#fff" stroke="#d9d2c3"/><path d="M152 78l-4 14 16-14z" fill="#fff" stroke="#d9d2c3"/>'
                . '<rect x="146" y="49" width="56" height="6" rx="3" fill="#e7dfce"/><rect x="146" y="61" width="38" height="6" rx="3" fill="#e7dfce"/>'
                . '<rect x="150" y="94" width="86" height="40" rx="14" fill="#b5472b"/><path d="M220 134l6 12-18-12z" fill="#b5472b"/>'
                . '<rect x="164" y="106" width="52" height="6" rx="3" fill="#f4d7cc"/><rect x="164" y="118" width="34" height="6" rx="3" fill="#f4d7cc"/>',
            // a spreadsheet
            'prep' => '<rect x="128" y="36" width="112" height="98" rx="10" fill="#fff" stroke="#d9d2c3"/>'
                . '<rect x="128" y="36" width="112" height="20" rx="10" fill="#3f6b5a"/><rect x="128" y="46" width="112" height="10" fill="#3f6b5a"/>'
                . '<path d="M156 56v78M184 56v78M212 56v78M128 76h112M128 96h112M128 116h112" stroke="#e7dfce" stroke-width="1.5"/>'
                . '<rect x="158" y="78" width="24" height="16" fill="#f4d7cc"/><text x="140" y="49" font-size="9" font-weight="700" fill="#fff" font-family="sans-serif">A</text><text x="168" y="49" font-size="9" font-weight="700" fill="#fff" font-family="sans-serif">B</text><text x="196" y="49" font-size="9" font-weight="700" fill="#fff" font-family="sans-serif">C</text><text x="224" y="49" font-size="9" font-weight="700" fill="#fff" font-family="sans-serif">D</text>',
            // a zip box holding a picture
            'zip' => '<rect x="132" y="62" width="104" height="76" rx="10" fill="#e6d3a8" stroke="#c9b27a"/>'
                . '<rect x="128" y="48" width="112" height="24" rx="8" fill="#d9bf86" stroke="#c9b27a"/>'
                . '<path d="M184 48v90" stroke="#c9b27a" stroke-width="2" stroke-dasharray="4 4"/><rect x="178" y="82" width="12" height="16" rx="2" fill="#7a4b8a"/>'
                . '<rect x="146" y="14" width="44" height="36" rx="5" fill="#fff" stroke="#d9d2c3" transform="rotate(-8 168 32)"/><circle cx="160" cy="26" r="5" fill="#c98a2b"/><path d="M150 44l12-12 8 8 6-6 12 10z" fill="#3f6b5a" transform="rotate(-8 168 32)"/>',
            // a magnifier over a checked line
            'fix' => '<rect x="132" y="40" width="104" height="92" rx="10" fill="#fff" stroke="#d9d2c3"/>'
                . '<rect x="146" y="56" width="60" height="6" rx="3" fill="#e7dfce"/><rect x="146" y="72" width="76" height="6" rx="3" fill="#e7dfce"/><rect x="146" y="88" width="50" height="6" rx="3" fill="#e7dfce"/>'
                . '<circle cx="200" cy="100" r="24" fill="#fff" fill-opacity=".85" stroke="#b5472b" stroke-width="5"/><path d="M217 117l16 16" stroke="#b5472b" stroke-width="7" stroke-linecap="round"/>'
                . '<path d="M190 100l8 8 14-16" stroke="#3f6b5a" stroke-width="4.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        ];
        return '<svg class="fa-art" viewBox="0 0 260 160" role="img" aria-hidden="true" focusable="false">'
            . '<circle cx="130" cy="86" r="70" fill="var(--paper-2, #efe8d8)"/><circle cx="52" cy="36" r="9" fill="' . $shirt . '" opacity=".18"/><circle cx="246" cy="146" r="12" fill="' . $shirt . '" opacity=".15"/>'
            . ($props[$theme] ?? '') . $person . '</svg>';
    }

    /**
     * Section cards (index) then, for each section, an illustrated banner and its questions.
     * $sections: [['id','theme','title','lede','items'=>[ids]]]; $items: id => ['q','a']; $idp: prefix of the element ids; $fill: callback on the answer.
     */
    function faqRender(array $sections, array $items, string $idp, callable $fill, bool $openFirst = false): void
    {
        $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        echo '<nav class="fa-index" aria-label="Sections">';
        foreach ($sections as $k => $s) {
            echo '<a class="fa-card fa-t-' . $e($s['theme']) . '" href="#' . $e($idp) . 'sec-' . $e($s['id']) . '" data-fa-sec="' . $e($idp) . 'sec-' . $e($s['id']) . '">'
                . '<span class="fa-card-art">' . faqArt($s['theme']) . '</span>'
                . '<span class="fa-card-n">' . ($k + 1) . '</span><strong>' . $e($s['title']) . '</strong>'
                . '<small>' . count($s['items']) . '</small></a>';
        }
        echo '</nav>';
        $n = 0;
        foreach ($sections as $k => $s) {
            echo '<section class="fa-sec fa-t-' . $e($s['theme']) . '" id="' . $e($idp) . 'sec-' . $e($s['id']) . '">'
                . '<header class="fa-banner"><div class="fa-banner-text"><span class="fa-eyebrow">' . ($k + 1) . ' / ' . count($sections) . '</span>'
                . '<h3>' . $e($s['title']) . '</h3><p>' . $e($s['lede']) . '</p></div>' . faqArt($s['theme']) . '</header>';
            foreach ($s['items'] as $id) {
                if (!isset($items[$id])) { continue; }
                $n++;
                echo '<details class="lif-item" id="' . $e($idp) . $e($id) . '"' . ($openFirst && $n === 1 ? ' open' : '') . '>'
                    . '<summary><span class="lif-n">' . $n . '</span>' . $e($items[$id]['q']) . '</summary>'
                    . '<div class="lif-body">' . $fill($items[$id]['a']) . '</div></details>';
            }
            echo '</section>';
        }
    }

    function faqStyles(): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;
        ?>
<style>
.fa-index { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .8rem; margin: 0 0 1.8rem; }
.fa-card { position: relative; display: flex; flex-direction: column; gap: .15rem; padding: 0 0 .9rem; border: 1px solid var(--line); border-radius: 16px; background: var(--card); text-decoration: none; color: var(--ink); overflow: hidden; transition: transform .15s, border-color .15s, box-shadow .15s; }
.fa-card:hover { transform: translateY(-2px); border-color: var(--clay); box-shadow: 0 8px 22px rgba(0,0,0,.07); }
.fa-card-art { display: block; background: var(--paper-2, #efe8d8); height: 104px; overflow: hidden; margin-bottom: .6rem; }
.fa-card-art .fa-art { width: 100%; height: 100%; }
.fa-card-n { position: absolute; top: .55rem; left: .6rem; width: 1.5rem; height: 1.5rem; border-radius: 50%; background: var(--card); font-size: .75rem; font-weight: 700; display: grid; place-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
.fa-card strong { padding: 0 .9rem; font-size: .98rem; line-height: 1.3; }
.fa-card small { padding: 0 .9rem; color: var(--ink-3); font-size: .78rem; }
.fa-sec { margin: 0 0 2.2rem; scroll-margin-top: 5rem; }
.fa-banner { display: flex; align-items: center; justify-content: space-between; gap: 1.2rem; padding: 1.1rem 1.5rem 1.1rem 1.6rem; margin-bottom: .9rem; border-radius: 20px; background: var(--paper-2, #efe8d8); border: 1px solid var(--line); overflow: hidden; }
.fa-banner-text { min-width: 0; }
.fa-banner h3 { margin: .15rem 0 .35rem; font-size: 1.45rem; line-height: 1.2; }
.fa-banner p { margin: 0; color: var(--ink-2); max-width: 36rem; font-size: .95rem; line-height: 1.55; }
.fa-eyebrow { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--clay); font-weight: 700; }
.fa-banner .fa-art { flex: none; width: 260px; max-width: 38%; height: auto; }
.fa-t-assign .fa-eyebrow { color: #3f6b5a; } .fa-t-talk .fa-eyebrow { color: #a8701a; } .fa-t-prep .fa-eyebrow { color: #3b5b7a; } .fa-t-zip .fa-eyebrow { color: #7a4b8a; }
@media (max-width: 640px) { .fa-banner { flex-direction: column-reverse; align-items: flex-start; padding: 1rem; } .fa-banner .fa-art { width: 200px; max-width: 70%; align-self: center; } .fa-banner h3 { font-size: 1.25rem; } }
</style>
<?php
    }
}
