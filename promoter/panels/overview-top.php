<?php /** Rebuilt top of the "Pilotage" page. Needs $PI (strings), $h, $firstName, $todayStr, $awaitingCerts. */ ?>
    <div class="pm-head pi-hero">
      <div>
        <p class="pi-kicker"><?= $h($PI['cmd_kicker']) ?></p>
        <h1><?= $h(str_replace(':name', $firstName, $PI['cmd_hello'])) ?></h1>
        <p><?= $h($todayStr) ?> · <span id="pm-updated"></span></p>
      </div>
      <div class="pi-range" role="group" aria-label="<?= $h($PI['range']) ?>">
        <button type="button" data-range="7" aria-pressed="false"><?= $h($PI['r7']) ?></button>
        <button type="button" data-range="30" aria-pressed="true"><?= $h($PI['r30']) ?></button>
        <button type="button" data-range="90" aria-pressed="false"><?= $h($PI['r90']) ?></button>
      </div>
    </div>

    <div class="pi-quick">
      <button type="button" class="btn btn-primary" data-qr-open><?= $PI_ICO['qr'] ?><?= $h($PI['qa_invite']) ?></button>
      <button type="button" class="btn btn-ghost" data-goto="tab-communications"><?= $h($PI['qa_write']) ?></button>
      <button type="button" class="btn btn-ghost" data-goto="tab-certificates"><?= $h($PI['qa_issue']) ?><?php if (count($awaitingCerts) > 0): ?><span class="pm-count num"><?= count($awaitingCerts) ?></span><?php endif; ?></button>
      <button type="button" class="btn btn-ghost" data-goto="tab-academy"><?= $h($PI['qa_module']) ?></button>
    </div>

    <section id="pm-kpis" class="pi-kpis" aria-live="polite" aria-label="KPI">
      <?php for ($i = 0; $i < 7; $i++): ?><article class="pi-kpi is-skel" style="--i:<?= $i ?>"><p class="pi-kpi-l">&nbsp;</p><p class="pi-kpi-v">&nbsp;</p><div class="pi-kpi-f"></div></article><?php endfor; ?>
    </section>

    <div class="pi-grid2">
      <section class="pi-card" aria-labelledby="pi-trend-h">
        <header><h2 id="pi-trend-h"><?= $h($PI['chart_h']) ?></h2><p><?= $h($PI['chart_p']) ?></p></header>
        <div id="pm-trend" class="pi-trend"><div class="pi-skel-block"></div></div>
      </section>
      <aside class="pi-card" aria-labelledby="pi-recs-h">
        <header><h2 id="pi-recs-h"><?= $h($PI['recs_h']) ?></h2></header>
        <div id="pm-recs" class="pi-recs"><div class="pi-skel-block is-short"></div></div>
      </aside>
    </div>

    <section class="pi-card pi-mini" aria-labelledby="pi-mf-h">
      <header>
        <div><h2 id="pi-mf-h"><?= $h($PI['funnel_mini_h']) ?></h2><p><?= $h($PI['funnel_mini_p']) ?></p></div>
        <button type="button" class="link" data-goto="tab-growth"><?= $h($PI['see_funnel']) ?></button>
      </header>
      <div id="pm-funnel-mini" class="pi-mfs"></div>
    </section>

    <header class="pi-divider"><h2><?= $h($PI['decisions_h']) ?></h2></header>
