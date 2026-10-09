<?php /** "Croissance" page. Needs $PI, $h, $PI_ICO. */ ?>
  <section id="tab-growth" class="tab-content pm-panel" data-panel hidden>
    <div class="pm-head">
      <div><p class="pi-kicker"><?= $h($PI['nav_growth']) ?></p><h1><?= $h($PI['gr_h']) ?></h1><p><?= $h($PI['gr_p']) ?></p></div>
      <div class="pi-range" role="group" aria-label="<?= $h($PI['range']) ?>">
        <button type="button" data-range="7" aria-pressed="false"><?= $h($PI['r7']) ?></button>
        <button type="button" data-range="30" aria-pressed="true"><?= $h($PI['r30']) ?></button>
        <button type="button" data-range="90" aria-pressed="false"><?= $h($PI['r90']) ?></button>
      </div>
    </div>

    <section class="pi-card" aria-labelledby="pi-fn-h">
      <header><div><h2 id="pi-fn-h"><?= $h($PI['fn_h']) ?></h2><p><?= $h($PI['fn_p']) ?></p></div></header>
      <div id="pm-funnel" class="pi-fns"><div class="pi-skel-block"></div></div>
    </section>

    <div>
      <section class="pi-card" aria-labelledby="pi-ch-h">
        <header><div><h2 id="pi-ch-h"><?= $h($PI['ch_h']) ?></h2><p><?= $h($PI['ch_p']) ?></p></div></header>
        <div id="pm-channels"></div>
      </section>
      <section class="pi-card" aria-labelledby="pi-pg-h">
        <header><div><h2 id="pi-pg-h"><?= $h($PI['pg_h']) ?></h2></div></header>
        <div id="pm-pages" class="pi-mfs"></div>
      </section>
    </div>

    <section class="pi-card pi-studio" id="qr-studio" aria-labelledby="pi-qr-h">
      <header><div><h2 id="pi-qr-h"><?= $h($PI['qr_h']) ?></h2><p><?= $h($PI['qr_p']) ?></p></div></header>
      <div class="pi-studio-grid">
        <div class="pi-studio-form">
          <label class="field"><span><?= $h($PI['qr_what']) ?></span>
            <select class="input" id="qr-target">
              <option value="signup"><?= $h($PI['qr_t_signup']) ?></option>
              <option value="home"><?= $h($PI['qr_t_home']) ?></option>
              <option value="course"><?= $h($PI['qr_t_course']) ?></option>
              <option value="live"><?= $h($PI['qr_t_live']) ?></option>
            </select>
          </label>
          <label class="field" data-qr-ref="course" hidden><span><?= $h($PI['qr_t_course']) ?></span><select class="input" id="qr-course"></select></label>
          <label class="field" data-qr-ref="live" hidden><span><?= $h($PI['qr_t_live']) ?></span><select class="input" id="qr-live"></select></label>
          <label class="field"><span><?= $h($PI['qr_name']) ?></span>
            <input class="input" id="qr-name" type="text" maxlength="120" autocomplete="off" placeholder="<?= $h($PI['qr_name_ph']) ?>">
            <small class="note"><?= $h($PI['qr_name_h']) ?></small>
          </label>
          <label class="field"><span><?= $h($PI['qr_link']) ?></span>
            <span class="pi-copy"><input class="input" id="qr-link" type="text" readonly><button type="button" class="btn btn-ghost btn-sm" id="qr-copy"><?= $h($PI['qr_copy']) ?></button></span>
          </label>
          <div class="pi-studio-actions">
            <button type="button" class="btn btn-primary" id="qr-png"><?= $PI_ICO['down'] ?><?= $h($PI['qr_png']) ?></button>
            <button type="button" class="btn btn-ghost" id="qr-svg"><?= $h($PI['qr_svg']) ?></button>
            <button type="button" class="btn btn-ghost" id="qr-print"><?= $PI_ICO['print'] ?><?= $h($PI['qr_print']) ?></button>
            <button type="button" class="btn btn-text" id="qr-save"><?= $h($PI['qr_save']) ?></button>
          </div>
        </div>
        <figure class="pi-studio-preview">
          <div id="qr-preview" class="pi-qr" aria-label="<?= $h($PI['qr_preview']) ?>"></div>
          <figcaption><?= $h($PI['qr_preview']) ?></figcaption>
        </figure>
      </div>
    </section>

    <section class="pi-card" aria-labelledby="pi-cp-h">
      <header><div><h2 id="pi-cp-h"><?= $h($PI['cp_h']) ?></h2></div></header>
      <div id="pm-campaigns" class="pi-cps"></div>
    </section>
  </section>
