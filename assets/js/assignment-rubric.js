/*
 * Teacher dashboard, lesson form: the rubric (marking grid) editor of an assignment.
 * The rows live in #asg-rubric-rows; the hidden field #lesson-assignment-rubric always holds the current rubric as JSON, which the server
 * re-checks (lib/AssignmentRubric.php). With at least one criterion the maximum mark is the total of the criteria and cannot be typed.
 * AsgRubric.load(jsonOrArray), AsgRubric.clear(). Everything shown is put in with textContent / value.
 */
(function () {
  'use strict';
  var T = window.SV_T || {};
  var t = function (k, v) { var s = T[k] || k; Object.keys(v || {}).forEach(function (n) { s = s.split('{' + n + '}').join(String(v[n])); }); return s; };
  var $ = function (id) { return document.getElementById(id); };
  function fmt(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }
  function num(v) { var s = String(v == null ? '' : v).trim().replace(',', '.'); return s !== '' && isFinite(s) ? Math.round(parseFloat(s) * 100) / 100 : null; }

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
  function field(label, input) { var w = el('label', 'asg-rb-f'); w.appendChild(el('span', '', label)); w.appendChild(input); return w; }

  function levelsToText(levels) { return (levels || []).map(function (l) { return l.label + ' : ' + fmt(l.points); }).join('\n'); }
  function textToLevels(txt) {
    return String(txt || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean).map(function (line) {
      var m = line.match(/^(.*?)\s*[:：]\s*([0-9]+(?:[.,][0-9]+)?)\s*$/);
      return m ? { label: m[1], points: m[2].replace(',', '.') } : { label: line, points: '' };
    });
  }

  function addRow(c) {
    var rows = $('asg-rubric-rows'); if (!rows) return;
    c = c || {};
    var row = el('div', 'asg-rb-row'); row.dataset.id = c.id || '';
    var title = el('input'); title.type = 'text'; title.maxLength = 120; title.value = c.title || ''; title.className = 'asg-rb-title'; title.placeholder = t('asg_rubric_crit');
    var max = el('input'); max.type = 'number'; max.min = '0.25'; max.max = '1000'; max.step = '0.25'; max.value = c.max != null ? c.max : ''; max.className = 'asg-rb-max';
    var desc = el('input'); desc.type = 'text'; desc.maxLength = 300; desc.value = c.description || ''; desc.className = 'asg-rb-desc';
    var lv = el('textarea'); lv.rows = 3; lv.value = levelsToText(c.levels); lv.placeholder = t('asg_rubric_levels_ph'); lv.className = 'asg-rb-levels';
    var rm = el('button', 'asg-rb-rm', '×'); rm.type = 'button'; rm.title = t('asg_rubric_remove'); rm.setAttribute('aria-label', t('asg_rubric_remove'));
    rm.addEventListener('click', function () { row.remove(); sync(); });
    var top = el('div', 'asg-rb-top'); top.appendChild(field(t('asg_rubric_crit'), title)); top.appendChild(field(t('asg_rubric_max'), max)); top.appendChild(rm);
    row.appendChild(top); row.appendChild(field(t('asg_rubric_desc'), desc)); row.appendChild(field(t('asg_rubric_levels'), lv));
    row.addEventListener('input', sync);
    rows.appendChild(row);
    return row;
  }

  function read() {
    var out = [];
    document.querySelectorAll('#asg-rubric-rows .asg-rb-row').forEach(function (row) {
      var c = { title: row.querySelector('.asg-rb-title').value, max: row.querySelector('.asg-rb-max').value, description: row.querySelector('.asg-rb-desc').value, levels: textToLevels(row.querySelector('.asg-rb-levels').value) };
      if (row.dataset.id) c.id = row.dataset.id;
      out.push(c);
    });
    return out;
  }

  function sync() {
    var list = read(), hidden = $('lesson-assignment-rubric'), total = 0, maxInput = $('lesson-assignment-max');
    list.forEach(function (c) { var m = num(c.max); if (m) total += m; });
    if (hidden) hidden.value = list.length ? JSON.stringify(list) : '';
    var info = $('asg-rubric-total');
    if (info) { info.textContent = list.length ? t('asg_rubric_total', { n: fmt(total) }) : ''; info.hidden = !list.length; }
    var warn = $('asg-rubric-warn'); if (warn) warn.hidden = !list.length;
    if (maxInput) {
      if (list.length) { maxInput.value = Math.round(total * 100) / 100; maxInput.readOnly = true; maxInput.classList.add('is-locked'); }
      else { maxInput.readOnly = false; maxInput.classList.remove('is-locked'); }
    }
  }

  function clear() { var rows = $('asg-rubric-rows'); if (rows) rows.textContent = ''; sync(); }
  function load(src) {
    clear();
    var list = src;
    if (typeof src === 'string') { try { list = JSON.parse(src); } catch (e) { list = []; } }
    (Array.isArray(list) ? list : []).forEach(addRow);
    sync();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var add = $('asg-rubric-add');
    if (add) add.addEventListener('click', function () { var r = addRow({}); sync(); if (r) r.querySelector('.asg-rb-title').focus(); });
    var form = $('lesson-form'); if (form) form.addEventListener('submit', sync);
  });
  window.AsgRubric = { load: load, clear: clear };
})();
