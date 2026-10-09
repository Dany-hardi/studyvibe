/* StudyVibe — student space (v2).
   Navigation, lesson reader, study companion, quizzes, exam, profile.
   Strings come from window.SV_T (see locales/student.php, keys prefixed js_). Shared helpers
   (Toast, SessionTimer, PdfViewer, LessonContentGate, ExamTimer, renderMarkdownAndMath, svPost)
   live in app.js. */

/* ───────────────────────── helpers ───────────────────────── */
const SV = window.SV_T || {};
const LANG = window.SV_LANG === 'en' ? 'en' : 'fr';
const LOCALE = LANG === 'en' ? 'en-US' : 'fr-FR';

function T(key, vars) {
    let s = SV[key] !== undefined ? SV[key] : key;
    if (vars) Object.keys(vars).forEach(k => { s = s.split(':' + k).join(String(vars[k])); });
    return s;
}
function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
const escapeHtml = esc;
const escapeHTML = esc;
function $(sel, root) { return (root || document).querySelector(sel); }
function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }
function byId(id) { return document.getElementById(id); }
function csrf() { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; }
function fmtDateTime(d) { try { return new Date(d).toLocaleString(LOCALE, { dateStyle: 'medium', timeStyle: 'short' }); } catch (e) { return String(d); } }
function icon(name, px) {
    const p = {
        check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        x: '<path d="m6 6 12 12M18 6 6 18"/>',
        arrow: '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        lock: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'
    }[name] || '';
    px = px || 16;
    return '<svg class="sd-ic" width="' + px + '" height="' + px + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
}

/* ───────────────────────── dialogs ───────────────────────── */
let lastFocus = null;
function openModal(id) {
    const m = byId(id); if (!m) return;
    lastFocus = document.activeElement;
    m.classList.remove('hidden');
    document.body.classList.add('sd-lock');
    const f = m.querySelector('input:not([type=hidden]), textarea, button.btn-primary, button');
    if (f) setTimeout(() => f.focus({ preventScroll: true }), 30);
}
function closeModal(id) {
    const m = byId(id); if (!m) return;
    m.classList.add('hidden');
    if (!$$('.sd-modal:not(.hidden)').length && byId('study-modal').classList.contains('hidden')) document.body.classList.remove('sd-lock');
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (e) { } }
}
function toggleModal(id) {
    const m = byId(id); if (!m) return;
    m.classList.contains('hidden') ? openModal(id) : closeModal(id);
}
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = $$('.sd-modal:not(.hidden):not(.sd-modal-hard)').pop();
    if (open && open.id !== 'final-exam-modal') { closeModal(open.id); return; }
    const pop = byId('notif-panel-container');
    if (pop && !pop.classList.contains('hidden')) { pop.classList.add('hidden'); return; }
    const rd = byId('study-modal');
    if (rd && !rd.classList.contains('hidden') && !open) {
        if (rd.classList.contains('rd--outline-open') || rd.classList.contains('rd--side-open-sheet')) closeSheets();
    }
});
document.addEventListener('click', (e) => {
    const m = e.target.classList && e.target.classList.contains('sd-modal') ? e.target : null;
    if (m && !m.classList.contains('sd-modal-hard') && m.id !== 'final-exam-modal') closeModal(m.id);
});

/* ───────────────────────── navigation ───────────────────────── */
const TABS = ['home', 'courses', 'evals', 'results', 'certs', 'profile'];
const SUBS = ['mine', 'catalogue', 'library'];
let currentTab = 'home';
let currentSub = 'mine';

function setSub(sub) {
    if (SUBS.indexOf(sub) < 0) sub = 'mine';
    currentSub = sub;
    SUBS.forEach(s => {
        const p = byId('cs-' + s), b = byId('cs-btn-' + s);
        if (p) p.classList.toggle('hidden', s !== sub);
        if (b) { b.setAttribute('aria-selected', s === sub ? 'true' : 'false'); b.classList.toggle('is-on', s === sub); }
    });
}

function switchTab(tab, sub, opts) {
    opts = opts || {};
    // legacy names used by older links
    const legacy = { catalogue: ['courses', 'catalogue'], 'mes-cours': ['courses', 'mine'], bibliotheque: ['courses', 'library'], releve: ['results'], certifications: ['certs'], achievements: ['certs'], 'tele-evaluations': ['evals'], profil: ['profile'] };
    if (legacy[tab]) { sub = sub || legacy[tab][1]; tab = legacy[tab][0]; }
    if (TABS.indexOf(tab) < 0) tab = 'home';
    currentTab = tab;
    TABS.forEach(t => { const p = byId('tab-' + t); if (p) p.classList.toggle('hidden', t !== tab); });
    $$('[data-nav]').forEach(b => {
        const on = b.getAttribute('data-nav') === tab && !b.matches('.sd-logo, .sd-topbar-logo');
        if (b.classList.contains('sd-nav') || b.classList.contains('sd-me')) { if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); }
    });
    if (tab === 'courses') setSub(sub || currentSub);
    if (!opts.noHash) {
        const h = '#' + tab + (tab === 'courses' && sub ? '/' + sub : '');
        if (location.hash !== h) history.pushState(null, '', h);
    }
    window.scrollTo({ top: 0 });
    if (!opts.noFocus) { const h1 = $('#tab-' + tab + ' h1'); if (h1) { h1.setAttribute('tabindex', '-1'); h1.focus({ preventScroll: true }); } }
}

function routeFromHash() {
    const h = (location.hash || '').replace('#', '');
    if (!h) return false;
    const [t, s] = h.split('/');
    if (TABS.indexOf(t) < 0 && !['catalogue', 'mes-cours', 'bibliotheque', 'releve', 'certifications', 'achievements', 'tele-evaluations', 'profil'].includes(t)) return false;
    switchTab(t, s, { noHash: true, noFocus: true });
    return true;
}

document.addEventListener('click', (e) => {
    const nav = e.target.closest('[data-nav]');
    if (nav) { e.preventDefault(); switchTab(nav.getAttribute('data-nav'), nav.getAttribute('data-sub') || undefined); return; }
    const sub = e.target.closest('[data-sub]');
    if (sub) { e.preventDefault(); if (currentTab !== 'courses') switchTab('courses', sub.getAttribute('data-sub')); else { setSub(sub.getAttribute('data-sub')); history.pushState(null, '', '#courses/' + currentSub); } return; }
    const st = e.target.closest('[data-study]');
    if (st) { studyCourse(parseInt(st.getAttribute('data-study'), 10)); return; }
    const ex = e.target.closest('[data-start-exam]');
    if (ex) { startFinalExam(parseInt(ex.getAttribute('data-start-exam'), 10), ex.getAttribute('data-title') || ''); return; }
    const lib = e.target.closest('[data-lib-text]');
    if (lib) { try { viewLibraryTextModal(JSON.parse(lib.getAttribute('data-lib-text'))); } catch (err) { } return; }
    const lg = e.target.closest('[data-lang]');
    if (lg) { e.preventDefault(); sdChangeLanguage(lg.getAttribute('data-lang')); return; }
});
window.addEventListener('popstate', () => { if (!routeFromHash()) switchTab('home', undefined, { noHash: true, noFocus: true }); });

function sdChangeLanguage(lang) {
    fetch('/api/set-language.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: lang }) })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else Toast.error(T('err_lang')); })
        .catch(() => Toast.error(T('err_network')));
}
window.changeLanguage = sdChangeLanguage;

/* ───────────────────────── catalogue search ───────────────────────── */
function initCatalogueSearch() {
    const input = byId('course-search'), grid = byId('course-grid'), none = byId('course-nores');
    if (!input || !grid) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        let shown = 0;
        $$('[data-course-card]', grid).forEach(c => {
            const hit = q === '' || (c.dataset.search || '').includes(q);
            c.classList.toggle('hidden', !hit);
            if (hit) shown++;
        });
        if (none) none.classList.toggle('hidden', shown > 0);
    });
}

/* ───────────────────────── figures, enrolment, profile ───────────────────────── */
function refreshDashboard() {
    fetch('/student/get-stats.php').then(r => r.json()).then(d => {
        if (!d.success) return;
        const time = d.study_time.hours + ' h ' + String(d.study_time.minutes).padStart(2, '0');
        $$('[data-kpi="completed"]').forEach(el => el.textContent = d.completed_courses);
        $$('[data-kpi="score"]').forEach(el => el.textContent = d.avg_score);
        $$('[data-kpi="time"], [data-kpi="time2"]').forEach(el => el.textContent = time);
        $$('[data-kpi="certs"]').forEach(el => el.textContent = d.certificates);
    }).catch(() => { });
}

function attemptEnroll(courseId, needsKey) {
    if (needsKey) {
        byId('enroll-course-id').value = courseId;
        byId('enroll-key-input').value = '';
        byId('enroll-error').classList.add('hidden');
        openModal('enroll-modal');
    } else {
        submitEnrollment(courseId, null);
    }
}

function submitEnrollment(courseId, key, done) {
    const fd = new FormData();
    fd.append('course_id', courseId);
    fd.append('csrf_token', csrf());
    if (key) fd.append('enrollment_key', key);
    fetch('/student/enroll.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Toast.success(T('enrolled_ok'));
                if (!byId('enroll-modal').classList.contains('hidden')) closeModal('enroll-modal');
                refreshDashboard();
                setTimeout(() => { location.hash = '#courses/mine'; location.reload(); }, 700);
            } else if (key) {
                const er = byId('enroll-error');
                er.textContent = d.message || T('key_wrong');
                er.classList.remove('hidden');
            } else {
                Toast.error(d.message || T('enrol_err'));
            }
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message))
        .finally(() => { if (done) done(); });
}

const enrollForm = byId('enroll-form');
if (enrollForm) {
    enrollForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const key = byId('enroll-key-input').value.trim();
        const er = byId('enroll-error');
        if (key === '') { er.textContent = T('key_wrong'); er.classList.remove('hidden'); return; }
        er.classList.add('hidden');
        const btn = enrollForm.querySelector('button[type=submit]');
        if (btn) btn.disabled = true;
        submitEnrollment(byId('enroll-course-id').value, key, () => { if (btn) btn.disabled = false; });
    });
}

/* Matricule rules (mirrors lib/Matricule.php, the server has the last word):
   two digits for the year of joining (never in the future), one letter, three or four digits. */
function matriculeCode(raw) {
    const v = String(raw || '').replace(/\s+/g, '').toUpperCase();
    if (!v) return 'mat_empty';
    const m = /^(\d{2})([A-Z])(\d{3,4})$/.exec(v);
    if (!m) return 'mat_format';
    return 2000 + parseInt(m[1], 10) > new Date().getFullYear() ? 'mat_future' : '';
}
function matError(code, fallback) { return T(code) !== code ? T(code) : (fallback || T('err_generic')); }
function showMatError(boxId, text) { const er = byId(boxId); if (er) { er.textContent = text; er.classList.remove('hidden'); } }
function clearMatError() { ['quick-matricule-error', 'profile-matricule-error'].forEach(id => { const er = byId(id); if (er) er.classList.add('hidden'); }); }

function updateProfileName() {
    const name = byId('profile-name').value.trim();
    const mat = (byId('profile-matricule') || {}).value || '';
    const status = byId('profile-status');
    if (name === '') return;
    const bad = matriculeCode(mat);
    if (bad) { showMatError('profile-matricule-error', matError(bad)); const pm = byId('profile-matricule'); if (pm) pm.focus(); return; }
    clearMatError();
    const fd = new FormData();
    fd.append('name', name);
    fd.append('matricule', mat.trim());
    fetch('/student/update-profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                status.classList.remove('hidden');
                $$('.id-student-name').forEach(el => el.textContent = name);
                const alert = byId('matricule-alert-modal');
                if (alert && mat.trim() !== '') { alert.remove(); document.body.classList.remove('sd-lock'); }
                setTimeout(() => status.classList.add('hidden'), 3000);
            } else if (d.code && d.code.indexOf('matricule_') === 0) {
                showMatError('profile-matricule-error', matError('mat_' + d.code.slice(10), d.message));
            } else Toast.error(d.message || T('err_generic'));
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

function uploadAvatar() {
    const file = byId('avatar-input').files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('avatar', file);
    fetch('/student/update-profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const src = '/download.php?type=avatar&file=' + encodeURIComponent(d.avatar_path);
                byId('profile-avatar-preview').src = src;
                byId('header-avatar').src = src;
                $$('.sd-avatar-sm').forEach(i => i.src = src);
                Toast.success(T('avatar_ok'));
            } else Toast.error(d.message || T('err_generic'));
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

function submitQuickMatricule(e) {
    e.preventDefault();
    const val = byId('quick-matricule-input').value.trim();
    const bad = matriculeCode(val);
    if (bad) { showMatError('quick-matricule-error', matError(bad)); return; }
    clearMatError();
    const fd = new FormData();
    fd.append('matricule', val);
    fetch('/student/update-profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const m = byId('matricule-alert-modal'); if (m) m.remove();
                if (!$$('.sd-modal:not(.hidden)').length) document.body.classList.remove('sd-lock');
                const pm = byId('profile-matricule'); if (pm) pm.value = val.toUpperCase();
                const am = byId('assignment-student-matricule'); if (am && !am.value) am.value = val.toUpperCase();
                Toast.success(T('mat_ok'));
            } else {
                const code = d.code && d.code.indexOf('matricule_') === 0 ? 'mat_' + d.code.slice(10) : '';
                showMatError('quick-matricule-error', code ? matError(code, d.message) : (d.message || T('err_generic')));
            }
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

/* ───────────────────────── notifications ───────────────────────── */
function timeAgo(dateString) {
    const date = new Date(String(dateString).replace(' ', 'T'));
    const s = Math.floor((Date.now() - date) / 1000);
    if (isNaN(s)) return '';
    if (s < 60) return T('ago_now');
    const m = Math.floor(s / 60); if (m < 60) return T('ago_min', { n: m });
    const h = Math.floor(m / 60); if (h < 24) return T('ago_hour', { n: h });
    const d = Math.floor(h / 24); if (d === 1) return T('ago_yesterday');
    return date.toLocaleDateString(LOCALE, { day: 'numeric', month: 'short' });
}

async function markAllNotificationsRead(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    try {
        const r = await fetch('/student/mark-all-read.php', { method: 'POST' });
        const d = await r.json();
        if (d.success) loadNotifications();
    } catch (err) { /* ignore */ }
}

function loadNotifications() {
    fetch('/student/get-notifications.php').then(r => r.json()).then(d => {
        if (!d.success) return;
        $$('.notif-count').forEach(b => {
            if (d.unread_count > 0) { b.textContent = d.unread_count > 9 ? '9+' : d.unread_count; b.classList.remove('hidden'); } else b.classList.add('hidden');
        });
        const panel = byId('notif-panel');
        if (!panel) return;
        panel.innerHTML = d.notifications.length
            ? d.notifications.map(n => '<a href="' + esc(n.link || '#') + '" class="sd-notif' + (n.is_read == 0 ? ' is-unread' : '') + '">'
                + '<span class="sd-notif-dot" aria-hidden="true"></span>'
                + '<span><strong>' + esc(n.title) + '</strong>' + (n.body ? '<span class="sd-notif-body">' + esc(n.body) + '</span>' : '')
                + '<span class="sd-notif-time num">' + esc(timeAgo(n.created_at)) + '</span></span></a>').join('')
            : '<p class="sd-pop-empty"><strong>' + esc(T('notif_empty_t')) + '</strong><span>' + esc(T('notif_empty_p')) + '</span></p>';
    }).catch(() => { });
}

function toggleNotifs(btn) {
    const pop = byId('notif-panel-container');
    pop.classList.toggle('hidden');
    pop.classList.toggle('from-bar', btn && btn.id === 'notif-btn-m');
    if (!pop.classList.contains('hidden')) loadNotifications();
}
document.addEventListener('click', (e) => {
    const b = e.target.closest('#notif-btn, #notif-btn-m');
    const pop = byId('notif-panel-container');
    if (b) { e.stopPropagation(); toggleNotifs(b); return; }
    if (pop && !pop.classList.contains('hidden') && !pop.contains(e.target)) pop.classList.add('hidden');
});

/* ───────────────────────── library text ───────────────────────── */
function renderRich(el, md) {
    el.innerHTML = renderMarkdownAndMath(md || '');
    if (typeof renderMathInElement === 'function') {
        renderMathInElement(el, {
            delimiters: [{ left: '$$', right: '$$', display: true }, { left: '$', right: '$', display: false }, { left: '\\(', right: '\\)', display: false }, { left: '\\[', right: '\\]', display: true }],
            throwOnError: false
        });
    }
}
function viewLibraryTextModal(item) {
    byId('lib-modal-title').textContent = item.title || '';
    renderRich(byId('lib-modal-body'), item.md || '');
    openModal('lib-modal');
}

/* ═════════════════════════ THE READER ═════════════════════════ */
let currentLessonId = 0;
let studyCourseIdGlobal = 0;
let currentCourseLessons = [];
let currentNextLesson = null;
let pendingLessonQuiz = null;
let lessonContentConsumed = false;
let currentLessonContentType = '';
let readerOpener = null;

function readerEl() { return byId('study-modal'); }
function isWide() { return window.matchMedia('(min-width: 1180px)').matches; }
function isMid() { return window.matchMedia('(min-width: 961px)').matches; }

function setOutline(open) {
    const rd = readerEl();
    rd.classList.toggle('rd--outline-open', open);
    byId('btn-outline').setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && !isMid()) rd.classList.add('rd--scrim');
    else if (!rd.classList.contains('rd--side-open-sheet')) rd.classList.remove('rd--scrim');
}
function toggleOutline() { setOutline(!readerEl().classList.contains('rd--outline-open')); }

function setCompanion(open) {
    const rd = readerEl(), side = byId('study-companion-panel');
    side.classList.toggle('hidden', !open);
    rd.classList.toggle('rd--side-open', open);
    byId('btn-companion').setAttribute('aria-expanded', open ? 'true' : 'false');
    const asSheet = open && !isMid();
    rd.classList.toggle('rd--side-open-sheet', asSheet);
    if (asSheet) rd.classList.add('rd--scrim');
    else if (!(rd.classList.contains('rd--outline-open') && !isMid())) rd.classList.remove('rd--scrim');
    if (open && isWide()) { const i = byId('ai-chat-input'); if (i && !byId('companion-tab-ai').classList.contains('hidden')) i.focus({ preventScroll: true }); }
}
function toggleCompanion() { setCompanion(byId('study-companion-panel').classList.contains('hidden')); }
function closeSheets() {
    const rd = readerEl();
    if (!isMid()) setOutline(false);
    if (!isWide()) setCompanion(false);
    rd.classList.remove('rd--scrim');
}

function switchCompanionTab(tab) {
    ['ai', 'notes', 'qa'].forEach(t => {
        const b = byId('companion-btn-' + t), p = byId('companion-tab-' + t);
        if (b) { b.classList.toggle('is-on', t === tab); b.setAttribute('aria-selected', t === tab ? 'true' : 'false'); }
        if (p) p.classList.toggle('hidden', t !== tab);
    });
}

function openReaderShell() {
    const rd = readerEl();
    readerOpener = document.activeElement;
    rd.classList.remove('hidden');
    document.body.classList.add('sd-lock');
    if (rd.dataset.init !== '1') {
        rd.dataset.init = '1';
        setOutline(isMid());
        setCompanion(false);
        byId('study-viewer-content').addEventListener('scroll', updateReadProgress, { passive: true });
    }
}

function updateReadProgress() {
    const m = byId('study-viewer-content');
    const max = m.scrollHeight - m.clientHeight;
    byId('rd-progress-bar').style.width = (max > 4 ? Math.min(100, Math.round((m.scrollTop / max) * 100)) : 0) + '%';
}

/* Builds the left outline from the course payload. Lessons come in order: a lesson opens only once the one before it is done. */
function renderOutline(data) {
    const allLessons = [];
    let doneCount = 0, totalCount = 0;
    const container = byId('study-chapters-container');
    container.innerHTML = '';

    data.chapters.forEach(ch => {
        const sec = document.createElement('section');
        sec.className = 'rd-chap';
        const h = document.createElement('h3');
        h.textContent = ch.title;
        sec.appendChild(h);
        const ul = document.createElement('ul');
        ch.lessons.forEach(les => {
            const expired = les.quiz_deadline && parseInt(les.completed, 10) === 0 && new Date() > new Date(les.quiz_deadline);
            const done = parseInt(les.completed, 10) === 1;
            const locked = !done && parseInt(les.locked, 10) === 1;
            totalCount++;
            if (done) doneCount++;
            if (!expired) allLessons.push(les);
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rd-les' + (done ? ' is-done' : '') + (expired ? ' is-expired' : '') + (locked ? ' is-locked' : '') + (currentLessonId && currentLessonId == les.id ? ' is-current' : '');
            btn.dataset.lessonId = les.id;
            if (locked) btn.setAttribute('aria-disabled', 'true');
            if (currentLessonId && currentLessonId == les.id) btn.setAttribute('aria-current', 'true');
            btn.innerHTML = '<span class="rd-les-mark" aria-hidden="true">' + (done ? icon('check', 12) : (locked ? icon('lock', 11) : '')) + '</span>'
                + '<span class="rd-les-t"></span>';
            btn.querySelector('.rd-les-t').textContent = les.title + (expired ? ' (' + T('expired') + ')' : '');
            if (done) { const sp = document.createElement('span'); sp.className = 'sr-only'; sp.textContent = ' — ' + T('lesson_done'); btn.appendChild(sp); }
            if (locked) { const sp = document.createElement('span'); sp.className = 'sr-only'; sp.textContent = ' — ' + T('lesson_locked_sr'); btn.appendChild(sp); }
            btn.onclick = expired ? () => Toast.error(T('lesson_expired'))
                : locked ? () => { Toast.info(T('lesson_locked', { title: les.blocker || '' })); btn.classList.remove('is-shake'); void btn.offsetWidth; btn.classList.add('is-shake'); }
                : () => { if (currentLessonId == les.id) { if (!isMid()) setOutline(false); return; } swapToLesson(les.id); if (!isMid()) setOutline(false); };
            li.appendChild(btn);
            ul.appendChild(li);
        });
        sec.appendChild(ul);
        container.appendChild(sec);
    });

    currentCourseLessons = allLessons;
    byId('rd-outline-count').textContent = T('lessons_done', { done: doneCount, total: totalCount });
    byId('rd-course-bar').style.width = (totalCount ? Math.round(doneCount / totalCount * 100) : 0) + '%';
    return { allLessons, doneCount, totalCount };
}

function fetchCourse(courseId) {
    return fetch('/student/get-course-details.php?course_id=' + courseId).then(r => r.json());
}

/* Refresh the outline (ticks, locks, counters) without touching the lesson being read. */
function refreshOutline() {
    return fetchCourse(studyCourseIdGlobal).then(data => {
        if (data.success) {
            renderOutline(data);
            const i = currentCourseLessons.findIndex(l => l.id == currentLessonId);
            currentNextLesson = (i !== -1 && i + 1 < currentCourseLessons.length) ? currentCourseLessons[i + 1] : null;
        }
        return data;
    }).catch(() => ({ success: false }));
}

function firstOpenLesson() {
    return currentCourseLessons.find(l => parseInt(l.completed, 10) === 0 && parseInt(l.locked, 10) !== 1)
        || currentCourseLessons.filter(l => parseInt(l.locked, 10) !== 1).pop() || null;
}

function studyCourse(courseId, stayOnLessonId) {
    stayOnLessonId = stayOnLessonId || null;
    studyCourseIdGlobal = courseId;
    fetchCourse(courseId)
        .then(data => {
            if (!data.success) { Toast.error(data.message || T('err_generic')); return; }
            byId('study-course-module').textContent = data.course.module_title;
            byId('study-course-title').textContent = data.course.title;
            const out = renderOutline(data);
            openReaderShell();

            if (stayOnLessonId) {
                const i = currentCourseLessons.findIndex(l => l.id == stayOnLessonId);
                currentNextLesson = (i !== -1 && i + 1 < currentCourseLessons.length) ? currentCourseLessons[i + 1] : null;
                loadLesson(stayOnLessonId);
                return;
            }
            let target = null;
            if (data.course.last_lesson_id) {
                const li = currentCourseLessons.findIndex(l => l.id == data.course.last_lesson_id);
                if (li !== -1) {
                    const last = currentCourseLessons[li];
                    if (parseInt(last.completed, 10) === 0) target = last;
                    else if (li + 1 < currentCourseLessons.length) target = currentCourseLessons[li + 1];
                    else target = last;
                }
            }
            if (target && parseInt(target.locked, 10) === 1 && parseInt(target.completed, 10) === 0) target = null;
            if (!target) target = firstOpenLesson();
            if (target) { loadLesson(target.id); return; }
            byId('lesson-viewer-header').classList.add('hidden');
            byId('study-media-container').innerHTML = '<p class="sd-empty">' + esc(data.chapters.length && data.chapters[0].lessons.length ? T('all_expired') : T('no_lessons')) + '</p>';
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

/* Moves to another lesson with a short slide, so changing lesson never feels like a page jump. */
function swapToLesson(lessonId) {
    const art = $('.rd-article');
    if (!art || !currentLessonId) { loadLesson(lessonId); return; }
    art.classList.add('is-leaving');
    setTimeout(() => { loadLesson(lessonId); }, matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 280);
}

/* A lesson has just been completed: Daniel says so, the outline updates, then the next lesson opens by itself. */
let advancing = false;
function onLessonCompleted(lessonId) {
    if (advancing) return;
    advancing = true;
    const pos = currentCourseLessons.findIndex(l => l.id == lessonId);
    const n = pos + 1;
    const hello = [T('dn_go_1'), T('dn_go_2'), T('dn_go_3')];
    refreshOutline().then(() => {
        const i = currentCourseLessons.findIndex(l => l.id == lessonId);
        const next = (i !== -1 && i + 1 < currentCourseLessons.length) ? currentCourseLessons[i + 1] : null;
        const moment = next
            ? DanielMoment.show({ title: T('dn_done', { n: n }), text: hello[Math.floor(Math.random() * hello.length)] + ' ' + T('dn_next', { title: next.title }), ms: 2800 })
            : DanielMoment.show({ title: T('dn_course_done'), text: T('dn_course_done_p'), ms: 3600 });
        moment.then(() => {
            advancing = false;
            if (!readerEl() || readerEl().classList.contains('hidden') || currentLessonId != lessonId) return;
            if (next) swapToLesson(next.id);
            else loadLesson(lessonId);
        });
    });
}

function resumeCourse(courseId, lessonId) { studyCourse(courseId, lessonId || null); }

function closeStudyModal() {
    SessionTimer.stop();
    LessonContentGate.reset();
    const media = byId('study-media-container');
    Array.from(media.children).forEach(el => { if (el._pdfCleanup) el._pdfCleanup(); if (el._vcDestroy) el._vcDestroy(); });
    media.innerHTML = '';
    const rd = readerEl();
    rd.classList.remove('sv-pdf-focus-mode', 'rd--scrim');
    rd.classList.add('hidden');
    currentLessonId = 0;
    if (!$$('.sd-modal:not(.hidden)').length) document.body.classList.remove('sd-lock');
    if (readerOpener && readerOpener.focus) { try { readerOpener.focus({ preventScroll: true }); } catch (e) { } }
    refreshDashboard();
}

function markContentConsumedOnServer(lessonId) {
    const fd = new FormData();
    fd.append('lesson_id', lessonId);
    return fetch('/student/mark-content-consumed.php', { method: 'POST', body: fd }).then(r => r.json()).catch(() => ({ success: false }));
}

function getContentRequirements(lesson, videos) {
    const reqs = [];
    if ((lesson.content_type === 'text' || lesson.content_type === 'mixed') && lesson.text_content) reqs.push(T('req_text'));
    if ((lesson.content_type === 'pdf' || lesson.content_type === 'mixed') && lesson.pdf_path) reqs.push('PDF');
    if ((lesson.content_type === 'video' || lesson.content_type === 'mixed') && (lesson.video_url || (videos && videos.length))) reqs.push(T('req_video'));
    return reqs;
}

function updateContentProgressHint(lesson, videos, consumed) {
    const el = byId('lesson-content-progress');
    if (!el) return;
    const reqs = getContentRequirements(lesson, videos);
    el.textContent = !reqs.length ? '' : (consumed ? T('content_done') : T('content_todo', { list: reqs.join(', ') }));
}

function unlockLessonEvaluations(lessonId, lesson, autoLaunchQuiz, videos) {
    lessonContentConsumed = true;
    updateContentProgressHint(lesson, videos, true);
    byId('lesson-quiz-locked').classList.add('hidden');
    const ok = byId('lesson-completed-success-msg');
    if (ok) ok.classList.remove('hidden');
    const isCompleted = !byId('lesson-complete-status').classList.contains('hidden');
    if (pendingLessonQuiz && pendingLessonQuiz.has_quiz && pendingLessonQuiz.questions.length > 0) {
        byId('lesson-quiz-container').classList.remove('hidden');
        renderLessonQuestion(pendingLessonQuiz.questions[0], pendingLessonQuiz.questions.length);
        Toast.success(T('content_unlocked'));
        updateLessonCompleteBar(isCompleted, true, true);
    } else {
        markLessonComplete(lessonId);
    }
}

function showLockedLessonEvaluations(lesson, videos) {
    lessonContentConsumed = false;
    byId('lesson-quiz-container').classList.add('hidden');
    const locked = byId('lesson-quiz-locked');
    const reqs = getContentRequirements(lesson, videos);
    if (reqs.length > 0 && pendingLessonQuiz && pendingLessonQuiz.has_quiz) {
        locked.classList.remove('hidden');
        updateContentProgressHint(lesson, videos, false);
    } else locked.classList.add('hidden');
}

function highlightOutline(lessonId) {
    $$('.rd-les').forEach(b => {
        const on = b.dataset.lessonId == lessonId;
        b.classList.toggle('is-current', on);
        if (on) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
    });
}

function loadLesson(lessonId) {
    currentLessonId = lessonId;
    currentLessonContentType = '';
    LessonContentGate.reset();
    pendingLessonQuiz = null;
    lessonContentConsumed = false;
    highlightOutline(lessonId);

    const viewer = byId('study-viewer-content');
    viewer.scrollTop = 0;
    byId('rd-progress-bar').style.width = '0%';

    if (currentCourseLessons && currentCourseLessons.length) {
        const i = currentCourseLessons.findIndex(l => l.id == lessonId);
        currentNextLesson = (i !== -1 && i + 1 < currentCourseLessons.length) ? currentCourseLessons[i + 1] : null;
    }

    const chat = byId('ai-chat-messages');
    if (chat) chat.innerHTML = '<div class="rd-msg is-bot">' + esc(T('ai_hello')) + '</div>';

    fetch('/student/get-lesson-details.php?lesson_id=' + lessonId)
        .then(r => r.json())
        .then(data => {
            const art = $('.rd-article');
            if (art) { art.classList.remove('is-leaving'); }
            if (!data.success) {
                Toast.error(data.message || T('err_generic'));
                if (data.code === 'locked') { currentLessonId = 0; refreshOutline().then(() => { const o = firstOpenLesson(); if (o) loadLesson(o.id); }); }
                return;
            }
            if (art && !matchMedia('(prefers-reduced-motion: reduce)').matches) { art.classList.remove('is-entering'); void art.offsetWidth; art.classList.add('is-entering'); }
            const l = data.lesson;
            const alreadyUnlocked = data.content_consumed || data.completed || data.quiz_complete;
            lessonContentConsumed = alreadyUnlocked;
            currentLessonContentType = l.content_type || '';

            byId('lesson-viewer-header').classList.remove('hidden');
            $('.rd-article').classList.toggle('is-wide', l.content_type !== 'text');
            byId('study-lesson-title').textContent = l.title;
            byId('study-lesson-badge').textContent = T('type_' + l.content_type);
            const words = l.text_content ? Math.round(String(l.text_content).length / 6) : 0;
            byId('rd-readtime').textContent = words > 0 && l.content_type === 'text' ? ' · ' + T('read_min', { n: Math.max(1, Math.ceil(words / 220)) }) : '';

            const media = byId('study-media-container');
            Array.from(media.children).forEach(el => { if (el._pdfCleanup) el._pdfCleanup(); if (el._vcDestroy) el._vcDestroy(); });
            media.innerHTML = '';
            let textEl = null, videoContainer = null;

            if ((l.content_type === 'text' || l.content_type === 'mixed') && l.text_content) {
                const p = document.createElement('div');
                p.className = 'rd-prose sv-lesson-text';
                renderRich(p, l.text_content);
                media.appendChild(p);
                textEl = p;
            }

            if ((l.content_type === 'pdf' || l.content_type === 'mixed') && l.pdf_path) {
                const box = document.createElement('div');
                box.className = 'rd-pdf';
                media.appendChild(box);
                SVPdf.render(box, '/download.php?type=pdf&file=' + encodeURIComponent(l.pdf_path), {
                    title: l.title || 'PDF',
                    labels: { read: T('pdf_read'), keep: T('pdf_keep'), done: T('pdf_done'), loading: T('pdf_loading'), err: T('pdf_err'), dl: T('pdf_dl'), zin: T('pdf_zin'), zout: T('pdf_zout'), fit: T('pdf_fit'), full: T('pdf_full'), page: T('pdf_page'), of: T('pdf_of') },
                    onComplete: () => LessonContentGate.markDone('pdf')
                });
            }

            // Videos arrive from the server one at a time: only the current one (and finished ones) carry a link.
            const videos = Array.isArray(data.videos) ? data.videos : [];
            if ((l.content_type === 'video' || l.content_type === 'mixed') && videos.length) {
                videoContainer = document.createElement('div');
                media.appendChild(videoContainer);
                VideoChain.mount(videoContainer, videos, {
                    lessonId: lessonId,
                    review: !!alreadyUnlocked,
                    t: (k, v) => T(k, v),
                    post: (action, key, duration) => {
                        const fd = new FormData();
                        fd.append('action', action); fd.append('lesson_id', lessonId); fd.append('video_key', key); fd.append('duration', duration || 0);
                        return fetch('/student/video-progress.php', { method: 'POST', body: fd }).then(r => r.json());
                    },
                    onAllDone: () => LessonContentGate.markDone('video')
                });
            }

            // Lesson quiz, gated by content consumption
            const quizBox = byId('lesson-quiz-container');
            const quizActive = byId('lesson-quiz-active');
            const quizComplete = byId('lesson-quiz-complete-msg');
            const quizHint = byId('lesson-quiz-hint');
            const feedback = byId('lesson-quiz-feedback');
            const ok = byId('lesson-completed-success-msg');
            feedback.textContent = ''; feedback.className = 'rd-feedback';
            byId('lesson-quiz-form').reset();
            quizComplete.classList.add('hidden');
            quizActive.classList.remove('hidden');
            quizHint.classList.remove('hidden');
            quizBox.classList.add('hidden');
            if (ok) ok.classList.add('hidden');
            byId('lesson-quiz-locked').classList.add('hidden');
            byId('quiz-lesson-id').value = lessonId;

            pendingLessonQuiz = { has_quiz: data.has_quiz, questions: data.questions || [] };

            if (alreadyUnlocked) {
                if (data.has_quiz && data.questions.length > 0) {
                    quizBox.classList.remove('hidden');
                    if (ok) ok.classList.remove('hidden');
                    renderLessonQuestion(data.questions[0], data.questions.length);
                }
            } else if (data.has_quiz) {
                showLockedLessonEvaluations(l, videos);
            }

            if (!alreadyUnlocked) {
                LessonContentGate.init({
                    lesson: l, videos: videos, mediaContainer: media, textEl: textEl, videoContainer: null,
                    scrollRoot: viewer,
                    onComplete: (lastType) => {
                        markContentConsumedOnServer(lessonId).then(() => unlockLessonEvaluations(lessonId, l, lastType === 'video', videos));
                    }
                });
            }

            const box = byId('lesson-assignment-container');
            if ((l.has_assignment == 1 || l.has_assignment === '1') && box) { renderLessonAssignmentBox(l, data.submission); box.classList.remove('hidden'); }
            else if (box) box.classList.add('hidden');

            SessionTimer.start(lessonId, 'lesson-session-timer');
            if (l.course_id) {
                const fd = new FormData();
                fd.append('lesson_id', lessonId);
                fd.append('course_id', l.course_id);
                svPost('/student/mark-lesson-visited.php', fd).catch(() => { });
            }
            updateLessonCompleteBar(data.completed || data.quiz_complete, alreadyUnlocked, data.has_quiz);
            loadLessonComments(lessonId);
            setupNotesFor(lessonId, currentLessonContentType);
            setTimeout(updateReadProgress, 500);
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

function updateLessonCompleteBar(isCompleted, contentConsumed, hasQuiz) {
    const bar = byId('lesson-complete-bar'), btn = byId('mark-lesson-complete-btn'), status = byId('lesson-complete-status'), hint = byId('lesson-complete-hint');
    if (!bar) return;
    if (hasQuiz && !isCompleted) { bar.classList.add('hidden'); return; }
    bar.classList.remove('hidden');
    const old = byId('next-lesson-btn'); if (old) old.remove();
    if (isCompleted) {
        btn.classList.add('hidden');
        status.classList.remove('hidden');
        if (hint) hint.classList.add('hidden');
        if (currentNextLesson) {
            const nb = document.createElement('button');
            nb.type = 'button'; nb.id = 'next-lesson-btn'; nb.className = 'btn btn-primary';
            nb.innerHTML = '<span>' + esc(T('next_lesson', { title: currentNextLesson.title })) + '</span> ' + icon('arrow', 16);
            const nid = currentNextLesson.id;
            nb.onclick = () => swapToLesson(nid);
            bar.appendChild(nb);
        }
    } else {
        btn.classList.remove('hidden');
        status.classList.add('hidden');
        if (hint) { hint.classList.remove('hidden'); hint.textContent = contentConsumed ? T('hint_ready') : T('hint_finish'); }
        btn.disabled = !contentConsumed;
        btn.textContent = contentConsumed ? T('mark_done') : T('finish_first');
    }
}

function markLessonComplete(lessonId) {
    const btn = byId('mark-lesson-complete-btn');
    if (btn) { btn.disabled = true; btn.textContent = T('saving'); }
    const fd = new FormData();
    fd.append('lesson_id', lessonId);
    fd.append('mark_complete', 'true');
    fd.append('csrf_token', getCsrfToken());
    fetch('/student/submit-lesson-quiz.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success && d.lesson_complete) {
                byId('lesson-quiz-container').classList.add('hidden');
                onLessonCompleted(lessonId);
            } else {
                Toast.error(d.message || T('lesson_fail'));
                if (btn) { btn.disabled = false; btn.textContent = T('mark_done'); }
            }
        })
        .catch(err => { Toast.error(T('err_network') + ' ' + err.message); if (btn) { btn.disabled = false; btn.textContent = T('mark_done'); } });
}

byId('mark-lesson-complete-btn').addEventListener('click', () => {
    if (!currentLessonId || !lessonContentConsumed) { Toast.error(T('finish_first_err')); return; }
    if (pendingLessonQuiz && pendingLessonQuiz.has_quiz && pendingLessonQuiz.questions.length > 0 && currentLessonContentType !== 'video') {
        const qb = byId('lesson-quiz-container');
        qb.classList.remove('hidden');
        renderLessonQuestion(pendingLessonQuiz.questions[0], pendingLessonQuiz.questions.length);
        qb.scrollIntoView({ behavior: 'smooth', block: 'start' });
        byId('lesson-complete-bar').classList.add('hidden');
        return;
    }
    markLessonComplete(currentLessonId);
});

/* ───────── lesson quiz (one question at a time) ───────── */
function renderLessonQuestion(q, remaining) {
    const box = byId('lesson-quiz-question-box'), hint = byId('lesson-quiz-hint');
    byId('quiz-question-id').value = q.id;
    box.innerHTML = '';
    hint.textContent = remaining > 1 ? T('quiz_remaining', { n: remaining }) : T('quiz_last');
    const wrap = document.createElement('fieldset');
    wrap.id = 'current-quiz-question';
    wrap.className = 'rd-q';
    const lg = document.createElement('legend');
    lg.textContent = q.question_text;
    wrap.appendChild(lg);
    ['A', 'B', 'C', 'D'].forEach(opt => {
        const val = q['option_' + opt.toLowerCase()];
        if (val === null || val === undefined || val === '') return;
        const label = document.createElement('label');
        label.className = 'rd-opt sv-quiz-option';
        label.dataset.option = opt;
        const input = document.createElement('input');
        input.type = 'radio'; input.name = 'answer'; input.value = opt; input.required = true;
        const letter = document.createElement('span'); letter.className = 'rd-opt-l'; letter.textContent = opt;
        const text = document.createElement('span'); text.className = 'rd-opt-t'; text.textContent = val;
        label.appendChild(input); label.appendChild(letter); label.appendChild(text);
        wrap.appendChild(label);
    });
    box.appendChild(wrap);
    byId('lesson-quiz-submit-btn').disabled = false;
}

function hideLessonQuizComplete() {
    byId('lesson-quiz-active').classList.add('hidden');
    byId('lesson-quiz-hint').classList.add('hidden');
    byId('lesson-quiz-complete-msg').classList.remove('hidden');
    setTimeout(() => byId('lesson-quiz-container').classList.add('hidden'), 1800);
}

function highlightQuizAnswer(selected, correct) {
    $$('.sv-quiz-option').forEach(label => {
        const opt = label.dataset.option;
        label.style.pointerEvents = 'none';
        const tag = (cls, txt) => { const s = document.createElement('span'); s.className = 'rd-opt-tag ' + cls; s.textContent = txt; label.appendChild(s); };
        if (opt === correct) { label.classList.add('is-correct'); tag('is-ok', T('correct_answer')); }
        else if (opt === selected) { label.classList.add('is-wrong'); tag('is-bad', T('your_answer')); }
    });
}

byId('lesson-quiz-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const lessonId = byId('quiz-lesson-id').value;
    const questionId = byId('quiz-question-id').value;
    const feedback = byId('lesson-quiz-feedback');
    const submit = byId('lesson-quiz-submit-btn');
    const sel = document.querySelector('input[name="answer"]:checked');
    if (!sel) { feedback.textContent = T('pick_answer'); feedback.className = 'rd-feedback is-bad'; return; }
    submit.disabled = true;
    feedback.textContent = T('checking'); feedback.className = 'rd-feedback';
    const fd = new FormData();
    fd.append('lesson_id', lessonId); fd.append('question_id', questionId); fd.append('answer', sel.value);
    fetch('/student/submit-lesson-quiz.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { Toast.error(d.message || T('err_generic')); submit.disabled = false; return; }
            highlightQuizAnswer(sel.value, d.correct_option);
            feedback.textContent = (d.correct ? T('quiz_correct') : T('quiz_wrong')) + ' ' + T('quiz_answer_was', { opt: d.correct_option, text: d.correct_text || '' });
            feedback.className = 'rd-feedback ' + (d.correct ? 'is-ok' : 'is-bad');
            setTimeout(() => { const q = byId('current-quiz-question'); if (q) q.classList.add('is-leaving'); }, 2600);
            setTimeout(() => {
                if (d.lesson_complete) {
                    hideLessonQuizComplete();
                    onLessonCompleted(lessonId);
                } else {
                    fetch('/student/get-lesson-details.php?lesson_id=' + lessonId).then(r => r.json()).then(x => {
                        if (x.success && x.questions.length > 0) { renderLessonQuestion(x.questions[0], x.questions.length); feedback.textContent = ''; }
                        else { hideLessonQuizComplete(); onLessonCompleted(lessonId); }
                    });
                }
            }, 3000);
        })
        .catch(err => { Toast.error(T('err_network') + ' ' + err.message); submit.disabled = false; });
});

/* ───────── assignment ───────── */
function renderLessonAssignmentBox(lesson, submission) {
    const box = byId('lesson-assignment-container');
    if (!box) return;
    byId('assignment-lesson-id').value = lesson.id;
    byId('assignment-display-title').textContent = lesson.assignment_title || T('assignment_default');

    const types = (lesson.allowed_file_types || 'pdf,docx').split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
    const fileInput = byId('assignment-file-input');
    if (fileInput) fileInput.setAttribute('accept', types.map(t => '.' + t).join(','));
    byId('assignment-allowed-types-label').textContent = T('formats') + ' ' + types.map(t => t.toUpperCase()).join(', ');

    const asg = lesson.assignment_type || 'both';
    byId('assignment-file-wrapper').classList.toggle('hidden', asg === 'link');
    byId('assignment-link-wrapper').classList.toggle('hidden', asg === 'file');

    const instr = byId('assignment-display-instructions');
    if (lesson.assignment_instructions) renderRich(instr, lesson.assignment_instructions);
    else instr.innerHTML = '<em>' + esc(T('assignment_none')) + '</em>';

    byId('assignment-display-deadline').textContent = lesson.assignment_deadline ? T('deadline_on', { date: fmtDateTime(lesson.assignment_deadline) }) : '';

    const statusBox = byId('assignment-existing-status'), details = byId('assignment-existing-details');
    const nameInput = byId('assignment-student-name'), matInput = byId('assignment-student-matricule');
    const linkInput = byId('assignment-link-input'), commentInput = byId('assignment-comment-input');
    const btn = byId('assignment-submit-btn'), btnSpan = $('#assignment-submit-btn span');

    if (submission) {
        if (submission.student_name) nameInput.value = submission.student_name;
        if (submission.student_matricule) matInput.value = submission.student_matricule;
    }
    fileInput.value = '';
    linkInput.value = submission ? (submission.submitted_link || '') : '';
    commentInput.value = submission ? (submission.student_comment || '') : '';
    byId('assignment-form-message').textContent = '';

    const lock = (v) => { [nameInput, matInput, fileInput, linkInput, commentInput].forEach(el => el.disabled = v); btn.disabled = v; };
    if (submission) {
        statusBox.classList.remove('hidden');
        let h = '<span class="num">' + esc(T('sent_on', { date: fmtDateTime(submission.submitted_at) })) + '</span>';
        if (submission.submitted_file_name) h += '<br>' + esc(T('file')) + ' <a href="/download.php?type=assignment&file=' + encodeURIComponent(submission.submitted_file_path) + '" target="_blank" rel="noopener">' + esc(submission.submitted_file_name) + '</a>';
        if (submission.submitted_link) h += '<br>' + esc(T('link')) + ' <a href="' + esc(submission.submitted_link) + '" target="_blank" rel="noopener noreferrer">' + esc(submission.submitted_link) + '</a>';
        details.innerHTML = h;
        lock(true);
        if (btnSpan) btnSpan.textContent = T('as_sent_btn');
    } else {
        statusBox.classList.add('hidden');
        details.innerHTML = '';
        lock(false);
        if (btnSpan) btnSpan.textContent = T('as_submit');
    }
}

function submitStudentAssignment(e) {
    e.preventDefault();
    const lessonId = byId('assignment-lesson-id').value;
    const name = byId('assignment-student-name').value.trim();
    const mat = byId('assignment-student-matricule').value.trim();
    const fi = byId('assignment-file-input'), li = byId('assignment-link-input'), ci = byId('assignment-comment-input');
    const msg = byId('assignment-form-message'), btn = byId('assignment-submit-btn');
    const bad = (t) => { msg.className = 'rd-feedback is-bad'; msg.textContent = t; };
    if (!name || !mat) return bad(T('as_need_id'));
    if (!fi.files[0] && !li.value.trim()) return bad(T('as_need_one'));
    if (fi.files[0] && fi.files[0].size > 20 * 1024 * 1024) return bad(T('as_too_big'));
    const fd = new FormData();
    fd.append('lesson_id', lessonId); fd.append('student_name', name); fd.append('student_matricule', mat);
    if (fi.files[0]) fd.append('assignment_file', fi.files[0]);
    if (li.value.trim()) fd.append('assignment_link', li.value.trim());
    if (ci.value.trim()) fd.append('student_comment', ci.value.trim());
    btn.disabled = true;
    msg.className = 'rd-feedback'; msg.textContent = T('as_uploading');
    fetch('/api/submit-assignment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                msg.className = 'rd-feedback is-ok'; msg.textContent = T('as_done');
                Toast.success(T('as_toast'));
                setTimeout(() => loadLesson(lessonId), 800);
            } else { btn.disabled = false; bad(T('error') + ' ' + d.message); }
        })
        .catch(err => { btn.disabled = false; bad(T('err_network') + ' ' + err.message); });
}

/* ───────── comments (Q&A) ───────── */
function loadLessonComments(lessonId) {
    const list = byId('lesson-comments-list');
    byId('comment-lesson-id').value = lessonId;
    list.innerHTML = '<div class="sv-skeleton" style="height:3rem"></div>';
    fetch('/student/get-comments.php?lesson_id=' + lessonId).then(r => r.json()).then(d => {
        if (!d.success) return;
        list.innerHTML = d.comments.length ? '' : '<p class="sd-meta">' + esc(T('no_questions')) + '</p>';
        d.comments.forEach(c => {
            const el = document.createElement('div');
            el.className = 'rd-comment';
            const role = c.author_role === 'teacher' ? T('role_teacher') : (c.author_role === 'student' ? T('role_student') : esc(c.author_role));
            el.innerHTML = '<p class="rd-comment-h"><strong>' + esc(c.author_name) + '</strong> <span>' + esc(role) + '</span></p><p>' + esc(c.comment_text) + '</p>'
                + (c.teacher_reply ? '<p class="rd-reply"><strong>' + esc(T('teacher_reply')) + '</strong> ' + esc(c.teacher_reply) + '</p>' : '');
            list.appendChild(el);
        });
    }).catch(() => { });
}
byId('lesson-comment-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('lesson_id', byId('comment-lesson-id').value);
    fd.append('comment_text', byId('comment-input').value.trim());
    fetch('/student/post-comment.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.success) { byId('comment-input').value = ''; loadLessonComments(byId('comment-lesson-id').value); Toast.success(T('question_posted')); }
        else Toast.error(d.message || T('err_generic'));
    });
});

/* ───────── notes tied to the video time ───────── */
const VideoNotes = (() => {
    const KEY = 'sv_video_notes';
    const all = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } };
    const save = (d) => { try { localStorage.setItem(KEY, JSON.stringify(d)); } catch (e) { } };
    const toSec = (t) => { const p = String(t).split(':').map(n => parseInt(n, 10)); if (p.some(isNaN)) return -1; return p.length === 3 ? p[0] * 3600 + p[1] * 60 + p[2] : p.length === 2 ? p[0] * 60 + p[1] : -1; };
    return {
        getForLesson: (id) => all()[id] || [],
        addNote: (id, ts, text) => {
            const a = all(); if (!a[id]) a[id] = [];
            a[id].push({ ts: ts || '—', text: text, id: Date.now() });
            a[id].sort((x, y) => toSec(x.ts) - toSec(y.ts));
            save(a);
        },
        deleteNote: (id, noteId) => { const a = all(); if (!a[id]) return; a[id] = a[id].filter(n => n.id !== noteId); save(a); }
    };
})();

function renderVideoNotes(lessonId) {
    const list = byId('video-notes-list');
    if (!list) return;
    const notes = VideoNotes.getForLesson(lessonId);
    list.innerHTML = '';
    if (!notes.length) { list.innerHTML = '<p id="no-notes-msg" class="sd-meta">' + esc(T('no_notes')) + '</p>'; return; }
    notes.forEach(n => {
        const row = document.createElement('div');
        row.className = 'rd-note';
        row.innerHTML = '<span class="rd-note-t num">' + esc(n.ts) + '</span><span class="rd-note-x">' + esc(n.text) + '</span>'
            + '<button type="button" class="rd-note-del" aria-label="' + esc(T('delete')) + '">' + icon('x', 14) + '</button>';
        row.querySelector('button').onclick = () => deleteVideoNote(lessonId, n.id);
        list.appendChild(row);
    });
}
function deleteVideoNote(lessonId, noteId) { VideoNotes.deleteNote(lessonId, noteId); renderVideoNotes(lessonId); }
function setupNotesFor(lessonId, contentType) {
    const isVideo = contentType === 'video' || contentType === 'mixed';
    const f = byId('note-ts-field'); if (f) f.classList.toggle('hidden', !isVideo);
    const intro = byId('notes-intro'); if (intro) intro.textContent = isVideo ? T('notes_intro_video') : T('notes_intro_text');
    renderVideoNotes(lessonId);
}
byId('video-note-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const lessonId = parseInt(byId('comment-lesson-id').value, 10);
    const ts = byId('note-timestamp').value.trim();
    const text = byId('note-text').value.trim();
    if (!text) return;
    if (ts && !/^\d{1,2}(:\d{2}){1,2}$/.test(ts)) { Toast.error(T('note_time_bad')); return; }
    VideoNotes.addNote(lessonId, ts, text);
    renderVideoNotes(lessonId);
    byId('note-timestamp').value = ''; byId('note-text').value = '';
    Toast.success(T('note_added'));
});

/* ───────── AI companion ───────── */
function nowHM() { const d = new Date(); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }

function addTyping(label) {
    const msgs = byId('ai-chat-messages');
    const el = document.createElement('div');
    el.className = 'rd-msg is-bot is-typing';
    el.id = 'ai-loader-' + Date.now();
    el.innerHTML = '<span>' + esc(label) + '</span><i></i><i></i><i></i>';
    msgs.appendChild(el); msgs.scrollTop = msgs.scrollHeight;
    return el.id;
}

function appendAiMessage(sender, text, isSystem) {
    const msgs = byId('ai-chat-messages');
    const div = document.createElement('div');
    div.className = 'rd-msg ' + (sender === 'me' ? 'is-me' : (isSystem ? 'is-sys' : 'is-bot'));
    div.innerHTML = '<span class="rd-msg-t">' + esc(text) + '</span>' + (isSystem ? '' : '<span class="rd-time num">' + nowHM() + '</span>');
    msgs.appendChild(div); msgs.scrollTop = msgs.scrollHeight;
}

function triggerAiAction(action) {
    if (!currentLessonId) { Toast.error(T('ai_need_lesson')); return; }
    const label = action === 'explain' ? T('ai_l_explain') : (action === 'generate_quiz' ? T('ai_l_quiz') : T('ai_l_sum'));
    const lid = addTyping(label);
    fetch('/api/ai-student.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lesson_id: currentLessonId, action: action }) })
        .then(r => r.json())
        .then(d => {
            const l = byId(lid); if (l) l.remove();
            if (d.success) {
                if (action === 'generate_quiz' && d.quiz) renderAiQuiz(d.quiz);
                else if (d.response) appendAiMessage('bot', d.response, false);
            } else appendAiMessage('sys', T('error') + ' ' + d.error, true);
        })
        .catch(err => { const l = byId(lid); if (l) l.remove(); appendAiMessage('sys', T('err_network') + ' ' + err.message, true); });
}

function sendAiMessage(e) {
    if (e) e.preventDefault();
    const input = byId('ai-chat-input');
    const text = input.value.trim();
    if (!text) return;
    if (!currentLessonId) { Toast.error(T('ai_need_lesson')); return; }
    appendAiMessage('me', text);
    input.value = '';
    const lid = addTyping(T('ai_typing'));
    fetch('/api/ai-student.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lesson_id: currentLessonId, action: 'chat', message: text }) })
        .then(r => r.json())
        .then(d => { const l = byId(lid); if (l) l.remove(); if (d.success) appendAiMessage('bot', d.response, false); else appendAiMessage('sys', d.error, true); })
        .catch(err => { const l = byId(lid); if (l) l.remove(); appendAiMessage('sys', T('err_network') + ' ' + err.message, true); });
}

function renderAiQuiz(questions) {
    const msgs = byId('ai-chat-messages');
    const wrap = document.createElement('div');
    wrap.className = 'rd-aiquiz';
    wrap.innerHTML = '<h4>' + esc(T('ai_quiz_title')) + '</h4>';
    questions.forEach((q, idx) => {
        const qb = document.createElement('div');
        qb.className = 'rd-aiq';
        const p = document.createElement('p'); p.textContent = (idx + 1) + '. ' + q.question; qb.appendChild(p);
        const opts = document.createElement('div'); opts.className = 'rd-aiopts';
        Object.entries(q.options).forEach(([key, val]) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'rd-opt'; b.dataset.key = key;
            b.innerHTML = '<span class="rd-opt-l">' + esc(key) + '</span><span class="rd-opt-t">' + esc(val) + '</span>';
            b.onclick = () => {
                Array.from(opts.children).forEach(x => x.disabled = true);
                const mark = (el, ok) => { el.classList.add(ok ? 'is-correct' : 'is-wrong'); const s = document.createElement('span'); s.className = 'rd-opt-tag ' + (ok ? 'is-ok' : 'is-bad'); s.textContent = ok ? T('correct_answer') : T('your_answer'); el.appendChild(s); };
                if (key === q.correct) mark(b, true);
                else { mark(b, false); const c = Array.from(opts.children).find(x => x.dataset.key === q.correct); if (c) mark(c, true); }
            };
            opts.appendChild(b);
        });
        qb.appendChild(opts);
        wrap.appendChild(qb);
    });
    msgs.appendChild(wrap); msgs.scrollTop = msgs.scrollHeight;
}

/* ═════════════════════════ FINAL EXAM ═════════════════════════ */
function abandonExam() { ExamTimer.stop(); closeModal('final-exam-modal'); }

function startFinalExam(courseId, courseTitle) {
    byId('exam-course-id').value = courseId;
    byId('exam-course-title').textContent = courseTitle;
    byId('exam-error-alert').classList.add('hidden');
    ExamTimer.stop();
    fetch('/student/get-exam-questions.php?course_id=' + courseId)
        .then(r => r.json())
        .then(d => {
            if (!d.success) { Toast.error(d.message || T('exam_load_err')); return; }
            const c = byId('exam-questions-container');
            c.innerHTML = '';
            byId('exam-attempts-info').textContent = T('exam_attempts', { n: d.attempts_left });
            d.questions.forEach((q, idx) => {
                const fs = document.createElement('fieldset');
                fs.className = 'rd-q sd-qq';
                const lg = document.createElement('legend');
                lg.innerHTML = '<span class="num">' + (idx + 1) + '/' + d.questions.length + '</span>';
                const t = document.createElement('span'); t.textContent = q.question_text; lg.appendChild(t);
                fs.appendChild(lg);
                ['A', 'B', 'C', 'D'].forEach(o => {
                    const v = q['option_' + o.toLowerCase()];
                    if (v === null || v === undefined || v === '') return;
                    const label = document.createElement('label');
                    label.className = 'rd-opt';
                    const inp = document.createElement('input');
                    inp.type = 'radio'; inp.name = 'question_' + q.id; inp.value = o; inp.required = true;
                    const l = document.createElement('span'); l.className = 'rd-opt-l'; l.textContent = o;
                    const tx = document.createElement('span'); tx.className = 'rd-opt-t'; tx.textContent = v;
                    label.appendChild(inp); label.appendChild(l); label.appendChild(tx);
                    fs.appendChild(label);
                });
                c.appendChild(fs);
            });
            openModal('final-exam-modal');
            const seconds = d.seconds_left ?? (d.exam_minutes || 90) * 60;
            ExamTimer.startSeconds('exam-timer', seconds, () => {
                Toast.error(T('exam_time_up'));
                byId('final-exam-form').requestSubmit();
            });
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
}

byId('final-exam-form').addEventListener('submit', function (e) {
    e.preventDefault();
    byId('exam-error-alert').classList.add('hidden');
    fetch('/student/submit-final-exam.php', { method: 'POST', body: new FormData(e.target) })
        .then(r => r.json())
        .then(d => {
            ExamTimer.stop();
            if (!d.success) { Toast.error(d.message || T('exam_submit_err')); return; }
            closeModal('final-exam-modal');
            if (d.passed) {
                certDialog(d.score, d.certificate_code);
            } else {
                Toast.error(T('exam_failed', { score: d.score }), 6000);
                if (d.report_url) setTimeout(() => Toast.info(T('exam_report_made'), 7000), 800);
                refreshDashboard();
                setTimeout(() => location.reload(), 1800);
            }
        })
        .catch(err => Toast.error(T('err_network') + ' ' + err.message));
});
byId('final-exam-form').addEventListener('invalid', () => byId('exam-error-alert').classList.remove('hidden'), true);

function certDialog(score, code) {
    const m = document.createElement('div');
    m.className = 'sd-modal sd-modal-hard';
    m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true'); m.setAttribute('aria-labelledby', 'cd-title');
    m.innerHTML = '<div class="sd-sheet sd-celebrate"><p class="sd-kicker">' + esc(T('cert_kicker')) + '</p><h2 id="cd-title">' + esc(T('cert_title')) + '</h2>'
        + '<p class="sd-meta">' + esc(T('cert_text', { score: score })) + '</p>'
        + (code ? '<p class="sd-code">' + esc(T('code')) + ' <span class="num">' + esc(code) + '</span></p>' : '')
        + '<div class="sd-sheet-act">' + (code ? '<a class="btn btn-primary" href="/certificate.php?code=' + encodeURIComponent(code) + '">' + esc(T('cert_see')) + '</a>' : '')
        + '<button type="button" class="btn btn-ghost" data-close>' + esc(T('close')) + '</button></div></div>';
    document.body.appendChild(m);
    document.body.classList.add('sd-lock');
    m.querySelector('a, button').focus();
    m.querySelector('[data-close]').addEventListener('click', () => { m.remove(); location.reload(); });
}


/* ───────────────────────── launched evaluations: countdown + auto refresh ───────────────────────── */
function tickLiveCounts() {
    $$('.sd-live-count').forEach(el => {
        let left = parseInt(el.getAttribute('data-start-in') || '0', 10);
        if (left <= 0) { el.textContent = T('live_now'); el.classList.add('is-live'); return; }
        const m = Math.floor(left / 3600) > 0 ? String(Math.floor(left / 3600)) + ':' + String(Math.floor(left % 3600 / 60)).padStart(2, '0') : String(Math.floor(left / 60)).padStart(2, '0');
        const sec = String(left % 60).padStart(2, '0');
        el.textContent = T('starts_in', { t: m + ':' + sec });
        el.setAttribute('data-start-in', String(left - 1));
    });
}

function pollUpcomingEvals() {
    const head = byId('ev-up');
    if (!head || document.hidden) return;
    fetch('/student/get-upcoming-evals.php', { cache: 'no-store' }).then(r => r.json()).then(d => {
        if (!d.success) return;
        const sig = d.items.map(i => i.id + (i.is_running ? 'r' : 'w')).join(',');
        if (sig === (head.getAttribute('data-ev-ids') || '')) return;
        // The list changed (something was launched, started or closed): swap in the server-rendered block
        return fetch(location.pathname, { cache: 'no-store' }).then(r => r.text()).then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.getElementById('ev-up');
            if (!fresh) return;
            head.parentElement.replaceWith(fresh.parentElement);
            if (d.items.some(i => !i.is_async)) Toast.success(T('new_eval'));
        });
    }).catch(() => { });
}

/* ───────────────────────── boot ───────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    initCatalogueSearch();
    loadNotifications();
    setSub('mine');
    tickLiveCounts();
    setInterval(tickLiveCounts, 1000);
    setInterval(pollUpcomingEvals, 8000);
    document.addEventListener("visibilitychange", () => { if (!document.hidden) pollUpcomingEvals(); });
    const q = new URLSearchParams(location.search);
    if (!routeFromHash()) switchTab('home', undefined, { noHash: true, noFocus: true });
    const cid = parseInt(q.get('course_id') || '0', 10);
    if (cid > 0) studyCourse(cid);
    if (window.matchMedia) window.matchMedia('(min-width: 961px)').addEventListener('change', () => { if (!readerEl().classList.contains('hidden')) { readerEl().classList.remove('rd--scrim'); } });
});
