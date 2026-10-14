# -*- coding: utf-8 -*-
import csv, io, os, sys, zipfile, collections
sys.path.insert(0, '.')
import questions_data as D

OUT = 'INF331_evaluation_pack.zip'
import random
# Balance the position of the correct answer over A-D (deterministic), keeping the distractors in their written order
mcq4 = [q for q in D.Q if q['kind'] == 'mcq']
targets = ['A', 'B', 'C', 'D'] * (len(mcq4) // 4 + 1)
targets = targets[:len(mcq4)]
rng = random.Random(331)
while True:
    rng.shuffle(targets)
    if all(targets[i] != targets[i + 1] or i % 7 == 0 for i in range(len(targets) - 1)): break
for q, t in zip(mcq4, targets):
    cur = 'ABCD'.index(q['ans'])
    correct = q['opts'][cur]
    others = [x for i, x in enumerate(q['opts']) if i != cur]
    pos = 'ABCD'.index(t)
    q['opts'] = others[:pos] + [correct] + others[pos:]
    q['ans'] = t

rows = []
for q in D.Q:
    o = q['opts'] or []
    if q['kind'] == 'written':
        typ, a, b, c, d = 'written', '', '', '', ''
    else:
        typ = 'mcq'
        a, b, c, d = (o + ['', '', '', ''])[:4]
        assert (q['kind'] == 'tf' and len(o) == 2 and q['ans'] in 'AB') or (q['kind'] == 'mcq' and len(o) == 4 and q['ans'] in 'ABCD'), q['text'][:60]
    rows.append([q['text'], typ, a, b, c, d, q['ans'], q['expl'], q['img'], q['t']])

buf = io.StringIO()
w = csv.writer(buf, quoting=csv.QUOTE_ALL, lineterminator='\n')
w.writerow(['question', 'type', 'option_a', 'option_b', 'option_c', 'option_d', 'correct', 'explanation', 'image', 'time_limit'])
for r in rows: w.writerow(r)
csv_text = '﻿' + buf.getvalue()
open('questions.csv', 'w', encoding='utf-8').write(csv_text)

used = sorted({q['img'] for q in D.Q if q['img']})
missing = [u for u in used if not os.path.exists('images/' + u)]
assert not missing, missing
readme = """INF331 - Approche OO pour la modelisation des SI : evaluation de 65 questions (niveau difficile)

Contenu : questions.csv (65 lignes) et images/ (24 figures UML).
Import : espace enseignant > evaluation en direct > importer un pack (ZIP).

Repartition : 45 QCM, 3 vrai/faux, 20 reponses ecrites ; 33 questions en francais, 32 en anglais ; 24 questions avec figure.
Reponses ecrites : plusieurs reponses acceptees separees par |, la casse et les espaces sont ignores, la virgule vaut le point.
Chaque question a son temps (45 a 150 s) et une correction detaillee. Tous les extraits Java ont ete compiles et executes pour verifier les sorties.
"""
with zipfile.ZipFile(OUT, 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr('questions.csv', csv_text)
    for u in used: z.write('images/' + u, 'images/' + u)
    z.writestr('LISEZMOI.txt', readme)

n = len(D.Q)
print('questions', n, '| images used', len(used), '| zip', os.path.getsize(OUT) // 1024, 'KB')
print('lang', dict(collections.Counter(q['lang'] for q in D.Q)))
print('kind', dict(collections.Counter(q['kind'] for q in D.Q)))
print('with image', sum(1 for q in D.Q if q['img']), '| total seconds', sum(q['t'] for q in D.Q), '=', round(sum(q['t'] for q in D.Q) / 60), 'min')
print('answer letters', dict(collections.Counter(q['ans'] for q in D.Q if q['kind'] != 'written')))
