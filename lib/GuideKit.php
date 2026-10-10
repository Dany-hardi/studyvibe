<?php
declare(strict_types=1);

/**
 * Illustrated guide pages (teacher guides, student guides): one card per question, each opens a full-page reader.
 *
 * GuideKit::render([
 *   'prefix' => 'gd', 'main' => true, 'class' => '', 'heading' => 'h2',
 *   'switchFn' => 'switchDashboardTab', 'switchArg' => 'tab-help',   // how openGuide() reveals the tab holding the main guide
 *   'hero' => null | ['kicker', 'title', 'lede', 'steps' => [...], 'img'],
 *   'cats' => [key => label], 'sections' => [['id','cat','color','title','lede','items' => [ids]]],
 *   'items' => [id => ['q', 'a' (html), 'teaser', 'img', 'fill' => callable|null, 'crumbs' => ...]],
 *   'ui' => ['all','search','read','back','prev','next','none'], 'after' => html shown under the grid,
 * ]);
 * Styles and script are printed once per page.
 */
final class GuideKit
{
    private static bool $assets = false;

    public static function render(array $c): void
    {
        $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $p = preg_replace('/[^a-z0-9-]/i', '', (string)($c['prefix'] ?? 'gd')) ?: 'gd';
        $ui = $c['ui'];
        $items = $c['items'];
        $sections = $c['sections'];
        $cats = $c['cats'];
        $h = ($c['heading'] ?? 'h2') === 'h1' ? 'h1' : 'h2';

        $flat = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $id) {
                if (isset($items[$id])) { $flat[] = ['id' => $id, 'sec' => $s]; }
            }
        }
        $count = count($flat);
        $number = array_flip(array_column($flat, 'id'));
        $catCount = [];
        foreach ($flat as $f) { $catCount[$f['sec']['cat']] = ($catCount[$f['sec']['cat']] ?? 0) + 1; }
        $fill = static fn(array $it) => isset($it['fill']) && is_callable($it['fill']) ? ($it['fill'])($it['a']) : $it['a'];

        echo '<section class="gd ' . $e($c['class'] ?? '') . '" id="' . $p . '" data-gd="' . $p . '"'
            . (!empty($c['main']) ? ' data-gd-main data-gd-switchfn="' . $e($c['switchFn'] ?? '') . '" data-gd-switcharg="' . $e($c['switchArg'] ?? '') . '"' : '') . '>';
        echo '<div class="gd-list">';
        if (!empty($c['hero'])) {
            $hero = $c['hero'];
            echo '<header class="gd-hero"><div class="gd-hero-text"><p class="gd-kicker">' . $e($hero['kicker']) . '</p><' . $h . '>' . $e($hero['title']) . '</' . $h . '>'
                . '<p class="gd-lede">' . $e($hero['lede']) . '</p><ol class="gd-steps">';
            foreach ($hero['steps'] as $i => $st) { echo '<li><span>' . ($i + 1) . '</span>' . $e($st) . '</li>'; }
            echo '</ol></div><img class="gd-hero-art" src="' . $e($hero['img']) . '" alt="" width="420" height="380"></header>';
        }
        echo '<div class="gd-bar">';
        if (count($cats) > 1) {
            echo '<div class="gd-chips" role="group"><button type="button" class="gd-chip" data-gd-cat="all" aria-pressed="true">' . $e($ui['all']) . ' <small>' . $count . '</small></button>';
            foreach ($cats as $k => $label) {
                echo '<button type="button" class="gd-chip" data-gd-cat="' . $e($k) . '" aria-pressed="false">' . $e($label) . ' <small>' . ($catCount[$k] ?? 0) . '</small></button>';
            }
            echo '</div>';
        }
        echo '<label class="gd-search"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>'
            . '<input type="search" data-gd-search placeholder="' . $e($ui['search']) . '" aria-label="' . $e($ui['search']) . '"></label></div>';

        foreach ($sections as $s) {
            echo '<div class="gd-sec" data-gd-sec data-cat="' . $e($s['cat']) . '" style="--gd-c: ' . $e($s['color']) . '"><div class="gd-sec-head"><h3>' . $e($s['title']) . '</h3><p>' . $e($s['lede']) . '</p></div><div class="gd-grid">';
            foreach ($s['items'] as $id) {
                if (!isset($items[$id])) { continue; }
                $it = $items[$id];
                echo '<a class="gd-card" href="#' . $p . '-' . $e($id) . '" data-gd-open="' . $e($id) . '" data-text="' . $e(mb_strtolower($it['q'] . ' ' . $it['teaser'])) . '">'
                    . '<span class="gd-card-art"><img src="' . $e($it['img']) . '" alt="" loading="lazy" width="320" height="240"></span>'
                    . '<span class="gd-card-n">' . ($number[$id] + 1) . '</span>'
                    . '<span class="gd-card-body"><strong>' . $e($it['q']) . '</strong><span>' . $e($it['teaser']) . '</span></span>'
                    . '<span class="gd-card-go">' . $e($ui['read']) . ' <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>';
            }
            echo '</div></div>';
        }
        echo '<p class="gd-none" hidden>' . $e($ui['none']) . '</p>' . ($c['after'] ?? '') . '</div>';

        foreach ($flat as $k => $f) {
            $id = $f['id']; $s = $f['sec']; $it = $items[$id];
            $prev = $flat[$k - 1] ?? null; $next = $flat[$k + 1] ?? null;
            echo '<article class="gd-doc" id="' . $p . '-' . $e($id) . '" data-gd-doc="' . $e($id) . '" hidden style="--gd-c: ' . $e($s['color']) . '">'
                . '<button type="button" class="gd-back" data-gd-back><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> ' . $e($ui['back']) . '</button>'
                . '<header class="gd-doc-head"><div><p class="gd-crumb">' . $e($cats[$s['cat']]) . ' <span>›</span> ' . $e($s['title']) . ' <span>·</span> ' . ($k + 1) . '/' . $count . '</p>'
                . '<h3>' . $e($it['q']) . '</h3><p class="gd-doc-lede">' . $e($it['teaser']) . '</p></div><img src="' . $e($it['img']) . '" alt="" loading="lazy" width="320" height="240"></header>'
                . '<div class="gd-body">' . $fill($it) . '</div><nav class="gd-pn">';
            foreach ([['prev', $prev], ['next', $next]] as [$dir, $t]) {
                if (!$t) { echo '<span></span>'; continue; }
                echo '<a class="gd-pn-' . $dir . '" href="#' . $p . '-' . $e($t['id']) . '" data-gd-open="' . $e($t['id']) . '" style="--gd-c: ' . $e($t['sec']['color']) . '">'
                    . '<img src="' . $e($items[$t['id']]['img']) . '" alt="" loading="lazy" width="120" height="90"><span><small>' . $e($ui[$dir]) . '</small><strong>' . $e($items[$t['id']]['q']) . '</strong></span></a>';
            }
            echo '</nav></article>';
        }
        echo '</section>';
        self::assets();
    }

    /** Styles once, script on every call (it only wires roots not wired yet). */
    private static function assets(): void
    {
        if (!self::$assets) {
            self::$assets = true;
            echo "<style>" . <<<'CSS'
.gd { --gd-c: var(--clay, #b5472b); color: var(--ink); }
.gd [hidden] { display: none !important; }
.gd-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); align-items: center; gap: 2rem; padding: 2.2rem 2.4rem; margin: 0 0 1.6rem; border-radius: 28px; background: linear-gradient(135deg, #f6e7da 0%, #f3ddcf 100%); border: 1px solid var(--line); overflow: hidden; }
.gd-hero-text { min-width: 0; }
.gd-kicker { margin: 0 0 .5rem; font-size: .75rem; letter-spacing: .16em; text-transform: uppercase; font-weight: 700; color: #b5472b; }
.gd-hero h2 { margin: 0 0 .7rem; font-size: clamp(2rem, 3.6vw, 2.8rem); line-height: 1.08; color: #2b2118; }
.gd .gd-lede { margin: 0 0 1.5rem; max-width: 34rem; font-size: 1.02rem; line-height: 1.6; color: #5b4a3d; }
.gd-steps { list-style: none; display: grid; gap: .55rem; margin: 0; padding: 0; }
.gd-steps li { display: flex; align-items: center; gap: .7rem; font-size: .92rem; color: #3b2f26; }
.gd-steps li span { flex: none; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: #b5472b; color: #fff; font-weight: 700; font-size: .8rem; display: grid; place-items: center; }
.gd-hero-art { width: 100%; height: auto; max-height: 300px; object-fit: contain; justify-self: end; }
.gd-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .8rem; margin: 0 0 1.4rem; position: sticky; top: var(--gd-sticky, 3.5rem); z-index: 4; padding: .6rem 0; background: var(--paper, #f5f0e6); }
.gd-chips { display: flex; flex-wrap: wrap; gap: .45rem; }
.gd-chip { border: 1px solid var(--line); background: var(--card); color: var(--ink-2); border-radius: 999px; padding: .45rem .95rem; font: inherit; font-size: .88rem; cursor: pointer; transition: all .15s; }
.gd-chip small { opacity: .6; margin-left: .25rem; }
.gd-chip:hover { border-color: var(--clay); color: var(--ink); }
.gd-chip[aria-pressed="true"] { background: #2b2118; border-color: #2b2118; color: #fff; }
.gd-search { display: flex; align-items: center; gap: .5rem; padding: .45rem .9rem; border: 1px solid var(--line); border-radius: 999px; background: var(--card); color: var(--ink-3); min-width: min(100%, 17rem); }
.gd-search input { border: 0; outline: 0; background: transparent; font: inherit; font-size: .9rem; color: var(--ink); width: 100%; }
.gd-search:focus-within { border-color: var(--clay); }
.gd-sec { margin: 0 0 2.4rem; }
.gd-sec-head { display: flex; align-items: baseline; gap: 1rem; flex-wrap: wrap; margin: 0 0 1rem; padding-left: .9rem; border-left: 4px solid var(--gd-c); }
.gd-sec-head h3 { margin: 0; font-size: 1.35rem; }
.gd-sec-head p { margin: 0; color: var(--ink-2); font-size: .92rem; flex: 1 1 18rem; }
.gd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.1rem; }
.gd-card { position: relative; display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--line); border-radius: 22px; overflow: hidden; text-decoration: none; color: var(--ink); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
.gd-card:hover, .gd-card:focus-visible { transform: translateY(-4px); box-shadow: 0 16px 34px rgba(43, 33, 24, .12); border-color: var(--gd-c); outline: none; }
.gd-card-art { display: block; aspect-ratio: 4 / 3; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); padding: .9rem 1rem 0; overflow: hidden; }
.gd-card-art img { width: 100%; height: 100%; object-fit: contain; transition: transform .35s ease; }
.gd-card:hover .gd-card-art img { transform: scale(1.06); }
.gd-card-n { position: absolute; top: .8rem; left: .8rem; width: 1.8rem; height: 1.8rem; border-radius: 50%; background: #fff; color: var(--gd-c); font-weight: 700; font-size: .82rem; display: grid; place-items: center; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
.gd-card-body { display: flex; flex-direction: column; gap: .35rem; padding: 1rem 1.2rem .4rem; flex: 1; }
.gd-card-body strong { font-size: 1.02rem; line-height: 1.3; }
.gd-card-body span { color: var(--ink-2); font-size: .88rem; line-height: 1.5; }
.gd-card-go { display: flex; align-items: center; gap: .4rem; padding: .5rem 1.2rem 1.05rem; font-size: .86rem; font-weight: 700; color: var(--gd-c); }
.gd-card-go svg { transition: transform .18s; } .gd-card:hover .gd-card-go svg { transform: translateX(4px); }
.gd-none { text-align: center; color: var(--ink-3); padding: 2rem 0; }
.gd-tpl { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: 1.1rem 1.3rem; border: 1px dashed var(--line); border-radius: 18px; }
.gd-tpl strong { margin-right: .5rem; }
/* reader */
.gd-doc { max-width: 56rem; margin: 0 auto; animation: gd-in .28s ease; }
@keyframes gd-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
.gd-back { display: inline-flex; align-items: center; gap: .5rem; border: 1px solid var(--line); background: var(--card); color: var(--ink); border-radius: 999px; padding: .5rem 1.05rem .5rem .85rem; font: inherit; font-size: .88rem; cursor: pointer; margin: 0 0 1rem; }
.gd-back:hover { border-color: var(--gd-c); color: var(--gd-c); }
.gd-doc-head { display: grid; grid-template-columns: minmax(0, 1fr) 270px; align-items: center; gap: 1.5rem; padding: 1.6rem 2rem 0 2rem; border-radius: 26px 26px 0 0; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); border: 1px solid var(--line); border-bottom: 0; overflow: hidden; }
.gd-doc-head img { width: 100%; height: 220px; object-fit: contain; align-self: end; }
.gd-crumb { margin: 0 0 .6rem; font-size: .76rem; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; color: var(--gd-c); }
.gd-crumb span { opacity: .5; margin: 0 .25rem; }
.gd-doc-head h3 { margin: 0 0 .6rem; font-size: clamp(1.5rem, 2.8vw, 2.1rem); line-height: 1.18; color: #2b2118; }
.gd-doc-lede { margin: 0 0 1.4rem; color: #5b4a3d; font-size: 1rem; }
.gd-body { background: var(--card); border: 1px solid var(--line); border-radius: 0 0 26px 26px; padding: 1.8rem 2.1rem 2.1rem; font-size: 1rem; line-height: 1.7; }
.gd-body p { margin: .7rem 0; } .gd-body ul, .gd-body ol { margin: .6rem 0 .6rem 1.5rem; padding: 0; } .gd-body ul { list-style: disc outside; } .gd-body ol { list-style: decimal outside; } .gd-body ul ul { list-style: circle outside; }
.gd-body li { margin: .45rem 0; padding-left: .2rem; } .gd-body li::marker { color: var(--gd-c); font-weight: 700; }
.gd-body h5 { font-size: 1.05rem; font-weight: 700; margin: 1.3rem 0 .3rem; }
.gd-body code { background: var(--paper-2, rgba(0,0,0,.06)); padding: .1rem .38rem; border-radius: 6px; font-size: .86em; word-break: break-word; }
.lif-code { background: #1f201c; color: #ece9df; padding: 1rem 1.2rem; border-radius: 12px; overflow-x: auto; font-size: .84rem; line-height: 1.55; white-space: pre; margin: .9rem 0; }
.lif-note { border-left: 4px solid var(--gd-c); padding: .7rem 1rem; background: color-mix(in srgb, var(--gd-c) 8%, var(--card)); border-radius: 0 12px 12px 0; color: var(--ink-2); }
.lif-table { width: 100%; border-collapse: collapse; margin: .9rem 0; font-size: .9rem; display: block; overflow-x: auto; }
.lif-table th { text-align: left; padding: .6rem .75rem; border-bottom: 2px solid var(--line); background: var(--paper-2, rgba(0,0,0,.04)); white-space: nowrap; }
.lif-table td { padding: .6rem .75rem; border-bottom: 1px solid var(--line); vertical-align: top; min-width: 8rem; }
.gd-pn { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.2rem; }
.gd-pn a { display: flex; align-items: center; gap: .9rem; padding: .8rem 1rem; border: 1px solid var(--line); border-radius: 18px; background: var(--card); text-decoration: none; color: var(--ink); transition: border-color .15s, transform .15s; }
.gd-pn a:hover { border-color: var(--gd-c); transform: translateY(-2px); }
.gd-pn img { flex: none; width: 76px; height: 58px; object-fit: contain; border-radius: 10px; background: color-mix(in srgb, var(--gd-c) 11%, #fbf7ef); }
.gd-pn small { display: block; font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--gd-c); font-weight: 700; }
.gd-pn strong { font-size: .95rem; line-height: 1.3; }
.gd-pn-next { flex-direction: row-reverse; text-align: right; grid-column: 2; }
@media (max-width: 820px) { .gd-hero { grid-template-columns: 1fr; padding: 1.5rem; } .gd-hero-art { order: -1; max-height: 160px; justify-self: center; } .gd-doc-head { grid-template-columns: 1fr; padding: 1.3rem 1.3rem 0; } .gd-doc-head img { order: -1; height: 160px; } .gd-body { padding: 1.3rem; } .gd-pn { grid-template-columns: 1fr; } .gd-pn-next { grid-column: auto; } }
@media (prefers-reduced-motion: reduce) { .gd-card, .gd-card-art img, .gd-doc { transition: none; animation: none; } }
CSS . "</style>";
        }
        echo "<script>" . <<<'JS'
(function () {
  function init(root) {
    var list = root.querySelector('.gd-list');
    var docs = root.querySelectorAll('.gd-doc');
    var cat = 'all';
    function top() { var t = root.closest('.tab-content') || root; try { t.scrollIntoView({ block: 'start' }); } catch (e) {} var m = document.querySelector('main, .t-main'); if (m && m.scrollTo) m.scrollTo({ top: 0 }); window.scrollTo(0, 0); }
    function show(id) {
      var d = root.querySelector('[data-gd-doc="' + id + '"]'); if (!d) return false;
      docs.forEach(function (x) { x.hidden = true; });
      list.hidden = true; d.hidden = false; top();
      return true;
    }
    function back() { docs.forEach(function (x) { x.hidden = true; }); list.hidden = false; top(); }
    function apply() {
      var q = (root.querySelector('[data-gd-search]').value || '').trim().toLowerCase(), any = false;
      root.querySelectorAll('[data-gd-sec]').forEach(function (s) {
        var okCat = cat === 'all' || s.getAttribute('data-cat') === cat, n = 0;
        s.querySelectorAll('.gd-card').forEach(function (c) { var ok = !q || c.getAttribute('data-text').indexOf(q) > -1; c.hidden = !ok; if (ok) n++; });
        s.hidden = !okCat || n === 0; if (!s.hidden) any = true;
      });
      root.querySelector('.gd-none').hidden = any;
    }
    root.addEventListener('click', function (e) {
      var o = e.target.closest('[data-gd-open]'); if (o && root.contains(o)) { e.preventDefault(); show(o.getAttribute('data-gd-open')); return; }
      if (e.target.closest('[data-gd-back]')) { e.preventDefault(); back(); return; }
      var c = e.target.closest('[data-gd-cat]'); if (c) { cat = c.getAttribute('data-gd-cat'); root.querySelectorAll('[data-gd-cat]').forEach(function (x) { x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); }); apply(); }
    });
    root.querySelector('[data-gd-search]').addEventListener('input', apply);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && list.hidden && root.offsetParent !== null) back(); });
    root.gdShow = show; root.gdBack = back;
  }
  document.querySelectorAll('[data-gd]:not([data-gd-ready])').forEach(function (r) { r.setAttribute('data-gd-ready', '1'); init(r); });
  if (window.openGuide) return;
  // From elsewhere in the dashboard: open the guides tab on a given card (used by the import forms and the questions tab).
  window.openGuide = function (id, tab) {
    var root = document.querySelector('[data-gd-main]'); if (!root) return;
    var fn = root.getAttribute('data-gd-switchfn'), arg = root.getAttribute('data-gd-switcharg');
    if (fn && typeof window[fn] === 'function') window[fn](arg);
    root.gdBack(); if (id) root.gdShow(id);
  };
  window.openLiveImportFaq = window.openGuide;
})();
JS . "</script>";
    }
}
