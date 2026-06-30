# Consignes LLM pour la Génération de Questions CSV (StudyVibe)

Ce guide est un **prompt prêt à l'emploi** que vous pouvez copier et envoyer directement à Claude, Gemini ou ChatGPT pour leur demander de générer des questions d'évaluation au format CSV compatible avec le système d'importation de StudyVibe (incluant le support des formules mathématiques KaTeX).

---

## Le Prompt à copier-coller à l'IA :

```text
Tu es un expert en conception pédagogique et en rédaction de QCM. Je veux que tu génères un ensemble de questions d'évaluation pour la matière suivante : [INSERER LE NOM DE LA MATIERE / SUJET ICI].

Tu dois me renvoyer UNIQUEMENT un bloc de code au format CSV brut contenant les questions, sans texte d'introduction ni de conclusion.

### RÈGLES STRICTES DE FORMATAGE DU CSV :
1. En-tête : La première ligne doit être exactement la suivante :
   question,option_a,option_b,option_c,option_d,correct

2. Colonnes : Chaque ligne doit avoir exactement 6 colonnes séparées par des virgules :
   - Colonne 1 : Le libellé de la question
   - Colonne 2 : Option A
   - Colonne 3 : Option B
   - Colonne 4 : Option C
   - Colonne 5 : Option D
   - Colonne 6 : La bonne réponse (uniquement la lettre en MAJUSCULE : A, B, C ou D)

3. Délimiteurs et Guillemets (CRITIQUE) :
   - Chaque champ de texte (questions et options) DOIT être entouré de doubles guillemets (ex: "ma question").
   - Si tu dois utiliser des guillemets à l'intérieur d'un champ, double-les pour les échapper (ex: "Le théorème dit ""Tout est relatif""").

4. Formules Mathématiques et LaTeX (CRITIQUE) :
   - Tu es encouragé à utiliser des formules mathématiques, des variables et des caractères spéciaux si nécessaire.
   - Les formules doivent utiliser la syntaxe LaTeX standard.
   - Les formules en ligne dans le texte doivent être entourées d'un seul symbole dollar, ex: "$x^2 + y^2 = z^2$".
   - Les formules de bloc (centrées et grandes) doivent être entourées de doubles dollars, ex: "$$\frac{a}{b}$$".
   - Ne mets pas de retour à la ligne à l'intérieur d'une formule LaTeX ou d'une cellule CSV. Tout doit tenir sur une seule ligne physique du CSV.

### EXEMPLE DE SORTIE ATTENDUE (100% CORRECTE) :
question,option_a,option_b,option_c,option_d,correct
"Calculer la limite suivante : $\lim_{x \to 0} \frac{\sin x}{x}$","0","1","$\infty$","$-1$","B"
"Quelle est la dérivée de $f(x) = \ln(x)$ pour $x > 0$ ?","$\frac{1}{x}$","$x$","$e^x$","$-\frac{1}{x^2}$","A"
"Soit une matrice $A$ de taille $2 \times 2$ telle que $\det(A) = 5$. Quel est le déterminant de $2A$ ?","10","20","5","25","B"
"Quelle est la formule de l'aire d'un cercle de rayon $r$ ?","$\pi r$","$2 \pi r$","$\pi r^2$","$2 \pi r^2$","C"

Génère maintenant [INSERER LE NOMBRE, ex: 10] questions sur le sujet : [INSERER LE THEME DE VOS QUESTIONS].
```
