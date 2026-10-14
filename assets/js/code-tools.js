/*
 * StudyVibe code tools: lint and format for the code editor of the exam room. Vanilla JS, no build step, no network.
 *
 * The language comes from the question (the server sends a profile with it: see LiveCodeReview::toolProfile), so the teacher configures
 * nothing. Linting is deliberately conservative: it flags what is certainly wrong (unbalanced brackets, unterminated strings, a missing
 * colon in Python, indentation errors, a stray comma in SQL) and a few likely slips; it never blocks the student. Formatting only changes
 * whitespace (indentation, spacing, line breaks) outside strings and comments, and SQL keyword case.
 *
 *   CodeTools.lint(profile, code)   -> [{ line, col, severity: 'error'|'warning'|'info', message }]
 *   CodeTools.format(profile, code) -> string
 *   CodeTools.setLang('fr'|'en')
 */
(function (root) {
  'use strict';

  var LANG = 'fr';
  var MSG = {
    fr: {
      unterminated: "Chaîne de caractères non terminée.",
      block_unterminated: "Commentaire /* … */ jamais fermé.",
      close_without_open: "« {c} » ferme quelque chose qui n'a jamais été ouvert.",
      mismatch: "« {c} » ne correspond pas à « {o} » ouvert à la ligne {l}.",
      never_closed: "« {o} » ouvert ici n'est jamais fermé.",
      trailing_ws: "Espaces en fin de ligne.",
      mixed_indent: "Tabulations et espaces mélangés dans l'indentation.",
      long_line: "Ligne trop longue ({n} caractères, {m} conseillés).",
      semicolon: "Point-virgule manquant ?",
      py_colon: "Deux-points « : » manquant à la fin de cette ligne.",
      py_indent: "Indentation inattendue : le bloc précédent n'a pas été ouvert par « : ».",
      py_dedent: "Indentation incohérente : ce retrait ne correspond à aucun bloc ouvert.",
      py_assign: "« = » affecte une valeur ; utilisez « == » pour comparer.",
      sql_eq: "En SQL, la comparaison s'écrit « = » (pas « == »).",
      sql_comma: "Virgule en trop juste avant « ) ».",
      sql_semicolon: "Pensez à terminer l'instruction par un point-virgule.",
      sql_assign_null: "Pour tester NULL, utilisez « IS NULL » (pas « = NULL »).",
      empty: "Aucun code pour l'instant."
    },
    en: {
      unterminated: "Unterminated string.",
      block_unterminated: "Comment /* … */ is never closed.",
      close_without_open: "“{c}” closes something that was never opened.",
      mismatch: "“{c}” does not match “{o}” opened on line {l}.",
      never_closed: "“{o}” opened here is never closed.",
      trailing_ws: "Trailing spaces.",
      mixed_indent: "Tabs and spaces mixed in the indentation.",
      long_line: "Line too long ({n} characters, {m} advised).",
      semicolon: "Missing semicolon?",
      py_colon: "Missing colon “:” at the end of this line.",
      py_indent: "Unexpected indentation: the previous block was not opened with “:”.",
      py_dedent: "Inconsistent indentation: this dedent matches no open block.",
      py_assign: "“=” assigns a value; use “==” to compare.",
      sql_eq: "In SQL, comparison is written “=” (not “==”).",
      sql_comma: "Extra comma right before “)”.",
      sql_semicolon: "Consider ending the statement with a semicolon.",
      sql_assign_null: "To test for NULL, use “IS NULL” (not “= NULL”).",
      empty: "No code yet."
    }
  };
  function t(key, vars) {
    var s = (MSG[LANG] || MSG.fr)[key] || key;
    if (vars) for (var k in vars) s = s.split('{' + k + '}').join(String(vars[k]));
    return s;
  }

  var OPEN = { '(': ')', '[': ']', '{': '}' };
  var CLOSE = { ')': '(', ']': '[', '}': '{' };

  /* ---------------------------------------------------------------------------------------------
   * Scanner: walks the code once and returns, for every line, its segments (code / string / comment) plus the problems met on the way
   * (unterminated strings, unterminated block comments, bracket errors). Everything else (lint rules, formatter) works on segments.
   * ------------------------------------------------------------------------------------------- */
  function scan(code, profile) {
    var fam = profile.family || 'brace';
    var lineComment = profile.line_comment || '';
    var lcs = lineComment ? [lineComment] : [];
    if (profile.lang === 'php') lcs = ['//', '#'];
    if (fam === 'python' || fam === 'shell') lcs = ['#'];
    if (fam === 'sql') lcs = ['--'];
    var blockComment = profile.block_comment !== false && fam !== 'python' && fam !== 'shell' && fam !== 'plain';
    var strings = profile.strings || ['"', "'"];
    var triple = fam === 'python';
    var js = profile.lang === 'javascript' || profile.lang === 'typescript';
    var checkBrackets = fam === 'brace' || fam === 'python' || fam === 'sql';
    var hasHeredoc = code.indexOf('<<<') !== -1;

    var lines = [{ segs: [], inBlock: false, inString: false }];
    var problems = [];
    var stack = [];
    var i = 0, n = code.length, line = 0, col = 0;
    var mode = 'code';           // code | block | string | tstring (triple/template, may span lines)
    var delim = '';
    var seg = { t: 'code', s: '' };
    var strStart = null;
    var lastSig = '';            // last significant char of code on this line, to spot a regex literal in JS

    function push() { if (seg.s !== '') lines[line].segs.push(seg); seg = { t: seg.t, s: '' }; }
    function setType(tp) { if (seg.t !== tp) { push(); seg = { t: tp, s: '' }; } }
    function newline() {
      push();
      line++; col = 0; lastSig = '';
      lines.push({ segs: [], inBlock: mode === 'block', inString: mode === 'tstring' });
      seg = { t: mode === 'block' ? 'com' : (mode === 'tstring' ? 'str' : 'code'), s: '' };
    }

    while (i < n) {
      var ch = code[i];
      if (ch === '\r') { i++; continue; }
      if (ch === '\n') {
        if (mode === 'string') {
          problems.push({ line: strStart.line + 1, col: strStart.col + 1, severity: 'error', message: t('unterminated') });
          mode = 'code'; delim = '';
        }
        newline(); i++; continue;
      }
      if (mode === 'block') {
        setType('com');
        if (ch === '*' && code[i + 1] === '/') { seg.s += '*/'; i += 2; col += 2; mode = 'code'; push(); seg = { t: 'code', s: '' }; continue; }
        seg.s += ch; i++; col++; continue;
      }
      if (mode === 'string' || mode === 'tstring') {
        setType('str');
        if (ch === '\\' && fam !== 'sql') { seg.s += ch + (code[i + 1] && code[i + 1] !== '\n' ? code[i + 1] : ''); i += 2; col += 2; continue; }
        if (mode === 'tstring' && delim.length === 3) {
          if (code.substr(i, 3) === delim) { seg.s += delim; i += 3; col += 3; mode = 'code'; delim = ''; push(); seg = { t: 'code', s: '' }; continue; }
        } else if (ch === delim) {
          if (fam === 'sql' && code[i + 1] === delim) { seg.s += ch + ch; i += 2; col += 2; continue; }   // '' inside an SQL string
          seg.s += ch; i++; col++; mode = 'code'; delim = ''; push(); seg = { t: 'code', s: '' }; continue;
        }
        seg.s += ch; i++; col++; continue;
      }
      // normal code
      setType('code');
      var two = code.substr(i, 2);
      var isLine = false;
      for (var q = 0; q < lcs.length; q++) { if (code.substr(i, lcs[q].length) === lcs[q]) { isLine = true; break; } }
      if (isLine && profile.lang === 'php' && code[i] === '#' && code[i + 1] === '[') isLine = false;   // PHP attribute
      if (isLine) {
        setType('com');
        while (i < n && code[i] !== '\n') { if (code[i] !== '\r') seg.s += code[i]; i++; col++; }
        push(); seg = { t: 'code', s: '' };
        continue;
      }
      if (blockComment && two === '/*') { mode = 'block'; setType('com'); seg.s += '/*'; i += 2; col += 2; continue; }
      if (triple && (code.substr(i, 3) === '"""' || code.substr(i, 3) === "'''")) {
        delim = code.substr(i, 3); mode = 'tstring'; setType('str'); seg.s += delim; i += 3; col += 3; continue;
      }
      if (strings.indexOf(ch) !== -1 && !(fam === 'sql' && ch === '"' && false)) {
        // JS/TS: a slash after an operator starts a regex literal, not a string-free division: handled below, here only quotes
        if (ch === '`' && (js || profile.lang === 'go')) { mode = 'tstring'; delim = '`'; strStart = { line: line, col: col }; setType('str'); seg.s += ch; i++; col++; continue; }
        mode = 'string'; delim = ch; strStart = { line: line, col: col }; setType('str'); seg.s += ch; i++; col++; continue;
      }
      if (js && ch === '/' && two !== '//' && two !== '/*' && (lastSig === '' || '(,=:[!&|?{};+-*%<>'.indexOf(lastSig) !== -1)) {
        // regex literal: skip to the closing slash on the same line (brackets and quotes inside it are not code)
        var j = i + 1, inClass = false;
        while (j < n && code[j] !== '\n') { if (code[j] === '\\') { j += 2; continue; } if (code[j] === '[') inClass = true; else if (code[j] === ']') inClass = false; else if (code[j] === '/' && !inClass) break; j++; }
        if (j < n && code[j] === '/') { setType('str'); seg.s += code.substring(i, j + 1); col += j + 1 - i; i = j + 1; setType('code'); lastSig = '/'; continue; }
      }
      if (CLOSE[ch] || OPEN[ch]) {
        if (checkBrackets && !(hasHeredoc && false)) {
          if (OPEN[ch]) stack.push({ c: ch, line: line, col: col });
          else {
            var top = stack.length ? stack[stack.length - 1] : null;
            if (!top) problems.push({ line: line + 1, col: col + 1, severity: 'error', message: t('close_without_open', { c: ch }) });
            else if (top.c !== CLOSE[ch]) { problems.push({ line: line + 1, col: col + 1, severity: 'error', message: t('mismatch', { c: ch, o: top.c, l: top.line + 1 }) }); stack.pop(); }
            else stack.pop();
          }
        }
      }
      if (ch !== ' ' && ch !== '\t') lastSig = ch;
      seg.s += ch; i++; col++;
    }
    push();
    if (mode === 'block') problems.push({ line: line + 1, col: 1, severity: 'error', message: t('block_unterminated') });
    if (mode === 'string' && !hasHeredoc) problems.push({ line: strStart.line + 1, col: strStart.col + 1, severity: 'error', message: t('unterminated') });
    if (mode === 'tstring') problems.push({ line: strStart ? strStart.line + 1 : line + 1, col: 1, severity: 'error', message: t('unterminated') });
    stack.forEach(function (o) { problems.push({ line: o.line + 1, col: o.col + 1, severity: 'error', message: t('never_closed', { o: o.c }) }); });
    return { lines: lines, problems: problems };
  }

  function codeOf(l) { return l.segs.filter(function (s) { return s.t === 'code'; }).map(function (s) { return s.s; }).join(''); }
  function maskOf(l) { return l.segs.map(function (s) { return s.t === 'code' ? s.s : (s.t === 'str' ? s.s.replace(/[^\n]/g, '"') : ''); }).join(''); }

  /* ---------------------------------------------------------------------------------------------
   * Lint
   * ------------------------------------------------------------------------------------------- */
  function lint(profile, code) {
    profile = profile || {};
    var fam = profile.family || 'brace';
    var out = [];
    code = String(code == null ? '' : code).replace(/\r\n?/g, '\n');
    if (code.trim() === '') return out;
    var sc = scan(code, profile);
    out = out.concat(sc.problems);
    var raw = code.split('\n');
    var maxLine = profile.max_line || 120;

    raw.forEach(function (text, idx) {
      var l = idx + 1;
      if (/[ \t]+$/.test(text) && text.trim() !== '') out.push({ line: l, col: text.replace(/[ \t]+$/, '').length + 1, severity: 'info', message: t('trailing_ws') });
      var lead = (text.match(/^[ \t]*/) || [''])[0];
      if (lead.indexOf('\t') !== -1 && lead.indexOf(' ') !== -1 && !(sc.lines[idx] && sc.lines[idx].inString)) out.push({ line: l, col: 1, severity: fam === 'python' ? 'error' : 'warning', message: t('mixed_indent') });
      if (text.length > maxLine) out.push({ line: l, col: maxLine + 1, severity: 'info', message: t('long_line', { n: text.length, m: maxLine }) });
    });

    if (fam === 'brace' && profile.semicolons) lintSemicolons(sc, out);
    if (fam === 'python') lintPython(sc, raw, out);
    if (fam === 'sql') lintSql(sc, out, code);

    out.sort(function (a, b) { return a.line - b.line || a.col - b.col; });
    // one message per line and text
    var seen = {};
    return out.filter(function (p) { var k = p.line + '|' + p.message; if (seen[k]) return false; seen[k] = 1; return true; });
  }

  var KW_NO_SEMI = /^(if|for|while|switch|catch|synchronized|else|do|try|finally|class|interface|enum|struct|namespace|using|import|package|public|private|protected|static|abstract|final|void|@|case|default|foreach|function|def|elif|with|#)/;
  function lintSemicolons(sc, out) {
    var codeLines = sc.lines.map(function (l) { return codeOf(l).replace(/\s+$/, ''); });
    function nextCode(i) { for (var k = i + 1; k < codeLines.length; k++) { if (codeLines[k].trim() !== '') return codeLines[k].trim(); } return ''; }
    codeLines.forEach(function (cl, i) {
      if (sc.lines[i].inBlock || sc.lines[i].inString) return;
      var s = cl.trim();
      if (!s) return;
      if (/[;{}(,:]$/.test(s) || /[+\-*\/%&|^=<>?.]$/.test(s)) return;
      if (/^<\?|^\?>/.test(s)) return;
      var nx = nextCode(i);
      if (/^[.)+\-*\/%&|?:,\]]/.test(nx) || /^\{/.test(nx)) return;
      var stmt = /^(return|break|continue|throw)\b/.test(s)
        || /^(int|long|double|float|boolean|bool|char|byte|short|String|var|let|const|final|auto)\b[^{}]*=[^{}]*$/.test(s)
        || /^\$?[A-Za-z_][\w.\->:]*\s*(\+\+|--|[-+*\/]?=)[^{}]*$/.test(s)
        || (/^[A-Za-z_$][\w.>\-:]*\([^{};]*\)$/.test(s) && !KW_NO_SEMI.test(s));
      if (stmt) out.push({ line: i + 1, col: cl.length + 1, severity: 'warning', message: t('semicolon') });
    });
  }

  function lintPython(sc, raw, out) {
    var stack = [0];
    var prevOpens = false;
    var depth = 0;
    sc.lines.forEach(function (l, idx) {
      var text = raw[idx] || '';
      if (l.inString) return;
      var masked = maskOf(l);
      var code = codeOf(l).replace(/\s+$/, '');
      var startDepth = depth;
      for (var k = 0; k < masked.length; k++) { var c = masked[k]; if (c === '(' || c === '[' || c === '{') depth++; else if (c === ')' || c === ']' || c === '}') depth = Math.max(0, depth - 1); }
      if (text.trim() === '' || /^\s*#/.test(text)) return;
      if (startDepth > 0) return;                       // continuation of a bracketed expression
      var indent = (text.match(/^[ \t]*/) || [''])[0].replace(/\t/g, '    ').length;
      var top = stack[stack.length - 1];
      if (indent > top) {
        if (!prevOpens) out.push({ line: idx + 1, col: 1, severity: 'error', message: t('py_indent') });
        stack.push(indent);
      } else if (indent < top) {
        while (stack.length > 1 && stack[stack.length - 1] > indent) stack.pop();
        if (stack[stack.length - 1] !== indent) { out.push({ line: idx + 1, col: 1, severity: 'error', message: t('py_dedent') }); stack.push(indent); }
      }
      var m = /^\s*(def|class|if|elif|else|for|while|try|except|finally|with|match|case)\b/.exec(code);
      var endsColon = /:\s*$/.test(code);
      if (m && !endsColon && depth === 0 && !/:\s*\S/.test(code.replace(/\blambda\b[^:]*:/g, '').replace(/\[[^\]]*\]/g, '')) ) {
        out.push({ line: idx + 1, col: code.length + 1, severity: 'error', message: t('py_colon') });
      }
      if (/^\s*(if|elif|while)\b/.test(code)) {
        var cond = maskOf(l).replace(/:=|==|!=|<=|>=|\+=|-=|\*=|\/=/g, '  ');
        if (/[^=!<>]=[^=]/.test(cond.replace(/^\s*(if|elif|while)\b/, ''))) out.push({ line: idx + 1, col: 1, severity: 'error', message: t('py_assign') });
      }
      prevOpens = !!m;          // a block keyword opens a block (a missing colon is reported once, not twice)
    });
  }

  function lintSql(sc, out, code) {
    var all = sc.lines.map(function (l) { return maskOf(l); });   // strings kept as placeholders: "IN ('a', 'b')" must not look like "IN (, )"
    all.forEach(function (c, i) {
      if (/(^|[^=!<>])==/.test(c)) out.push({ line: i + 1, col: 1, severity: 'error', message: t('sql_eq') });
      if (/=\s*NULL\b/i.test(c) && !/IS\s+NOT\s+NULL|IS\s+NULL/i.test(c.replace(/=\s*NULL/i, ''))) out.push({ line: i + 1, col: 1, severity: 'warning', message: t('sql_assign_null') });
    });
    var joined = all.join('\n');
    var re = /,\s*\)/g, m;
    while ((m = re.exec(joined)) !== null) {
      var line = joined.substring(0, m.index).split('\n').length;
      out.push({ line: line, col: 1, severity: 'error', message: t('sql_comma') });
    }
    var codeOnly = joined.replace(/\s+$/, '');
    if (codeOnly.trim() !== '' && !/;\s*$/.test(codeOnly)) out.push({ line: all.length, col: 1, severity: 'info', message: t('sql_semicolon') });
  }

  /* ---------------------------------------------------------------------------------------------
   * Format
   * ------------------------------------------------------------------------------------------- */
  var SQL_WORDS = ('SELECT FROM WHERE GROUP ORDER BY HAVING LIMIT OFFSET JOIN INNER LEFT RIGHT FULL OUTER CROSS ON AS AND OR NOT IN IS NULL LIKE BETWEEN EXISTS ' +
    'DISTINCT UNION ALL INSERT INTO VALUES UPDATE SET DELETE CREATE TABLE ALTER DROP ADD COLUMN PRIMARY FOREIGN REFERENCES UNIQUE CHECK DEFAULT CONSTRAINT ' +
    'INDEX VIEW CASCADE IF BEGIN COMMIT ROLLBACK CASE WHEN THEN ELSE END ASC DESC SERIAL BIGSERIAL INT INTEGER BIGINT SMALLINT VARCHAR CHAR BOOLEAN ' +
    'TIMESTAMP NUMERIC DECIMAL USING WITH RETURNING TRUE FALSE TRIGGER FUNCTION RETURNS FOR EACH ROW EXECUTE PROCEDURE DO').split(' ');
  var SQL_SET = {}; SQL_WORDS.forEach(function (w) { SQL_SET[w] = 1; });
  var SQL_AGG = { COUNT: 1, SUM: 1, AVG: 1, MIN: 1, MAX: 1, COALESCE: 1 };

  function spaceCode(s, fam, lang) {
    if (fam === 'sql') return s;
    s = s.replace(/[ \t]+/g, function (m, off) { return off === 0 ? m : ' '; });         // runs of spaces inside the line (the indent is rebuilt later)
    if (fam === 'brace' || fam === 'python') {
      s = s.replace(/,(?=[^\s,])/g, ', ');
      s = s.replace(/[ \t]+;/g, ';');
    }
    if (fam === 'brace') {
      s = s.replace(/\b(if|for|while|switch|catch|synchronized|foreach)\s*\(/g, '$1 (');
      s = s.replace(/([A-Za-z0-9_>\]])\{/g, '$1 {');
      s = s.replace(/\)\s*\{/g, ') {').replace(/\belse\s*\{/g, 'else {').replace(/\}\s*else\b/g, '} else').replace(/\}\s*(catch|finally)\b/g, '} $1');
      s = s.replace(/\s*(===|!==|==|!=|<=|>=|&&|\|\||\+=|-=|\*=|\/=|=>)\s*/g, ' $1 ');
      s = s.replace(/([A-Za-z0-9_\])"'])\s*=\s*(?![=>])/g, function (m, a, off) { var prev = s[off - 1]; return (prev && '=!<>+-*/%&|^'.indexOf(prev) !== -1) ? m : a + ' = '; });
      s = s.replace(/\{\s+\}/g, '{ }');
    }
    if (fam === 'python') {
      s = s.replace(/\s*(==|!=|<=|>=|\+=|-=|\*=|\/=|:=)\s*/g, ' $1 ');
    }
    return s;
  }

  function format(profile, code) {
    profile = profile || {};
    var fam = profile.family || 'brace';
    code = String(code == null ? '' : code).replace(/\r\n?/g, '\n').replace(/\t/g, '    ');
    if (code.trim() === '') return '';
    if (fam === 'sql') return formatSql(profile, code);
    var sc = scan(code, profile);
    var raw = code.split('\n');
    var out = [];
    var depth = 0;
    var caseOpen = [];                 // per brace level: is a case/default group open
    var unit;
    if (fam === 'python') {
      var widths = [], prev = 0;
      raw.forEach(function (l, i) { if (!l.trim() || sc.lines[i].inString || /^\s*#/.test(l)) return; var w = l.match(/^ */)[0].length; if (w > prev) widths.push(w - prev); prev = w; });
      unit = widths.length ? widths.reduce(function (a, b) { var x = a, y = b; while (y) { var r = x % y; x = y; y = r; } return x; }) : 4;
      if (unit < 1 || unit > 8) unit = 4;
    }
    var blanks = 0;
    var braceFam = fam === 'brace';
    var bdepth = 0;
    sc.lines.forEach(function (ln, i) {
      var text = raw[i] || '';
      if (ln.inString) { out.push(text.replace(/[ \t]+$/, '')); blanks = 0; return; }    // inside a multi-line string: untouched
      var trimmed = text.trim();
      if (trimmed === '') { blanks++; if (blanks === 1 && out.length) out.push(''); return; }
      blanks = 0;
      if (fam === 'python') {
        var lead = text.match(/^ */)[0].length;
        var inner = rebuild(ln, fam, profile, true);
        out.push(new Array(Math.round(lead * 4 / unit) + 1).join(' ') + inner);
        return;
      }
      if (!braceFam) { out.push(text.replace(/[ \t]+$/, '')); return; }
      // brace languages: indentation from the bracket depth
      var codePart = codeOf(ln);
      var firstCode = (codePart.match(/\S/) || [''])[0];
      var masked = maskOf(ln);
      var lead2 = 0;
      var m = /^\s*([)\]}]+)/.exec(masked);
      if (m) lead2 = m[1].length;
      var isLabel = /^\s*(case\b[^:]*:|default\s*:)/.test(codePart);
      var level = Math.max(0, depth - lead2);
      if (ln.inBlock && !/^\s*\/\*/.test(text)) {
        out.push(new Array(level * 4 + 1).join(' ') + (/^\s*\*/.test(text) ? ' ' : '') + trimmed);
      } else {
        var extra = 0;
        if (caseOpen[bdepth - (/^\s*\}/.test(masked) ? 1 : 0)] && !isLabel && !/^\s*\}/.test(masked)) extra = 1;
        var inner2 = rebuild(ln, fam, profile, false);
        out.push(new Array(level * 4 + extra * 4 + 1).join(' ') + inner2);
      }
      if (isLabel) caseOpen[bdepth] = true;
      for (var k = 0; k < masked.length; k++) {
        var c = masked[k];
        if (c === '{') { bdepth++; caseOpen[bdepth] = false; depth++; }
        else if (c === '(' || c === '[') depth++;
        else if (c === '}') { caseOpen[bdepth] = false; bdepth = Math.max(0, bdepth - 1); depth = Math.max(0, depth - 1); }
        else if (c === ')' || c === ']') depth = Math.max(0, depth - 1);
      }
    });
    while (out.length && out[out.length - 1] === '') out.pop();
    return out.join('\n') + '\n';
  }

  // The line again, with spacing rules applied to code segments only.
  function rebuild(ln, fam, profile, keepLead) {
    var parts = ln.segs.map(function (s, idx) {
      if (s.t !== 'code') return s.s;
      return spaceCode(s.s, fam, profile.lang);
    });
    var s = parts.join('');
    if (/<\?|\?>/.test(s)) return s.trim();
    return s.replace(/^[ \t]+/, '').replace(/[ \t]+$/, '');
  }

  function formatSql(profile, code) {
    var toks = sqlTokens(code);
    var out = '', depth = 0, createTable = false, stmtStart = true, lineStart = true, parenIsCreate = [];
    var CLAUSE = { FROM: 1, WHERE: 1, HAVING: 1, LIMIT: 1, UNION: 1, VALUES: 1, SET: 1, RETURNING: 1 };
    var JOINS = { INNER: 1, LEFT: 1, RIGHT: 1, FULL: 1, CROSS: 1, JOIN: 1 };
    var prevWord = '';
    function nl(ind) { out = out.replace(/[ ]+$/, ''); if (out !== '' && !/\n$/.test(out)) out += '\n'; out += new Array((ind || 0) + 1).join(' '); lineStart = true; }
    for (var i = 0; i < toks.length; i++) {
      var tk = toks[i];
      if (tk.t === 'ws') { if (!lineStart && !/[\s(]$/.test(out)) out += ' '; continue; }
      if (tk.t === 'com') { if (tk.s.indexOf('--') === 0) { if (!lineStart) out += ' '; out += tk.s; nl(depth ? 4 * depth : 0); } else { if (!lineStart) out += ' '; out += tk.s; lineStart = false; } continue; }
      if (tk.t === 'str' || tk.t === 'num') { out += tk.s; lineStart = false; prevWord = ''; continue; }
      if (tk.t === 'word') {
        var up = tk.s.toUpperCase();
        var prevCh = out.slice(-1);
        var isKw = SQL_SET[up] === 1 && prevCh !== '.';
        if (up === 'KEY' && (prevWord === 'PRIMARY' || prevWord === 'FOREIGN' || prevWord === 'UNIQUE')) isKw = true;
        if (SQL_AGG[up] && toks[i + 1] && toks[i + 1].s === '(') isKw = true;
        var w = isKw ? up : tk.s;
        if (depth === 0) {
          if ((CLAUSE[up] && prevWord !== 'DO') || ((up === 'GROUP' || up === 'ORDER') && toks.slice(i + 1).find(function (x) { return x.t !== 'ws'; }) && toks.slice(i + 1).find(function (x) { return x.t !== 'ws'; }).s.toUpperCase() === 'BY')
              || (JOINS[up] && !JOINS[prevWord] && up !== 'JOIN' || (up === 'JOIN' && !JOINS[prevWord]))) {
            if (!lineStart) nl(0);
          }
        }
        if (up === 'ON' && depth === 0 && prevWord !== 'DELETE' && stmtStart === false && /JOIN/.test(out.split('\n').slice(-2).join(' '))) { if (!lineStart) nl(4); }
        if ((up === 'SELECT' || up === 'INSERT' || up === 'UPDATE' || up === 'DELETE' || up === 'CREATE' || up === 'ALTER' || up === 'DROP') && depth === 0 && !lineStart && /;\s*$/.test(out.replace(/\s+$/, ''))) nl(0);
        if (up === 'CREATE') createTable = false;
        if (up === 'TABLE' && prevWord === 'CREATE') createTable = true;
        if (!lineStart && !/[\s(.]$/.test(out)) out += ' ';
        out += w; lineStart = false; prevWord = up; stmtStart = false; continue;
      }
      // punctuation
      var p = tk.s;
      if (p === '(') {
        var isCt = createTable && depth === 0;
        parenIsCreate.push(isCt);
        if (!lineStart && !/[\s(.]$/.test(out) && !/[A-Za-z0-9_"`]$/.test(out)) out += ' ';
        out += '(';
        depth++;
        if (isCt) nl(4);
        lineStart = isCt;
        continue;
      }
      if (p === ')') {
        var ct = parenIsCreate.pop();
        depth = Math.max(0, depth - 1);
        if (ct) { nl(0); out += ')'; createTable = false; } else { out = out.replace(/[ ]+$/, '') + ')'; }
        lineStart = false; continue;
      }
      if (p === ',') {
        out = out.replace(/[ ]+$/, '') + ',';
        if (createTable && depth === 1) nl(4); else out += ' ';
        lineStart = createTable && depth === 1;
        continue;
      }
      if (p === ';') { out = out.replace(/[ ]+$/, '') + ';'; nl(0); stmtStart = true; createTable = false; prevWord = ''; depth = 0; parenIsCreate = []; var nx = toks.slice(i + 1).find(function (x) { return x.t !== 'ws'; }); if (nx) out += '\n'; continue; }
      if (p === '*' && (/[(.]$/.test(out) || prevWord === 'SELECT' || prevWord === 'DISTINCT' || /,\s*$/.test(out))) {      // the star of SELECT * / COUNT(*) / t.*
        out += (/[(.\s]$/.test(out) ? '' : ' ') + '*'; lineStart = false; prevWord = ''; continue;
      }
      if (/^(=|<>|!=|<=|>=|<|>|\|\||\+|-|\*|\/|%)$/.test(p)) { out += (lineStart ? '' : ' ') + p + ' '; lineStart = false; continue; }
      out += p; lineStart = false;
    }
    return out.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').replace(/\s+$/, '') + '\n';
  }

  function sqlTokens(code) {
    var toks = [], i = 0, n = code.length;
    while (i < n) {
      var ch = code[i];
      if (/\s/.test(ch)) { var j = i; while (j < n && /\s/.test(code[j])) j++; toks.push({ t: 'ws', s: ' ' }); i = j; continue; }
      if (ch === '-' && code[i + 1] === '-') { var e = code.indexOf('\n', i); if (e < 0) e = n; toks.push({ t: 'com', s: code.substring(i, e).replace(/\s+$/, '') }); i = e; continue; }
      if (ch === '/' && code[i + 1] === '*') { var e2 = code.indexOf('*/', i + 2); e2 = e2 < 0 ? n : e2 + 2; toks.push({ t: 'com', s: code.substring(i, e2) }); i = e2; continue; }
      if (ch === "'" || ch === '"' || ch === '`') {
        var j2 = i + 1;
        while (j2 < n) { if (code[j2] === ch) { if (code[j2 + 1] === ch) { j2 += 2; continue; } j2++; break; } j2++; }
        toks.push({ t: 'str', s: code.substring(i, j2) }); i = j2; continue;
      }
      if (/[0-9]/.test(ch)) { var j3 = i; while (j3 < n && /[0-9.]/.test(code[j3])) j3++; toks.push({ t: 'num', s: code.substring(i, j3) }); i = j3; continue; }
      if (/[A-Za-z_]/.test(ch)) { var j4 = i; while (j4 < n && /[A-Za-z0-9_$]/.test(code[j4])) j4++; toks.push({ t: 'word', s: code.substring(i, j4) }); i = j4; continue; }
      var two = code.substr(i, 2);
      if (['<>', '!=', '<=', '>=', '||', '::'].indexOf(two) !== -1) { toks.push({ t: 'p', s: two === '::' ? '::' : two }); i += 2; continue; }
      toks.push({ t: 'p', s: ch }); i++;
    }
    return toks;
  }

  /* ---------------------------------------------------------------------------------------------
   * Syntax highlighting: HTML for the colour layer under the editor. Only colours change, never the width of a character,
   * and the text of the result is exactly the code, so the layer always lines up with the textarea.
   * ------------------------------------------------------------------------------------------- */
  var KW = {
    java: 'abstract assert boolean break byte case catch char class const continue default do double else enum extends final finally float for goto if implements import instanceof int interface long native new package private protected public return short static super switch synchronized this throw throws transient try void volatile while var record yield null true false',
    c: 'auto break case char const continue default do double else enum extern float for goto if inline int long register return short signed sizeof static struct switch typedef union unsigned void volatile while NULL',
    cpp: 'auto bool break case catch char class const constexpr continue default delete do double else enum explicit extern false float for friend goto if inline int long namespace new nullptr operator private protected public return short signed sizeof static struct switch template this throw true try typedef typename union unsigned using virtual void volatile while override',
    csharp: 'abstract as async await base bool break byte case catch char class const continue decimal default delegate do double else enum event explicit extern false finally float for foreach if implicit in int interface internal is lock long namespace new null object operator out override params private protected public readonly record ref return sealed short sizeof static string struct switch this throw true try typeof uint ulong using var virtual void volatile while',
    javascript: 'async await break case catch class const continue default delete do else export extends false finally for from function get if implements import in instanceof let new null of private protected public return set static super switch this throw true try typeof undefined var void while yield',
    typescript: 'abstract as async await boolean break case catch class const continue declare default delete do else enum export extends false finally for from function get if implements import in instanceof interface keyof let namespace new null number of private protected public readonly return set static string super switch this throw true try type typeof undefined var void while yield',
    php: 'abstract and array as break callable case catch class clone const continue declare default do echo else elseif empty extends false final finally fn for foreach function global if implements include instanceof interface isset list match namespace new null or parent print private protected public readonly require return self static switch this throw trait true try unset use var while yield',
    python: 'and as assert async await break class continue def del elif else except False finally for from global if import in is lambda None nonlocal not or pass raise return True try while with yield match case self',
    go: 'break case chan const continue default defer else fallthrough for func go goto if import interface map package range return select struct switch type var nil true false',
    rust: 'as async await break const continue crate dyn else enum extern false fn for if impl in let loop match mod move mut pub ref return self Self static struct super trait true type unsafe use where while',
    bash: 'if then else elif fi for while until do done case esac function in return exit local export echo read set unset shift',
    sql: 'SELECT FROM WHERE GROUP BY ORDER HAVING LIMIT OFFSET JOIN INNER LEFT RIGHT FULL OUTER CROSS ON AS AND OR NOT IN IS NULL LIKE BETWEEN EXISTS DISTINCT UNION ALL INSERT INTO VALUES UPDATE SET DELETE CREATE TABLE ALTER DROP ADD COLUMN PRIMARY FOREIGN KEY REFERENCES UNIQUE CHECK DEFAULT CONSTRAINT INDEX VIEW CASCADE IF BEGIN COMMIT ROLLBACK CASE WHEN THEN ELSE END ASC DESC USING WITH RETURNING TRUE FALSE TRIGGER FUNCTION RETURNS FOR EACH ROW EXECUTE PROCEDURE'
  };
  var SQL_TYPES = ' SERIAL BIGSERIAL INT INTEGER BIGINT SMALLINT VARCHAR CHAR TEXT BOOLEAN DATE TIMESTAMP NUMERIC DECIMAL FLOAT DOUBLE REAL UUID JSON JSONB ';
  var KWSET = {};
  function kwSet(lang) {
    if (KWSET[lang]) return KWSET[lang];
    var o = {};
    (KW[lang] || '').split(' ').forEach(function (w) { if (w) o[lang === 'sql' ? w.toUpperCase() : w] = 1; });
    return (KWSET[lang] = o);
  }
  function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  function highlight(profile, code) {
    profile = profile || {};
    code = String(code == null ? '' : code).replace(/\r\n?/g, '\n');
    var lang = profile.lang || 'other', sql = lang === 'sql';
    var kws = kwSet(lang), out = [];
    scan(code, profile).lines.forEach(function (ln) {
      var h = '';
      ln.segs.forEach(function (sg) {
        if (sg.t === 'com') { h += '<span class="tk-c">' + esc(sg.s) + '</span>'; return; }
        if (sg.t === 'str') { h += '<span class="tk-s">' + esc(sg.s) + '</span>'; return; }
        var re = /(@[A-Za-z_]\w*)|(\b\d[\d_]*(?:\.\d+)?(?:[eE][+-]?\d+)?\b)|([A-Za-z_$][\w$]*)|([\s\S])/g, m, src = sg.s;
        while ((m = re.exec(src)) !== null) {
          if (m[1]) h += '<span class="tk-a">' + esc(m[1]) + '</span>';
          else if (m[2]) h += '<span class="tk-n">' + esc(m[2]) + '</span>';
          else if (m[3]) {
            var w = m[3], key = sql ? w.toUpperCase() : w, cls = '';
            if (kws[key]) cls = 'tk-k';
            else if (sql) { if (SQL_TYPES.indexOf(' ' + key + ' ') !== -1) cls = 'tk-t'; }
            else if (/^\s*\(/.test(src.substr(re.lastIndex))) cls = 'tk-f';
            else if (/^[A-Z][A-Za-z0-9_]*[a-z][A-Za-z0-9_]*$/.test(w)) cls = 'tk-t';
            else if (/^[A-Z][A-Z0-9_]+$/.test(w)) cls = 'tk-n';
            h += cls ? '<span class="' + cls + '">' + esc(w) + '</span>' : esc(w);
          } else h += esc(m[4]);
        }
      });
      out.push(h);
    });
    return out.join('\n');
  }

  root.CodeTools = { lint: lint, format: format, highlight: highlight, setLang: function (l) { LANG = l === 'en' ? 'en' : 'fr'; }, scan: scan, _msg: t };
  if (typeof module !== 'undefined' && module.exports) module.exports = root.CodeTools;
})(typeof window !== 'undefined' ? window : globalThis);
