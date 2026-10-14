// node tests/js/code_tools.test.js
const CT = require('../../assets/js/code-tools.js');
let pass = 0, fail = 0;
const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL ' + m); } };
const P = (lang, extra) => Object.assign({
  java: { lang: 'java', family: 'brace', line_comment: '//', semicolons: true, strings: ['"', "'"], max_line: 120 },
  js: { lang: 'javascript', family: 'brace', line_comment: '//', semicolons: false, strings: ['"', "'", '`'], max_line: 120 },
  php: { lang: 'php', family: 'brace', line_comment: '//', semicolons: true, strings: ['"', "'"], max_line: 120 },
  py: { lang: 'python', family: 'python', line_comment: '#', block_comment: false, strings: ['"', "'"], max_line: 120 },
  sql: { lang: 'sql', family: 'sql', line_comment: '--', strings: ["'"], semicolons: true, max_line: 120 },
  c: { lang: 'c', family: 'brace', line_comment: '//', semicolons: true, strings: ['"', "'"], max_line: 120 },
}[lang], extra || {});
const msgs = (p, code) => CT.lint(p, code).map(x => x.severity + ':' + x.line);
const errs = (p, code) => CT.lint(p, code).filter(x => x.severity === 'error');

// ---- lint: clean code has no error
ok(errs(P('java'), 'class A {\n  int f(int a) {\n    return a + 1;\n  }\n}\n').length === 0, 'clean java');
ok(errs(P('java'), 'String s = "a{b(";\n// comment with ( and {\nchar c = \'}\';\n').length === 0, 'brackets inside strings/comments ignored');
ok(errs(P('java'), '/* ( [ { */\nint x = 1;\n').length === 0, 'brackets inside block comment ignored');
// ---- brackets
let e = errs(P('java'), 'class A {\n  void f() {\n    g(1, 2;\n  }\n}\n');
ok(e.length >= 1 && e.some(x => /ouvert/.test(x.message)), 'mismatch / unclosed paren reported');
e = errs(P('java'), 'class A {\n  void f() {\n  }\n');
ok(e.length === 1 && e[0].line === 1, 'unclosed brace points at the opener: ' + JSON.stringify(e));
e = errs(P('java'), 'int a = 1;\n}\n');
ok(e.length === 1 && e[0].line === 2, 'stray closing brace');
e = errs(P('java'), 'String s = "abc;\nint x = 1;\n');
ok(e.length >= 1 && e[0].line === 1 && /Chaîne/.test(e[0].message), 'unterminated string on its line');
e = errs(P('java'), '/* never closed\nint x;\n');
ok(e.length === 1 && /Commentaire/.test(e[0].message), 'unterminated block comment');
ok(errs(P('js'), 'const s = `a\nb ${x}`;\nconst r = /[(\\]]+/;\n').length === 0, 'template literal and regex literal');
// ---- semicolons
let w = CT.lint(P('java'), 'class A {\n  int f(int a) {\n    int b = a * 2\n    return b\n  }\n}\n').filter(x => x.severity === 'warning');
ok(w.length === 2 && w[0].line === 3 && w[1].line === 4, 'missing semicolons: ' + JSON.stringify(w));
w = CT.lint(P('java'), 'class A {\n  int f(int a) {\n    return a +\n       1;\n  }\n  void g() {\n    System.out.println("x")\n  }\n}\n').filter(x => x.severity === 'warning');
ok(w.length === 1 && w[0].line === 7, 'continuation lines not flagged, println without ; flagged: ' + JSON.stringify(w));
ok(CT.lint(P('js'), 'let a = 1\nlet b = 2\n').filter(x => x.severity === 'warning').length === 0, 'JS has no semicolon rule');
// ---- python
e = errs(P('py'), 'def f(a):\n    if a > 1\n        return 1\n    return 0\n');
ok(e.length === 1 && e[0].line === 2 && /Deux-points/.test(e[0].message), 'python missing colon: ' + JSON.stringify(e));
e = errs(P('py'), 'def f(a):\n    x = 1\n      y = 2\n');
ok(e.some(x => x.line === 3 && /Indentation inattendue/.test(x.message)), 'python unexpected indent');
e = errs(P('py'), 'def f(a):\n        x = 1\n    y = 2\n');
ok(e.some(x => x.line === 3 && /incohérente/.test(x.message)), 'python bad dedent');
e = errs(P('py'), 'if x = 3:\n    pass\n');
ok(e.length === 1 && /==/.test(e[0].message), 'python assignment in condition');
ok(errs(P('py'), 'def f(a):\n    s = """doc\n  x\n"""\n    return {\n        "k": 1,\n    }\n').length === 0, 'python clean: triple string and multi-line dict');
ok(errs(P('py'), 'x = [1, 2,\n     3]\nfor i in x: print(i)\n').length === 0, 'python one-liner block and continuation');
// ---- sql
e = errs(P('sql'), "CREATE TABLE t (\n  id INT,\n  name TEXT,\n);\n");
ok(e.length === 1 && e[0].line === 3 || e.some(x => /Virgule/.test(x.message)), 'sql trailing comma: ' + JSON.stringify(e));
e = errs(P('sql'), "SELECT * FROM t WHERE a == 1;\n");
ok(e.length === 1 && /==/.test(e[0].message), 'sql ==');
e = errs(P('sql'), "SELECT * FROM t WHERE a = 'x;\n");
ok(e.length >= 1, 'sql unterminated quote');
ok(errs(P('sql'), "SELECT 'it''s ok', \"a\" FROM t;\n").length === 0, 'sql doubled quote');
ok(CT.lint(P('sql'), 'SELECT 1').some(x => x.severity === 'info' && /point-virgule/.test(x.message)), 'sql semicolon hint');
// ---- general
ok(CT.lint(P('java'), 'int a = 1;   \n').some(x => /fin de ligne/.test(x.message)), 'trailing whitespace');
ok(CT.lint(P('java'), 'x'.repeat(130)).some(x => /trop longue/.test(x.message)), 'long line');
ok(CT.lint(P('java'), '').length === 0 && CT.lint(P('java'), '   \n').length === 0, 'empty code: nothing');

// ---- format: java
let f = CT.format(P('java'), 'class A{\nint f(int a){\nif(a>1){\nreturn a;\n}else{\nreturn 0;\n}\n}\n}\n');
ok(f === 'class A {\n    int f(int a) {\n        if (a>1) {\n            return a;\n        } else {\n            return 0;\n        }\n    }\n}\n', 'java format:\n' + f);
ok(CT.format(P('java'), f) === f, 'java format is idempotent');
f = CT.format(P('java'), 'void g(){\n  String s = "if(a>1){x=1}";   // keep (a,b)  \n  int a=1,b=2;\n}\n');
ok(/"if\(a>1\)\{x=1\}"/.test(f) && /\/\/ keep \(a,b\)/.test(f) && /int a = 1, b = 2;/.test(f), 'strings and comments untouched, code spaced:\n' + f);
f = CT.format(P('java'), 'switch (x) {\ncase 1:\nfoo();\nbreak;\ndefault:\nbar();\n}\n');
ok(f === 'switch (x) {\n    case 1:\n        foo();\n        break;\n    default:\n        bar();\n}\n', 'switch/case:\n' + f);
f = CT.format(P('java'), '/**\n * doc\n */\nclass A {\n\n\n\nint x;\n}\n');
ok(f === '/**\n * doc\n */\nclass A {\n\n    int x;\n}\n', 'block comment and blank lines:\n' + JSON.stringify(f));
f = CT.format(P('js'), 'const f = (a,b) =>{\nreturn a+b\n}\n');
ok(/\(a, b\) => \{/.test(f) && /^ {4}return a\+b$/m.test(f), 'js format:\n' + f);
// ---- format: python
f = CT.format(P('py'), 'def f(a):\n  if a:\n    return [1,2]\n  return 0\n');
ok(f === 'def f(a):\n    if a:\n        return [1, 2]\n    return 0\n', 'python 2-space to 4-space:\n' + JSON.stringify(f));
f = CT.format(P('py'), 'def f(a):\n\tx=1\n\treturn x\n');
ok(/^    x=1$/m.test(f) || /^    x = 1$/m.test(f), 'python tabs -> spaces');
// ---- format: sql
f = CT.format(P('sql'), "select a,b from t where a=1 order by b desc;");
ok(f === "SELECT a, b\nFROM t\nWHERE a = 1\nORDER BY b DESC;\n", 'sql select:\n' + JSON.stringify(f));
f = CT.format(P('sql'), "create table owner (id bigserial primary key, email varchar(190) not null unique, full_name varchar(120) not null);");
ok(f === "CREATE TABLE owner (\n    id BIGSERIAL PRIMARY KEY,\n    email VARCHAR(190) NOT NULL UNIQUE,\n    full_name VARCHAR(120) NOT NULL\n);\n", 'sql create table:\n' + JSON.stringify(f));
ok(/'select from'/.test(CT.format(P('sql'), "select 'select from' as x;")), 'sql strings untouched');
f = CT.format(P('sql'), "SELECT t.date, count(*) FROM t GROUP BY t.date;");
ok(/t\.date/.test(f) && /COUNT\(\*\)/.test(f), 'sql dotted identifiers and aggregates:\n' + f);
ok(errs(P('sql'), "CREATE TABLE v (t CHAR(1) CHECK (t IN ('C', 'T')), p TEXT);\n").length === 0, 'sql: values inside strings are not a stray comma');
// ---- highlight: colours only, the text is unchanged
const strip = h => h.replace(/<[^>]+>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const samples = {
  java: 'public class A<T> {\n  // c <b>\n  String s = "x<y>&z";\n  @Override int f(int a) { return a + 42; }\n}',
  py: 'def f(a):\n    """doc"""\n    return a # c\n',
  sql: "SELECT a, COUNT(*) FROM t WHERE x = 'it''s' -- c\n;",
  js: 'const r = /a"b/; let s = `x\ny`;',
};
for (const [k, c] of Object.entries(samples)) ok(strip(CT.highlight(P(k), c)) === c, 'highlight keeps the text of ' + k);
let h = CT.highlight(P('java'), 'public int f() { return 1; } // note "q"');
ok(/<span class="tk-k">public<\/span>/.test(h) && /<span class="tk-k">return<\/span>/.test(h) && /<span class="tk-n">1<\/span>/.test(h) && /<span class="tk-c">\/\/ note "q"<\/span>/.test(h) && /<span class="tk-f">f<\/span>/.test(h), 'java colours: ' + h);
h = CT.highlight(P('java'), 'String s = "if (x) { return }";');
ok(!/tk-k">if/.test(h) && /<span class="tk-s">"if \(x\) \{ return \}"<\/span>/.test(h) && /<span class="tk-t">String<\/span>/.test(h), 'keywords inside a string stay a string: ' + h);
h = CT.highlight(P('py'), 'def f(a):\n    return None  # fin');
ok(/tk-k">def/.test(h) && /tk-k">None/.test(h) && /tk-c"># fin/.test(h), 'python keywords: ' + h);
h = CT.highlight(P('sql'), "select a from t where b = 'x';");
ok(/tk-k">select/.test(h) && /tk-k">from/.test(h) && /tk-s">'x'/.test(h), 'sql keywords in any case: ' + h);
console.log(pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
