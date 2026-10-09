<?php /** "Analyses" page. Needs $PI, $h. */ ?>
  <section id="tab-insights" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head"><div><p class="pi-kicker"><?= $h($PI['nav_insights']) ?></p><h1><?= $h($PI['in_h']) ?></h1><p><?= $h($PI['in_p']) ?></p></div></div>

    <section class="pi-card" aria-labelledby="pi-en-h">
      <header><div><h2 id="pi-en-h"><?= $h($PI['en_h']) ?></h2></div></header>
      <div id="pm-eng" class="pi-stats"><div class="pi-skel-block is-short"></div></div>
    </section>

    <section class="pi-card" aria-labelledby="pi-heat-h">
      <header><div><h2 id="pi-heat-h"><?= $h($PI['heat_h']) ?></h2><p><?= $h($PI['heat_p']) ?></p></div></header>
      <div id="pm-heat"></div>
    </section>

    <section class="pi-card" aria-labelledby="pi-co-h">
      <header><div><h2 id="pi-co-h"><?= $h($PI['co_h']) ?></h2></div></header>
      <div id="pm-courses"></div>
    </section>

    <div class="pi-grid2 is-even">
      <section class="pi-card" aria-labelledby="pi-st-h">
        <header><div><h2 id="pi-st-h"><?= $h($PI['st_h']) ?></h2><p><?= $h($PI['st_p']) ?></p></div></header>
        <div id="pm-stall"></div>
      </section>
      <section class="pi-card" aria-labelledby="pi-lv-h">
        <header><div><h2 id="pi-lv-h"><?= $h($PI['lv_h']) ?></h2></div></header>
        <div id="pm-live"></div>
      </section>
    </div>
  </section>
