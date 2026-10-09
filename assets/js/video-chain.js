/* Lesson videos taken one at a time, and Daniel's little celebration between lessons.
   window.SVLottie.ready(cb)            loads lottie-web on demand
   window.VideoChain.mount(el, videos, ctx)
   window.DanielMoment.show({ title, text, ms })  -> Promise resolved once the card is gone */
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const el = (tag, cls, html) => { const n = document.createElement(tag); if (cls) n.className = cls; if (html != null) n.innerHTML = html; return n; };
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ── lottie, on demand ─────────────────────────────────── */
  const SVLottie = (() => {
    let loading = null;
    function ready(cb) {
      if (window.lottie) return cb(window.lottie);
      if (!loading) {
        loading = new Promise((res, rej) => {
          const s = document.createElement('script');
          s.src = 'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie_light.min.js';
          s.onload = () => res(window.lottie); s.onerror = () => { loading = null; rej(); };
          document.head.appendChild(s);
        });
      }
      loading.then(cb).catch(() => { });
    }
    return { ready };
  })();

  /* ── YouTube iframe API (shared) ───────────────────────── */
  const yt = (() => {
    let p = null;
    return () => {
      if (window.YT && window.YT.Player) return Promise.resolve();
      if (p) return p;
      p = new Promise(res => {
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => { if (typeof prev === 'function') prev(); res(); };
        if (!document.getElementById('yt-iframe-api')) {
          const t = document.createElement('script'); t.id = 'yt-iframe-api'; t.src = 'https://www.youtube.com/iframe_api'; document.head.appendChild(t);
        }
      });
      return p;
    };
  })();

  function youtubeId(url) {
    const m = String(url).match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/))([\w-]{11})/i);
    return m ? m[1] : '';
  }
  const isFile = (url) => /\.(mp4|webm|ogg|m4v|mov)(\?|#|$)/i.test(url);

  /* ── the chain ─────────────────────────────────────────── */
  function mount(host, videos, ctx) {
    const T = ctx.t || ((k) => k);
    host.innerHTML = '';
    host.className = 'vc';
    const total = videos.length;
    const state = videos.map(v => ({ ...v }));
    let idx = Math.max(0, state.findIndex(v => v.current));
    if (ctx.review || idx < 0) idx = 0;
    let stopPlayer = null, destroyed = false, busy = false;

    const head = el('div', 'vc-head');
    const count = el('p', 'vc-count');
    const steps = el('ol', 'vc-steps');
    head.append(count, steps);
    const stageWrap = el('div', 'vc-stage-wrap');
    const stage = el('div', 'vc-stage');
    stageWrap.appendChild(stage);
    const note = el('p', 'vc-note');
    note.setAttribute('role', 'status');
    host.append(head, stageWrap, note);

    host._vcDestroy = () => { destroyed = true; if (stopPlayer) stopPlayer(); };

    function paintHead() {
      const doneN = state.filter(v => v.done).length;
      count.innerHTML = total > 1
        ? T('vc_n', { n: idx + 1, t: total })
        : '';
      head.classList.toggle('is-single', total <= 1);
      steps.innerHTML = '';
      state.forEach((v, i) => {
        const li = el('li');
        const b = el('button'); b.type = 'button';
        const cls = v.done ? 'is-done' : (v.locked ? 'is-locked' : 'is-open');
        b.className = 'vc-step ' + cls + (i === idx ? ' is-active' : '');
        b.disabled = !!v.locked;
        b.setAttribute('aria-label', (v.label || T('vc_video', { n: i + 1 })) + (v.done ? ' — ' + T('vc_done') : v.locked ? ' — ' + T('vc_locked') : ''));
        b.innerHTML = '<span class="vc-step-n">' + (v.done ? '✓' : v.locked ? '🔒' : (i + 1)) + '</span><span class="vc-step-t">' + esc(v.label || T('vc_video', { n: i + 1 })) + '</span>';
        b.onclick = () => { if (!v.locked && i !== idx && !busy) go(i, i > idx ? 1 : -1); };
        li.appendChild(b); steps.appendChild(li);
      });
      note.textContent = ctx.review || state[idx].done ? '' : (idx < total - 1 ? T('vc_hint_next') : T('vc_hint_last'));
      note.className = 'vc-note';
      host.dataset.progress = doneN + '/' + total;
    }

    function setNote(text, bad) { note.textContent = text; note.className = 'vc-note' + (bad ? ' is-bad' : ''); }

    function go(i, dir) {
      if (destroyed) return;
      busy = true;
      stage.classList.remove('vc-in-r', 'vc-in-l');
      stage.classList.add(dir >= 0 ? 'vc-out-l' : 'vc-out-r');
      setTimeout(() => {
        if (destroyed) return;
        if (stopPlayer) { stopPlayer(); stopPlayer = null; }
        idx = i;
        stage.className = 'vc-stage ' + (dir >= 0 ? 'vc-in-r' : 'vc-in-l');
        paintHead();
        play(state[idx]);
        busy = false;
      }, reduce ? 0 : 260);
    }

    function finished(v) {
      v.done = true;
      const nextI = state.findIndex((x, k) => k > idx && !x.done);
      if (nextI >= 0) state[nextI].locked = false;
      paintHead();
      const veil = el('div', 'vc-veil', '<span class="vc-veil-ic">✓</span><b>' + esc(T('vc_finished', { n: idx + 1 })) + '</b>');
      stageWrap.appendChild(veil);
      const allDone = state.every(x => x.done);
      setTimeout(() => {
        veil.remove();
        if (destroyed) return;
        if (nextI >= 0) { go(nextI, 1); if (ctx.onVideoDone) ctx.onVideoDone(v, false); }
        else {
          if (ctx.onVideoDone) ctx.onVideoDone(v, true);
          if (allDone && ctx.onAllDone) ctx.onAllDone();
          setNote(T('vc_all'), false); note.classList.add('is-ok');
        }
      }, reduce ? 300 : 1400);
    }

    /* Report completion to the server; the server decides. */
    function complete(v, duration) {
      return ctx.post('complete', v.key, duration).then(r => {
        if (r && r.success) { finished(v); return true; }
        setNote((r && r.message) || T('vc_fast'), true);
        return false;
      }).catch(() => { setNote(T('vc_net'), true); return false; });
    }

    function play(v) {
      stage.innerHTML = '';
      const review = ctx.review || v.done;
      let started = false;
      const start = () => { if (!started && !review) { started = true; ctx.post('start', v.key, 0).catch(() => { }); } };

      const id = youtubeId(v.url);
      if (id) {
        const slot = el('div', 'vc-frame'); const inner = el('div'); slot.appendChild(inner); stage.appendChild(slot);
        let player = null, timer = null, furthest = 0, watched = 0, lastT = 0, ended = false;
        yt().then(() => {
          if (destroyed || !slot.isConnected) return;
          player = new YT.Player(inner, {
            videoId: id, width: '100%', height: '100%',
            playerVars: { rel: 0, modestbranding: 1, playsinline: 1, iv_load_policy: 3, disablekb: review ? 0 : 1 },
            events: {
              onStateChange: (e) => {
                if (e.data === YT.PlayerState.PLAYING) { start(); ended = false; }
                if (e.data === YT.PlayerState.ENDED && !ended) {
                  ended = true;
                  if (review) return;
                  const dur = player.getDuration() || 0;
                  if (dur > 0 && watched < dur * 0.8) { setNote(T('vc_skipped'), true); player.seekTo(Math.max(0, furthest - 5), true); return; }
                  complete(v, Math.round(dur));
                }
              },
            },
          });
          timer = setInterval(() => {
            if (!player || !player.getCurrentTime) return;
            const st = player.getPlayerState && player.getPlayerState();
            const t = player.getCurrentTime();
            if (st === 1) {
              if (!review && t > furthest + 3) { player.seekTo(furthest, true); setNote(T('vc_noskip'), true); return; }
              if (t >= lastT && t - lastT < 2) watched += (t - lastT);
              furthest = Math.max(furthest, t);
            }
            lastT = t;
          }, 500);
        });
        stopPlayer = () => { clearInterval(timer); try { player && player.destroy(); } catch (e) { } };
        return;
      }

      if (isFile(v.url)) {
        const slot = el('div', 'vc-frame is-file');
        const vid = document.createElement('video');
        vid.src = v.url; vid.controls = true; vid.playsInline = true; vid.preload = 'metadata';
        vid.setAttribute('controlsList', 'nodownload noplaybackrate');
        slot.appendChild(vid); stage.appendChild(slot);
        let furthest = 0, watched = 0, last = 0, ended = false;
        vid.addEventListener('play', start);
        vid.addEventListener('timeupdate', () => {
          const t = vid.currentTime;
          if (!review && !vid.seeking && t >= last && t - last < 2) watched += (t - last);
          last = t; furthest = Math.max(furthest, t);
        });
        vid.addEventListener('seeking', () => { if (!review && vid.currentTime > furthest + 3) { vid.currentTime = furthest; setNote(T('vc_noskip'), true); } });
        vid.addEventListener('ended', () => {
          if (review || ended) return; ended = true;
          const dur = vid.duration || 0;
          if (dur > 0 && watched < dur * 0.8) { setNote(T('vc_skipped'), true); ended = false; vid.currentTime = Math.max(0, furthest - 5); return; }
          complete(v, Math.round(dur));
        });
        stopPlayer = () => { try { vid.pause(); vid.removeAttribute('src'); vid.load(); } catch (e) { } };
        return;
      }

      // Any other link: shown as it is, with a button that opens after a minimum time
      const slot = el('div', 'vc-frame');
      const fr = document.createElement('iframe');
      fr.src = v.url; fr.title = v.label || ''; fr.allowFullscreen = true; fr.loading = 'lazy';
      slot.appendChild(fr); stage.appendChild(slot);
      if (!review) {
        start();
        const wait = 25;
        const btn = el('button', 'btn btn-primary vc-confirm'); btn.type = 'button'; btn.disabled = true;
        let left = wait; const tick = () => { btn.textContent = left > 0 ? T('vc_confirm_wait', { s: left }) : T('vc_confirm'); };
        tick();
        const iv = setInterval(() => { left--; tick(); if (left <= 0) { clearInterval(iv); btn.disabled = false; } }, 1000);
        btn.onclick = () => { btn.disabled = true; complete(v, 0).then(ok => { if (!ok) btn.disabled = false; }); };
        stage.appendChild(btn);
        stopPlayer = () => clearInterval(iv);
      }
    }

    stage.className = 'vc-stage vc-in-r';
    paintHead();
    play(state[idx]);
  }

  /* ── Daniel ────────────────────────────────────────────── */
  const DanielMoment = (() => {
    let active = null;
    function show({ title, text, ms = 2800, cta = '' }) {
      if (active) active.close();
      return new Promise(resolve => {
        const dm = el('div', 'dm');
        dm.setAttribute('role', 'status'); dm.setAttribute('aria-live', 'assertive');
        const conf = Array.from({ length: reduce ? 0 : 22 }, (_, i) => {
          const x = Math.round((Math.random() - .5) * 340), y = -Math.round(120 + Math.random() * 160), r = Math.round(Math.random() * 540 - 270), d = (Math.random() * .25).toFixed(2);
          const c = ['#B5482A', '#D9A23B', '#24402F', '#E27B57'][i % 4];
          return '<i style="--x:' + x + 'px;--y:' + y + 'px;--r:' + r + 'deg;--d:' + d + 's;background:' + c + '"></i>';
        }).join('');
        dm.innerHTML =
          '<div class="dm-card"><div class="dm-conf" aria-hidden="true">' + conf + '</div>' +
          '<div class="dm-av"><img src="/assets/img/daniel.png" alt="" width="92" height="92"><div class="dm-lottie"></div></div>' +
          '<div class="dm-say"><span class="dm-name">Daniel</span><b>' + esc(title) + '</b><span>' + esc(text) + '</span></div>' +
          (cta ? '<button type="button" class="dm-go">' + esc(cta) + '</button>' : '') + '</div>';
        document.body.appendChild(dm);
        requestAnimationFrame(() => dm.classList.add('is-in'));
        SVLottie.ready((lottie) => {
          if (!dm.isConnected) return;
          const holder = dm.querySelector('.dm-lottie');
          const a = lottie.loadAnimation({ container: holder, renderer: 'svg', loop: true, autoplay: !reduce, path: '/assets/anim/daniel.json', rendererSettings: { preserveAspectRatio: 'xMidYMid slice' } });
          a.addEventListener('DOMLoaded', () => { holder.classList.add('is-ready'); const img = dm.querySelector('.dm-av img'); if (img) setTimeout(() => img.remove(), 350); });
        });
        let closed = false, timer = null;
        const close = () => {
          if (closed) return; closed = true; clearTimeout(timer);
          dm.classList.remove('is-in'); dm.classList.add('is-out');
          document.removeEventListener('keydown', onKey, true);
          setTimeout(() => { dm.remove(); if (active && active.dm === dm) active = null; resolve(); }, reduce ? 0 : 320);
        };
        const onKey = (e) => { if (e.key === 'Escape' || e.key === 'Enter') { e.stopPropagation(); close(); } };
        document.addEventListener('keydown', onKey, true);
        dm.addEventListener('click', close);
        timer = setTimeout(close, ms);
        active = { dm, close };
      });
    }
    return { show };
  })();

  window.SVLottie = SVLottie;
  window.VideoChain = { mount, youtubeId };
  window.DanielMoment = DanielMoment;
})();
