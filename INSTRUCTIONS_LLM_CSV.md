# Guide de Génération de Questions CSV pour StudyVibe (Support LaTeX & Explications)

Ce document sert de **référence et de prompt universel** à soumettre à un grand modèle de langage (comme Claude 3.5 Sonnet) pour générer des banques de questions d'examen au format CSV 100% compatibles avec le système d'importation de StudyVibe.

---

## 1. Structure du Fichier CSV

Le fichier CSV importable doit comporter exactement **7 colonnes** avec l'en-tête suivant :

```csv
question,option_a,option_b,option_c,option_d,correct,explanation
```

### Détail des Colonnes
1. **`question`** : Énoncé de la question. Peut contenir du texte enrichi et des formules mathématiques LaTeX.
2. **`option_a`** : Option de réponse A.
3. **`option_b`** : Option de réponse B.
4. **`option_c`** : Option de réponse C.
5. **`option_d`** : Option de réponse D.
6. **`correct`** : Lettre en majuscule correspondant à la bonne réponse (`A`, `B`, `C` ou `D`).
7. **`explanation`** : Explication détaillée, justification de la bonne réponse, ou correction complète étape par étape (avec support LaTeX).

---

## 2. Règles de Formatage Strictes (Impératif de compatibilité)

> [!IMPORTANT]
> Tout non-respect de ces règles de syntaxe entraînera l'échec de l'importateur automatique de StudyVibe.

* **Délimiteurs et Guillemets** :
  * Chaque champ textuel (énoncé, options, explications) **doit être entouré de guillemets doubles** (ex: `"Mon texte"`).
  * Si vous devez insérer des guillemets doubles à l'intérieur d'un champ, ils doivent être **doublés** pour être échappés (ex: `"Le théorème dit ""Tout est relatif"" en physique"`).
* **Une Ligne par Question (Pas de retours à la ligne)** :
  * Le CSV ne doit contenir **aucun retour à la ligne physique** (`\n`) à l'intérieur des cellules de texte. Chaque question doit tenir sur **une seule ligne physique** dans le fichier.
  * Pour ajouter un saut de ligne visuel dans la question ou dans l'explication, utilisez la balise HTML `<br>` ou `\n` échappée dans la chaîne (l'utilisation de `<br>` est recommandée pour le rendu HTML du LMS).
* **Syntaxe LaTeX / KaTeX** :
  * Les formules mathématiques en ligne doivent être encadrées par un simple symbole dollar : `$formule$`. Exemple : `$x^2 + y^2 = z^2$`.
  * Les formules mathématiques en bloc (centrées et de grande taille) doivent être encadrées par des doubles symboles dollar : `$$formule$$`. Exemple : `$$\lim_{x \to \infty} \frac{1}{x} = 0$$`.
  * Toutes les commandes LaTeX standard (fractions `\frac`, intégrales `\int`, matrices, lettres grecques `\alpha`, `\beta`, etc.) sont supportées et interprétées à la fois dans les énoncés, les options et la colonne `explanation`.

---

## 3. Ligne directrice pour la colonne `explanation` par type de matière

### A. Mathématiques & Sciences Exactes
* **Exigence** : Fournir une **correction complète et rigoureuse étape par étape**.
* **Contenu** : Rappel de la formule ou du théorème utilisé, calcul détaillé des étapes intermédiaires, simplification, et conclusion claire.
* **LaTeX** : Utilisation intensive du mode mathématique pour toutes les expressions numériques et démonstrations.

### B. Physique & Chimie
* **Exigence** : Explication de la loi physique mise en jeu et application numérique détaillée.
* **Contenu** : Rappel des unités de mesure, conversion si nécessaire, manipulation de la formule (ex: isoler la variable $v$ dans $E_c = \frac{1}{2}mv^2$), et justification de la bonne valeur.

### C. Informatique & Algorithmique
* **Exigence** : Déroulement logique du code ou explication du concept théorique.
* **Contenu** : Trace d'exécution étape par étape (valeurs des variables à chaque itération), justification de la complexité algorithmique (ex: $O(n \log n)$), ou explication du protocole réseau concerné.

### D. Sciences Humaines, Langues & Culture générale
* **Exigence** : Justification historique, littéraire ou logique.
* **Contenu** : Contexte historique (dates clés, acteurs principaux), citation littéraire, ou explication de la règle grammaticale avec contre-exemples pour expliquer pourquoi les autres options sont fausses.

---

## 4. Exemples Pratiques de Fichiers CSV

Voici des exemples concrets de lignes CSV prêtes à l'importation :

### Mathématiques
```csv
question,option_a,option_b,option_c,option_d,correct,explanation
"Soit la fonction $f(x) = 3x^2 + 5x - 2$. Quelle est l'équation de la tangente à la courbe de $f$ au point d'abscisse $x = 1$ ?","y = 11x - 5","y = 11x + 6","y = 6x + 10","y = 11x - 11","A","Pour trouver l'équation de la tangente au point $x = a$, on utilise la formule : $y = f'(a)(x-a) + f(a)$. <br>1. Calculons la dérivée : $f'(x) = 6x + 5$. <br>2. Évaluons en $a = 1$ : $f'(1) = 6(1) + 5 = 11$. <br>3. Évaluons la fonction en $a = 1$ : $f(1) = 3(1)^2 + 5(1) - 2 = 6$. <br>4. Remplaçons dans la formule : $y = 11(x-1) + 6 \implies y = 11x - 11 + 6 \implies y = 11x - 5$."
```

### Physique / Chimie
```csv
question,option_a,option_b,option_c,option_d,correct,explanation
"Quelle est la concentration molaire d'une solution contenant $0.5\text{ mol}$ de soluté dissous dans $250\text{ mL}$ de solvant ?","0.2 mol/L","1.25 mol/L","2.0 mol/L","5.0 mol/L","C","La concentration molaire $C$ est donnée par la relation $C = \frac{n}{V}$. <br>Ici, la quantité de matière est $n = 0.5\text{ mol}$. <br>Le volume doit être converti en litres : $V = 250\text{ mL} = 0.25\text{ L}$. <br>Ainsi, $C = \frac{0.5}{0.25} = 2.0\text{ mol/L}$."
```

### Informatique
```csv
question,option_a,option_b,option_c,option_d,correct,explanation
"Quelle est la complexité temporelle dans le pire des cas de l'algorithme QuickSort ?","$O(n)$","$O(n \log n)$","$O(n^2)$","$O(2^n)$","C","Bien que la complexité moyenne de QuickSort soit de $O(n \log n)$, dans le pire des cas (par exemple, si le tableau est déjà trié et que le premier ou le dernier élément est systématiquement choisi comme pivot), l'algorithme effectue des partitions très déséquilibrées, menant à une complexité quadratique de $O(n^2)$."
```

---

## 5. Le Prompt Ultime pour Claude (Copier-coller)

Copiez le texte ci-dessous et envoyez-le à Claude pour générer vos évaluations sur mesure :

```text
Tu es un concepteur pédagogique expert et un rédacteur académique de haut niveau. Ta tâche consiste à rédiger une banque de questions QCM de niveau universitaire/professionnel sur le sujet suivant : [INSERER LE SUJET ICI].

Tu dois me renvoyer UNIQUEMENT un bloc de code au format CSV brut contenant les questions, sans texte d'introduction ni de conclusion.

### RÈGLES STRICTES DE FORMATAGE DU CSV :
1. En-tête : La première ligne doit être exactement la suivante :
   question,option_a,option_b,option_c,option_d,correct,explanation

2. Colonnes : Chaque ligne doit comporter exactement 7 colonnes séparées par des virgules :
   - Colonne 1 : Énoncé de la question
   - Colonne 2 : Option A
   - Colonne 3 : Option B
   - Colonne 4 : Option C
   - Colonne 5 : Option D
   - Colonne 6 : Bonne réponse (uniquement la lettre en MAJUSCULE : A, B, C ou D)
   - Colonne 7 : Explication / Justification complète avec support mathématique.

3. Guillemets et Échappement :
   - Chaque champ DOIT être entouré de doubles guillemets (ex: "champ").
   - Les guillemets internes doivent être doublés (ex: "Le terme ""concept"" est utilisé").

4. Ligne Physique Unique :
   - Interdiction formelle d'inclure des retours à la ligne réels (\n) dans les questions ou explications. Tout doit tenir sur une seule ligne physique par question.
   - Utilise "<br>" pour insérer des sauts de ligne visuels dans l'énoncé ou l'explication.

5. Support LaTeX & Mathématiques (KaTeX) :
   - Utilise LaTeX pour TOUTES les formules mathématiques, variables et notations techniques.
   - Encadre par '$' pour les formules en ligne (ex: $f(x) = x^2$) et par '$$' pour les blocs de formules isolées.
   - Les explications doivent utiliser LaTeX pour détailler toutes les démonstrations ou équations de correction.

6. Qualité des Explications (explanation) :
   - Pour les questions de calcul ou scientifiques : Rédige une démonstration complète étape par étape (calculs intermédiaires, application de formules, simplifications).
   - Pour les questions conceptuelles : Fournis une explication logique du choix correct et une brève réfutation des options erronées.

Génère [INSERER LE NOMBRE DE QUESTIONS, ex: 10] questions de niveau [INSERER LE NIVEAU, ex: Licence 3] sur le thème : [INSERER LE THEME].
```
