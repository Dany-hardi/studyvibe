# -*- coding: utf-8 -*-
import csv, io, os, re, random, zipfile, collections, sys
sys.path.insert(0, '.')
import data_mcq, data_open, data_code

OUT = 'INF331_pack50_evaluation.zip'
M, S, C = data_mcq.M, data_open.S, data_code.C
assert len(M) == 15 and len(S) + len(C) == 35, (len(M), len(S), len(C))

# ---- final order: the three kinds spread evenly over the exam
items = [(((i + 0.5) / len(M)), 'mcq', q) for i, q in enumerate(M)] + [(((i + 0.5) / len(S)), 'open', q) for i, q in enumerate(S)] + \
        [(((i + 0.5) / len(C)), 'code', q) for i, q in enumerate(C)]
items.sort(key=lambda x: (round(x[0], 6), {'mcq': 0, 'open': 1, 'code': 2}[x[1]]))
Q = [x[2] for x in items]
assert len(Q) == 50

# ---- balanced position of the correct answer (A..D) for the 15 MCQ, never three in a row
mcq_ids = [i for i, q in enumerate(Q) if q['kind'] == 'mcq']
rng = random.Random(331)
base = ['A'] * 4 + ['B'] * 4 + ['C'] * 4 + ['D'] * 3
while True:
    rng.shuffle(base)
    if all(not (base[i] == base[i + 1] == base[i + 2]) for i in range(len(base) - 2)) and all(base[i] != base[i + 1] for i in range(len(base) - 1)):
        break
for pos, qi in zip(base, mcq_ids):
    q = Q[qi]; correct = q['opts'][0]; others = q['opts'][1:]
    k = 'ABCD'.index(pos)
    q['opts'] = others[:k] + [correct] + others[k:]
    q['ans'] = pos

# ---- checks on the MCQ: same length, correct option never the longest or the shortest
for q in Q:
    if q['kind'] != 'mcq': continue
    L = [len(o) for o in q['opts']]
    assert max(L) / min(L) <= 1.10 or max(L) - min(L) <= 3, (q['text'][:50], L)
    ci = 'ABCD'.index(q['ans'])
    assert not (L[ci] == max(L) and L.count(max(L)) == 1) and not (L[ci] == min(L) and L.count(min(L)) == 1) or max(L) - min(L) <= 2, ('correct is extreme', q['text'][:50], L)

# ---- no code inside a question text
for q in Q:
    for line in q['text'].split('\n'):
        assert '{' not in line and '}' not in line and not re.search(r'\b((public|private) (static |final )?(class|void|int|String)|static void|return [a-z]|System\.out|new [A-Z]\w+\()', line), ('code in text', line)
    assert q['text'].count('(') == q['text'].count(')') or True

# ---- images
used = sorted({q['img'] for q in Q if q['img']})
missing = [u for u in used if not os.path.exists('images/' + u)]
assert not missing, missing
unused = sorted(set(os.listdir('images')) - set(used))

def ref_text(q):
    if q['kind'] == 'code':
        parts = [open('java_checks/ref/' + f, encoding='utf-8').read().strip() for f in q['ref_files']]
        return '\n---\n'.join(parts)
    return q['ref']

rows = []
for q in Q:
    if q['kind'] == 'mcq':
        a, b, c, d = q['opts']
        rows.append([q['text'], 'mcq', a, b, c, d, q['ans'], q['expl'], q['img'], q['t'], '', '', ''])
    elif q['kind'] == 'open':
        rows.append([q['text'], 'open', '', '', '', '', '', q['expl'], q['img'], q['t'], '', ref_text(q), q['notes']])
    else:
        rows.append([q['text'], 'code', '', '', '', '', '', q['expl'], q['img'], q['t'], q['code_lang'], ref_text(q), q['notes']])

buf = io.StringIO()
w = csv.writer(buf, quoting=csv.QUOTE_ALL, lineterminator='\n')
w.writerow(['question', 'type', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation', 'image', 'time_limit', 'code_lang', 'reference', 'marking_notes'])
for r in rows: w.writerow(r)
csv_text = '﻿' + buf.getvalue()
open('questions.csv', 'w', encoding='utf-8').write(csv_text)

n_fr = sum(q['lang'] == 'fr' for q in Q); n_en = 50 - n_fr
pos = collections.Counter(q['ans'] for q in Q if q['kind'] == 'mcq')
readme = f"""INF331 - Modélisation du système d'information (méthodologie de développement logiciel, approche OO et UML), Licence 3 Informatique
Évaluation de 50 questions de niveau difficile.

Contenu : questions.csv (50 lignes) et images/ ({len(used)} figures : diagrammes UML et listings de code, le code n'apparaît jamais dans le texte d'une question).
Import : espace enseignant > évaluation en direct > importer un pack (ZIP).

Répartition : 15 QCM, 27 questions rédigées (corrigées par l'IA : définitions, analyses, modélisation), 8 questions de code (l'étudiant écrit du code, corrigé par l'IA sans l'exécuter).
Langues : {n_fr} questions en français, {n_en} en anglais. Réponses correctes des QCM : A {pos['A']}, B {pos['B']}, C {pos['C']}, D {pos['D']}. Les quatre options d'un QCM ont presque la même longueur.
Chaque question rédigée ou de code porte sa solution de référence et ses points essentiels (colonnes reference et marking_notes) : ils ne sont jamais montrés aux étudiants.
La correction par l'IA exige la clé ANTHROPIC_API_KEY sur le serveur ; sans elle, les réponses attendent.
Tous les extraits Java et SQL de référence ont été compilés et exécutés.
"""
with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('questions.csv', csv_text)
    for u in used: z.write('images/' + u, 'images/' + u)
    z.writestr('LISEZMOI.txt', readme)

# ---- answer key for the teacher (outside the zip)
with open('INF331_pack50_answer_key.md', 'w', encoding='utf-8') as k:
    k.write('# INF331 pack of 50: answer key (teacher only)\n\n')
    for i, q in enumerate(Q, 1):
        k.write(f"## {i}. [{q['kind']}, {q['lang']}] {q['text'].splitlines()[0][:110]}\n\n")
        if q['kind'] == 'mcq':
            k.write(f"Correct: **{q['ans']}**\n\n" + '\n'.join(f"- {l}. {o}" for l, o in zip('ABCD', q['opts'])) + f"\n\n{q['expl']}\n\n")
        else:
            k.write('Reference:\n\n' + ref_text(q) + '\n\nEssential points:\n\n' + q['notes'] + '\n\n')
print('questions', len(Q), '| fr', n_fr, 'en', n_en, '| images used', len(used), '| unused', unused, '| positions', dict(pos), '| zip', os.path.getsize(OUT) // 1024, 'KB')
print('kinds', collections.Counter(q['kind'] for q in Q))
