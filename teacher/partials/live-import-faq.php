<?php
/**
 * FAQ of the live evaluations tab: how to add questions in bulk, with and without pictures.
 * Included at the end of #tab-live-eval in teacher/dashboard.php (needs $tdLang). The text is static and written here,
 * so it is printed as it is. Numbers (limits) come from the code so the page can never disagree with the importer.
 */
$lifLang = (isset($tdLang) && $tdLang === 'en') ? 'en' : 'fr';
$lifMaxZip   = (int)floor(BulkPackage::uploadLimitBytes() / 1048576);
$lifMaxImg   = BulkPackage::label(BulkPackage::MAX_IMAGE);
$lifMaxUnp   = BulkPackage::label(BulkPackage::MAX_UNPACKED);
$lifMaxQ     = BulkPackage::MAX_QUESTIONS;
$lifMaxFiles = BulkPackage::MAX_ENTRIES;
$lifZipMb    = ($lifMaxZip > 100000) ? '512' : (string)$lifMaxZip;

$LIF = [
'fr' => [
 'title' => 'FAQ : ajouter des questions en lot, avec ou sans images',
 'lede'  => 'Tout ce qu’il faut savoir pour préparer vos questions et les importer sans mauvaise surprise : formats de fichiers, noms des images, colonnes, exemples à copier, messages d’erreur et leurs solutions.',
 'expand' => 'Tout ouvrir', 'collapse' => 'Tout fermer', 'tpl_zip' => 'Télécharger un pack ZIP modèle', 'tpl_csv' => 'Télécharger un fichier CSV modèle',
 'goto' => 'Guide détaillé : formats, noms des fichiers, erreurs courantes',
 'items' => [
 ['id' => 'choose', 'q' => 'Quelle méthode choisir ?', 'a' => <<<'HTML'
<p>Il existe trois façons d’ajouter des questions à une séance d’évaluation en direct. Le choix dépend de ce que vous voulez ajouter :</p>
<table class="lif-table"><thead><tr><th>Je veux ajouter…</th><th>Méthode</th><th>Où la trouver</th></tr></thead><tbody>
<tr><td>Une seule question, avec ou sans image</td><td><strong>Formulaire</strong> « Ajouter une question »</td><td>Dans la séance, en haut du panneau « Questions »</td></tr>
<tr><td>Beaucoup de questions <strong>sans image</strong>, toutes à 4 choix (A, B, C, D)</td><td><strong>Import CSV / Excel</strong> avec aperçu</td><td>Dans la séance, bloc « Importer des questions »</td></tr>
<tr><td>Beaucoup de questions <strong>sans image</strong> avec vrai/faux, réponses écrites ou durée propre à chaque question</td><td><strong>Import en lot</strong> avec un fichier <code>.csv</code></td><td>Dans la séance, bloc « Import en lot »</td></tr>
<tr><td>Beaucoup de questions <strong>avec des images</strong></td><td><strong>Import en lot</strong> avec un fichier <code>.zip</code></td><td>Dans la séance, bloc « Import en lot »</td></tr>
</tbody></table>
<p class="lif-note">Les questions sont toujours ajoutées <strong>à la séance dans laquelle vous êtes</strong>. Rien n’est remplacé et rien n’est dédoublonné : importer deux fois le même fichier crée deux fois les mêmes questions.</p>
HTML],
 ['id' => 'single', 'q' => 'Comment ajouter une seule question avec une image ?', 'a' => <<<'HTML'
<ol>
<li>Ouvrez la séance, puis le panneau « Questions ».</li>
<li>Remplissez l’énoncé, les propositions A à D, la bonne réponse, la durée (facultative) et l’explication (facultative).</li>
<li>Dans le champ <strong>Image (facultatif)</strong>, choisissez un fichier. Formats acceptés : <strong>PNG, JPG, GIF, WebP</strong>. Taille maximale : <strong>8 Mo</strong>.</li>
<li>Cliquez sur « Ajouter la question ». L’image est vérifiée sur le fichier lui-même (renommer un PDF en <code>.png</code> ne marche pas), réduite à 1600 pixels de large au maximum puis conservée.</li>
</ol>
<p class="lif-note">Il n’existe pas de bouton pour changer l’image d’une question déjà créée : supprimez la question puis recréez-la (la suppression efface aussi son image).</p>
HTML],
 ['id' => 'csv-columns', 'q' => 'Sans image : quelles colonnes mettre dans le fichier CSV ou Excel ?', 'a' => <<<'HTML'
<p>La <strong>première ligne</strong> contient les noms des colonnes. L’ordre des colonnes est libre. Les noms sont reconnus sans tenir compte des majuscules ni des accents (« Énoncé », « enonce » et « ENONCE » sont équivalents).</p>
<table class="lif-table"><thead><tr><th>Colonne</th><th>Obligatoire</th><th>Contenu exact</th><th>Autres noms acceptés</th></tr></thead><tbody>
<tr><td><code>question</code></td><td>oui</td><td>L’énoncé. Une cellule entre guillemets peut contenir des retours à la ligne (utile pour un extrait de code) et des formules entre signes dollar : <code>$x^2$</code></td><td>énoncé, libellé, intitulé, texte</td></tr>
<tr><td><code>option_a</code>, <code>option_b</code>, <code>option_c</code>, <code>option_d</code></td><td>pour un QCM</td><td>Le texte de chaque proposition. Les quatre doivent être remplies (voir « vrai/faux » pour la seule exception)</td><td>a, b, c, d · choix_a · proposition_a</td></tr>
<tr><td><code>correct</code></td><td>oui</td><td>La lettre de la bonne réponse : <code>A</code>, <code>B</code>, <code>C</code> ou <code>D</code> (ou le chiffre 1, 2, 3, 4). Pour une question écrite : la réponse attendue</td><td>bonne_réponse, réponse, solution, corrigé</td></tr>
<tr><td><code>explanation</code></td><td>non</td><td>Le texte affiché dans la correction</td><td>explication, justification, commentaire</td></tr>
<tr><td><code>type</code></td><td>non</td><td><code>mcq</code> (QCM) ou <code>written</code> (réponse écrite). Si la colonne est absente ou vide : une question sans propositions est considérée comme écrite, sinon comme QCM</td><td>question_type</td></tr>
<tr><td><code>time_limit</code></td><td>non</td><td>Durée de cette question, en secondes : un entier entre <strong>5</strong> et <strong>3600</strong>. Vide : la durée par défaut de la séance s’applique</td><td>temps, durée, timer, secondes</td></tr>
<tr><td><code>image</code></td><td>non</td><td>Le nom d’une image du pack ZIP (voir « Avec images »). Sans effet dans un simple fichier .csv</td><td>img, illustration, figure, photo</td></tr>
</tbody></table>
<p>Une colonne de numérotation (<code>N°</code>, <code>id</code>, <code>numéro</code>…) est ignorée automatiquement. Toute colonne inconnue est ignorée.</p>
<p><strong>Fichier sans ligne de titre ?</strong> L’application suppose alors l’ordre : <code>question, option_a, option_b, option_c, option_d, correct, explanation</code>. Mettre une ligne de titre reste fortement recommandé.</p>
HTML],
 ['id' => 'csv-excel', 'q' => 'Comment préparer le fichier dans Excel, LibreOffice ou Google Sheets ?', 'a' => <<<'HTML'
<ol>
<li>Écrivez une question par ligne, avec la ligne de titre en première ligne (voir le tableau des colonnes).</li>
<li>Enregistrez au format <strong>CSV UTF-8</strong> :
  <ul><li><strong>Excel</strong> : Fichier → Enregistrer sous → « CSV UTF-8 (délimité par des virgules) ». Évitez « CSV (séparateur : point-virgule) » et « CSV Macintosh », qui ne sont pas en UTF-8.</li>
  <li><strong>LibreOffice</strong> : Enregistrer sous → « Texte CSV » → cochez « Éditer les paramètres de filtre » → jeu de caractères <strong>Unicode (UTF-8)</strong>.</li>
  <li><strong>Google Sheets</strong> : Fichier → Télécharger → « Valeurs séparées par des virgules (.csv) » (déjà en UTF-8).</li></ul></li>
<li>Séparateur accepté : virgule <code>,</code>, point-virgule <code>;</code> ou tabulation. L’application détecte celui de la première ligne. Une marque d’ordre d’octets (BOM) au début du fichier est sans conséquence.</li>
<li>Un fichier <code>.xlsx</code> ou <code>.xls</code> peut aussi être envoyé directement à l’import « CSV / Excel » : seule la <strong>première feuille</strong> est lue.</li>
</ol>
<p><strong>Si les accents s’affichent mal</strong> (« Ã© » à la place de « é ») : le fichier n’est pas en UTF-8, enregistrez-le de nouveau comme ci-dessus.</p>
<p><strong>Si une cellule contient une virgule, un guillemet ou un retour à la ligne</strong> : entourez-la de guillemets doubles ; un guillemet à l’intérieur s’écrit deux fois (<code>""</code>). Excel et LibreOffice le font tout seuls ; ne le faites à la main que si vous écrivez le CSV dans un éditeur de texte.</p>
<p><strong>Exemple complet</strong> (copiez-le dans un fichier <code>questions.csv</code> ; la troisième ligne montre une cellule sur plusieurs lignes) :</p>
<pre class="lif-code">question,option_a,option_b,option_c,option_d,correct,explanation
"Dérivée de $f(x)=x^2$ ?","$2x$","$x$","$x^2$","$2$",A,"La dérivée de $x^n$ est $nx^{n-1}$."
"Que vaut y ?
x = 2
y = x + 3","3","5","6","7",B,"y = 2 + 3 = 5."</pre>
<p class="lif-note">L’import « CSV / Excel » avec aperçu ne lit que des QCM à 4 propositions (A, B, C, D) ; pour du vrai/faux, des réponses écrites ou des durées par question, utilisez le bloc « Import en lot » avec un fichier <code>.csv</code>.</p>
HTML],
 ['id' => 'types', 'q' => 'Vrai/faux, réponse écrite, durée, formules : comment les écrire ?', 'a' => <<<'HTML'
<p>Ces possibilités sont disponibles avec le bloc <strong>Import en lot</strong> (fichier <code>.csv</code> ou <code>.zip</code>) d’une séance en direct.</p>
<table class="lif-table"><thead><tr><th>Cas</th><th>Comment l’écrire</th><th>Exemple de ligne</th></tr></thead><tbody>
<tr><td><strong>Vrai / faux</strong></td><td>Remplir seulement <code>option_a</code> et <code>option_b</code> ; laisser C et D vides ; <code>correct</code> vaut <code>A</code> ou <code>B</code></td><td><code>"Le soleil est une étoile",Vrai,Faux,,,A,</code></td></tr>
<tr><td><strong>Réponse écrite</strong></td><td>Laisser les quatre options vides (ou mettre <code>type</code> = <code>written</code>) ; <code>correct</code> contient la réponse attendue. L’étudiant la tape lui-même</td><td><code>"Capitale du Cameroun ?",,,,,Yaoundé,</code></td></tr>
<tr><td>Plusieurs réponses acceptées</td><td>Séparez-les par une barre verticale <code>|</code></td><td><code>5|cinq</code></td></tr>
<tr><td>Réponse numérique avec tolérance</td><td><code>valeur~tolérance</code> accepte toute valeur entre valeur − tolérance et valeur + tolérance</td><td><code>2.5~0.1</code> accepte de 2,4 à 2,6</td></tr>
<tr><td>Comparaison des réponses écrites</td><td>Majuscules, espaces et virgule/point décimal sont ignorés ; deux nombres sont comparés comme des nombres (2.50 égale 2.5)</td><td>—</td></tr>
<tr><td><strong>Durée propre à la question</strong></td><td>Colonne <code>time_limit</code>, en secondes, de 5 à 3600. Vide : durée par défaut de la séance</td><td><code>…,B,"Explication",,45</code></td></tr>
<tr><td><strong>Formule mathématique</strong></td><td>Entre deux signes dollar, en syntaxe LaTeX (KaTeX), dans l’énoncé, les propositions ou l’explication</td><td><code>$\frac{a}{b}$</code></td></tr>
<tr><td><strong>Extrait de code</strong></td><td>Cellule entre guillemets avec retours à la ligne, ou mieux : une <strong>image</strong> du code (lisible sur tous les écrans)</td><td>voir l’exemple CSV plus haut</td></tr>
</tbody></table>
<p class="lif-note">Si la séance mélange les propositions pour chaque étudiant, la bonne réponse suit son texte : la lettre écrite dans votre fichier reste la référence pour la correction.</p>
HTML],
 ['id' => 'zip-steps', 'q' => 'Avec images : comment créer et envoyer un pack ZIP, pas à pas ?', 'a' => <<<'HTML'
<p>Un <strong>pack</strong> est un seul fichier <code>.zip</code> qui contient le fichier des questions et les images. Voici la structure à obtenir :</p>
<pre class="lif-code">mon-examen.zip
├── questions.csv            ← un seul fichier .csv, à la racine
└── images/                  ← facultatif : le nom du dossier est libre
    ├── q01_graphe.png
    ├── q02_schema.png
    └── q07_code.jpg</pre>
<ol>
<li><strong>Préparez les images</strong> et donnez-leur des noms simples (voir « Nommer les images »).</li>
<li><strong>Écrivez <code>questions.csv</code></strong> avec les colonnes habituelles, plus une colonne <code>image</code> : dans chaque ligne qui a une image, écrivez exactement le nom du fichier, extension comprise (<code>q01_graphe.png</code>). Laissez la cellule vide pour une question sans image.</li>
<li><strong>Rassemblez</strong> le CSV et le dossier d’images dans un même dossier.</li>
<li><strong>Compressez</strong> en ZIP :
  <ul><li><strong>Windows</strong> : sélectionnez <code>questions.csv</code> et le dossier <code>images</code> → clic droit → « Envoyer vers » → « Dossier compressé ».</li>
  <li><strong>macOS</strong> : sélectionnez-les → clic droit → « Compresser 2 éléments ».</li>
  <li><strong>Linux</strong> : <code>zip -r mon-examen.zip questions.csv images</code></li></ul></li>
<li>Dans la séance, bloc <strong>Import en lot</strong> : choisissez le fichier <code>.zip</code>, cliquez sur <strong>« Vérifier le pack »</strong>, lisez le rapport (voir « Ce qui est vérifié »), puis cliquez sur <strong>« Importer N question(s) »</strong>.</li>
</ol>
<p><strong>Un pack tout prêt</strong> à modifier : cliquez sur « Télécharger un modèle de pack (ZIP) » dans le bloc Import en lot. Il contient un CSV d’exemple (avec image, vrai/faux, réponse écrite et durée), une image d’exemple et un mode d’emploi.</p>
<p class="lif-note">Les formats <code>.rar</code>, <code>.7z</code> et <code>.tar.gz</code> ne sont pas lus : le fichier doit être un vrai ZIP. Renommer un <code>.rar</code> en <code>.zip</code> ne fonctionne pas (« ZIP illisible »).</p>
HTML],
 ['id' => 'naming', 'q' => 'Comment nommer et préparer les images ? (toutes les règles)', 'a' => <<<'HTML'
<h5>Noms des fichiers</h5>
<ul>
<li>Le nom écrit dans la colonne <code>image</code> doit être <strong>identique</strong> au nom du fichier, <strong>extension comprise</strong> : <code>q01_graphe.png</code> et non <code>q01_graphe</code>. Une faute de frappe, une extension différente (<code>.jpg</code> au lieu de <code>.png</code>) ou une lettre accentuée différente rend l’image « introuvable ».</li>
<li>Les <strong>majuscules sont ignorées</strong> : <code>Q01.PNG</code> et <code>q01.png</code> désignent le même fichier.</li>
<li>Le <strong>dossier est ignoré</strong> : <code>images/q01.png</code> et <code>q01.png</code> sont le même fichier. Écrivez seulement le nom dans la cellule (un chemin <code>images/q01.png</code> fonctionne aussi, seul le nom compte).</li>
<li><strong>Chaque nom doit être unique</strong> dans tout le ZIP. Si deux fichiers de dossiers différents portent le même nom, seul le premier est utilisé et un avertissement l’indique.</li>
<li><strong>Recommandation forte</strong> : utilisez seulement des lettres sans accent <code>a-z</code>, des chiffres, le tiret <code>-</code> et le tiret bas <code>_</code>. Évitez les espaces, les accents, les apostrophes et les symboles. Les accents sont encodés différemment selon le système qui a créé le ZIP, ce qui peut rendre un nom « introuvable » alors qu’il semble identique.</li>
<li>Suggestion de nommage : le numéro de la question puis un mot : <code>q01_graphe.png</code>, <code>q02_schema.png</code>, <code>q10_code.jpg</code> (deux chiffres pour garder l’ordre alphabétique).</li>
<li>Une même image peut servir à plusieurs questions : écrivez le même nom dans plusieurs lignes.</li>
<li>Les fichiers cachés (nom commençant par un point) et le dossier <code>__MACOSX</code> sont ignorés.</li>
</ul>
<h5>Format, taille et qualité</h5>
<ul>
<li>Extensions acceptées : <strong>.png, .jpg, .jpeg, .gif, .webp</strong>. Le contenu du fichier doit être une vraie image : un PDF, un SVG ou un document renommé est refusé.</li>
<li>Taille maximale : <strong>__IMG__ par image</strong>. Largeur conseillée : <strong>800 à 1600 pixels</strong>. Au-delà de 1600 pixels de large, l’image est réduite automatiquement ; au-delà de 40 millions de pixels au total (largeur × hauteur), elle est refusée.</li>
<li><strong>PNG</strong> pour les schémas, graphiques, tableaux et extraits de code (texte net). <strong>JPG</strong> pour les photographies (fichier plus léger).</li>
<li>N’utilisez pas de GIF animé : l’animation n’est pas conservée.</li>
<li>Vérifiez que le texte de l’image reste lisible sur un téléphone : texte assez gros, fort contraste, fond uni.</li>
</ul>
HTML],
 ['id' => 'zip-csv', 'q' => 'À quoi ressemble le fichier questions.csv d’un pack avec images ?', 'a' => <<<'HTML'
<p>C’est le même fichier que pour un import sans image, avec en plus la colonne <code>image</code> (et, si vous le souhaitez, <code>time_limit</code> et <code>type</code>). Exemple complet à copier :</p>
<pre class="lif-code">question,option_a,option_b,option_c,option_d,correct,explanation,image,time_limit
"Que renvoie ce code ? (voir l'image)",2,3,4,5,B,"y = 2 + 3 vaut 5.",q01_code.png,60
"Quel est le diagramme correct ?",Cas A,Cas B,Cas C,Cas D,C,"Voir la relation include.",q02_uml.png,90
"Le Soleil est une étoile.",Vrai,Faux,,,A,,,
"Capitale du Cameroun ?",,,,,Yaoundé,"Accepté : yaoundé, YAOUNDE.",,30</pre>
<ul>
<li>Ligne 1 et 2 : QCM avec image et durée propre. Ligne 3 : vrai/faux sans image. Ligne 4 : réponse écrite sans image, 30 secondes.</li>
<li><strong>Un seul fichier .csv par ZIP.</strong> S’il y en a plusieurs, seul le premier trouvé est lu et les autres sont ignorés sans avertissement : n’en mettez qu’un.</li>
<li>Le nom du fichier CSV est libre (<code>questions.csv</code> est la convention), seule l’extension <code>.csv</code> compte.</li>
<li>Les images sont envoyées <strong>uniquement</strong> avec les évaluations en direct. Dans un quiz de leçon ou de cours, la colonne <code>image</code> est ignorée avec un avertissement.</li>
</ul>
HTML],
 ['id' => 'checks', 'q' => 'Que vérifie l’application avant d’importer, et comment lire le rapport ?', 'a' => <<<'HTML'
<p>« Vérifier le pack » ne <strong>rien enregistre</strong>. Le rapport indique : le nombre de questions trouvées, combien ont une image, le nombre d’erreurs et d’avertissements, puis un tableau ligne par ligne (<strong>N°</strong>, <strong>Question</strong>, <strong>Image</strong> : ✓ trouvée et valide, ✗ introuvable ou invalide, <strong>Vérification</strong> : OK ou la raison de l’erreur).</p>
<ul>
<li><strong>Erreur (en rouge) : l’import est bloqué.</strong> Corrigez le fichier puis relancez « Vérifier le pack ».</li>
<li><strong>Avertissement (en ocre) : l’import reste possible.</strong> Exemples : une image qui n’est utilisée par aucune question, deux fichiers au même nom.</li>
<li>Le bouton <strong>« Importer N question(s) »</strong> n’apparaît que s’il n’y a aucune erreur.</li>
<li>Au clic, le pack est <strong>vérifié une seconde fois</strong> sur le serveur, puis enregistré <strong>en tout ou rien</strong> : les images d’abord, puis toutes les questions en une seule opération. Si quoi que ce soit échoue, rien n’est importé et les images déjà enregistrées sont supprimées. Vous ne pouvez donc jamais obtenir un import à moitié fait.</li>
</ul>
HTML],
 ['id' => 'errors', 'q' => 'Messages d’erreur et d’avertissement : cause et solution', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Message affiché</th><th>Cause</th><th>Solution</th></tr></thead><tbody>
<tr><td>Question N : l’image « x.png » est introuvable dans le ZIP.</td><td>Le nom de la colonne <code>image</code> ne correspond à aucun fichier : faute de frappe, extension différente, accent différent, image oubliée dans le ZIP</td><td>Comparez lettre par lettre avec le fichier ; renommez l’image en caractères simples ; vérifiez qu’elle est bien dans le ZIP</td></tr>
<tr><td>Question N : « x.png » n’est pas une image valide.</td><td>Le fichier est abîmé ou n’est pas une image (PDF, SVG, document renommé)</td><td>Réexportez l’image en PNG ou JPG depuis votre logiciel</td></tr>
<tr><td>L’image « x » dépasse __IMG__.</td><td>Le fichier est trop lourd</td><td>Réduisez-le (largeur de 1600 pixels suffit) ou enregistrez-le en JPG</td></tr>
<tr><td>Aucun fichier .csv trouvé dans le ZIP…</td><td>Le ZIP ne contient pas de fichier se terminant par <code>.csv</code></td><td>Ajoutez <code>questions.csv</code> à la racine, puis recréez le ZIP</td></tr>
<tr><td>Ce fichier ZIP est illisible ou endommagé.</td><td>Le fichier n’est pas un vrai ZIP (par exemple un RAR renommé) ou le transfert a été interrompu</td><td>Recompressez en ZIP depuis votre système, puis renvoyez</td></tr>
<tr><td>Envoyez un fichier .zip (…) ou un fichier .csv.</td><td>Extension non acceptée</td><td>N’envoyez qu’un <code>.zip</code> ou un <code>.csv</code></td></tr>
<tr><td>Ligne N : champs de QCM incomplets.</td><td>Un QCM doit avoir l’énoncé et les propositions A, B, C, D (A et B seulement pour le vrai/faux) ; une des cellules est vide</td><td>Remplissez-les, ou laissez les quatre options vides pour une question écrite</td></tr>
<tr><td>Ligne N : réponse correcte QCM invalide (utilisez A, B, C ou D…).</td><td>La colonne <code>correct</code> contient autre chose que A, B, C, D (ou 1 à 4). Pour un vrai/faux : seulement A ou B</td><td>Écrivez une seule lettre</td></tr>
<tr><td>Ligne N : énoncé de question ouverte vide. / réponse attendue de question ouverte vide.</td><td>Question écrite sans énoncé, ou sans réponse dans <code>correct</code></td><td>Complétez l’énoncé et la colonne <code>correct</code></td></tr>
<tr><td>Ligne N : temps invalide « x » (…)</td><td>La colonne <code>time_limit</code> n’est pas un entier de 5 à 3600</td><td>Écrivez un nombre de secondes (par exemple 45) ou laissez vide</td></tr>
<tr><td>Fichier CSV vide. / Aucune question valide trouvée dans le CSV.</td><td>Le fichier ne contient aucune ligne exploitable</td><td>Vérifiez la ligne de titre et le séparateur</td></tr>
<tr><td>Trop de questions (__Q__ au plus par pack). / Le ZIP contient trop de fichiers (__F__ au plus).</td><td>Le pack dépasse les limites</td><td>Scindez en plusieurs packs</td></tr>
<tr><td>Le fichier dépasse la taille maximale acceptée par le serveur (__ZIP__ Mo…)</td><td>Le ZIP est plus gros que ce que le serveur accepte</td><td>Réduisez les images ou scindez en deux packs</td></tr>
<tr><td><em>Avertissement :</em> L’image « x » n’est utilisée par aucune question.</td><td>Une image du ZIP n’est citée dans aucune ligne</td><td>Sans conséquence ; supprimez-la du ZIP ou ajoutez-la dans la colonne <code>image</code></td></tr>
<tr><td><em>Avertissement :</em> Deux images portent le nom « x » : la première est utilisée.</td><td>Même nom dans deux dossiers</td><td>Renommez l’une des deux</td></tr>
</tbody></table>
HTML],
 ['id' => 'limits', 'q' => 'Quelles sont les limites ?', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Élément</th><th>Limite</th></tr></thead><tbody>
<tr><td>Taille du fichier envoyé (ZIP ou CSV)</td><td>__ZIP__ Mo (réglage du serveur ; la page refuse un fichier plus gros avant de l’envoyer)</td></tr>
<tr><td>Nombre de questions par pack</td><td>__Q__</td></tr>
<tr><td>Nombre de fichiers dans le ZIP</td><td>__F__</td></tr>
<tr><td>Taille du ZIP une fois décompressé</td><td>__UNP__</td></tr>
<tr><td>Taille d’une image dans un pack</td><td>__IMG__ (puis réduite à 1600 pixels de large)</td></tr>
<tr><td>Taille d’une image ajoutée avec le formulaire d’une seule question</td><td>8 Mo</td></tr>
<tr><td>Dimensions d’une image</td><td>40 millions de pixels au plus (largeur × hauteur)</td></tr>
<tr><td>Durée d’une question</td><td>de 5 à 3600 secondes</td></tr>
<tr><td>Vérifications et imports</td><td>40 par heure et par enseignant (chaque clic sur « Vérifier le pack » ou « Importer » compte pour un)</td></tr>
</tbody></table>
HTML],
 ['id' => 'after', 'q' => 'Après l’import : où voir, corriger, supprimer, recommencer ?', 'a' => <<<'HTML'
<ul>
<li>Les questions importées apparaissent dans la liste « Questions » de la séance, <strong>dans l’ordre des lignes du fichier</strong>.</li>
<li>Elles s’ajoutent aux questions déjà présentes. <strong>Rien n’est remplacé ni dédoublonné.</strong> Pour repartir de zéro, supprimez d’abord les anciennes questions.</li>
<li>Supprimer <strong>une</strong> question efface aussi son image. Le bouton de suppression de <strong>toutes</strong> les questions de la séance supprime également toutes leurs images.</li>
<li>Pour corriger une question importée (énoncé, option, image) : supprimez-la puis ajoutez-la de nouveau avec le formulaire, ou supprimez tout et réimportez un fichier corrigé.</li>
<li>Importer dans la mauvaise séance ? Les questions vont dans la séance où vous avez cliqué : ouvrez la bonne séance avant de choisir le fichier.</li>
<li>Faites un <strong>essai avant l’examen</strong> : ouvrez la séance comme un étudiant (avec un compte de test) pour voir l’affichage des images et des formules sur ordinateur et sur téléphone.</li>
</ul>
HTML],
 ['id' => 'tips', 'q' => 'Conseils pour un examen bien construit', 'a' => <<<'HTML'
<ul>
<li><strong>Propositions de longueur comparable.</strong> Une bonne réponse nettement plus longue que les autres se devine. Rédigez les quatre propositions avec à peu près le même nombre de mots.</li>
<li><strong>Répartissez les bonnes réponses</strong> entre A, B, C et D (environ un quart chacune) et évitez trois fois la même lettre de suite.</li>
<li><strong>Une image doit apporter une information nécessaire</strong> (code, schéma, tableau, courbe), pas décorer. Écrivez dans l’énoncé « voir l’image » pour que l’étudiant la cherche.</li>
<li>Pour du code, une <strong>image</strong> du code évite les problèmes d’indentation et de caractères ; pour un diagramme, un PNG net de 1000 à 1600 pixels de large convient.</li>
<li>Adaptez la durée : plus longue pour une question qui demande de lire une image ou un code (colonne <code>time_limit</code>).</li>
<li>Gardez toujours une <strong>copie de votre pack ZIP</strong> : c’est le moyen le plus simple de recréer ou de modifier toute la séance.</li>
</ul>
HTML],
 ],
],
'en' => [
 'title' => 'FAQ: adding questions in bulk, with or without pictures',
 'lede'  => 'Everything you need to prepare your questions and import them without surprises: file formats, picture names, columns, examples to copy, error messages and how to fix them.',
 'expand' => 'Open all', 'collapse' => 'Close all', 'tpl_zip' => 'Download a sample ZIP package', 'tpl_csv' => 'Download a sample CSV file',
 'goto' => 'Detailed guide: formats, file names, common errors',
 'items' => [
 ['id' => 'choose', 'q' => 'Which method should I use?', 'a' => <<<'HTML'
<p>There are three ways to add questions to a live evaluation session. The right one depends on what you want to add:</p>
<table class="lif-table"><thead><tr><th>I want to add…</th><th>Method</th><th>Where</th></tr></thead><tbody>
<tr><td>A single question, with or without a picture</td><td>The <strong>“Add a question”</strong> form</td><td>In the session, at the top of the “Questions” panel</td></tr>
<tr><td>Many questions <strong>without pictures</strong>, all with 4 choices (A, B, C, D)</td><td><strong>CSV / Excel import</strong> with a preview</td><td>In the session, “Import questions” block</td></tr>
<tr><td>Many questions <strong>without pictures</strong> with true/false, written answers or a time per question</td><td><strong>Bulk import</strong> with a <code>.csv</code> file</td><td>In the session, “Bulk import” block</td></tr>
<tr><td>Many questions <strong>with pictures</strong></td><td><strong>Bulk import</strong> with a <code>.zip</code> file</td><td>In the session, “Bulk import” block</td></tr>
</tbody></table>
<p class="lif-note">Questions are always added to <strong>the session you are in</strong>. Nothing is replaced and nothing is de-duplicated: importing the same file twice creates the same questions twice.</p>
HTML],
 ['id' => 'single', 'q' => 'How do I add a single question with a picture?', 'a' => <<<'HTML'
<ol>
<li>Open the session, then the “Questions” panel.</li>
<li>Fill in the statement, options A to D, the correct answer, the time (optional) and the explanation (optional).</li>
<li>In <strong>Picture (optional)</strong>, choose a file. Accepted formats: <strong>PNG, JPG, GIF, WebP</strong>. Maximum size: <strong>8 MB</strong>.</li>
<li>Click “Add question”. The picture is checked on the file itself (renaming a PDF to <code>.png</code> does not work), scaled down to 1600 pixels wide at most and stored.</li>
</ol>
<p class="lif-note">There is no button to change the picture of an existing question: delete the question and create it again (deleting also removes its picture).</p>
HTML],
 ['id' => 'csv-columns', 'q' => 'Without pictures: which columns go in the CSV or Excel file?', 'a' => <<<'HTML'
<p>The <strong>first row</strong> holds the column names. Column order is free. Names are matched ignoring case and accents (“Énoncé”, “enonce” and “ENONCE” are the same).</p>
<table class="lif-table"><thead><tr><th>Column</th><th>Required</th><th>Exact content</th><th>Other accepted names</th></tr></thead><tbody>
<tr><td><code>question</code></td><td>yes</td><td>The statement. A quoted cell may contain line breaks (handy for a code snippet) and formulas between dollar signs: <code>$x^2$</code></td><td>statement, text, enonce, libelle</td></tr>
<tr><td><code>option_a</code>, <code>option_b</code>, <code>option_c</code>, <code>option_d</code></td><td>for multiple choice</td><td>The text of each choice. All four must be filled (see “true/false” for the only exception)</td><td>a, b, c, d · choice_a</td></tr>
<tr><td><code>correct</code></td><td>yes</td><td>The letter of the right answer: <code>A</code>, <code>B</code>, <code>C</code> or <code>D</code> (or the digit 1 to 4). For a written question: the expected answer</td><td>correct_answer, answer, solution</td></tr>
<tr><td><code>explanation</code></td><td>no</td><td>The text shown in the correction</td><td>explication, justification, comment</td></tr>
<tr><td><code>type</code></td><td>no</td><td><code>mcq</code> (multiple choice) or <code>written</code>. When the column is absent or empty: a question without options is written, otherwise multiple choice</td><td>question_type</td></tr>
<tr><td><code>time_limit</code></td><td>no</td><td>Time for this question in seconds: a whole number from <strong>5</strong> to <strong>3600</strong>. Empty: the session default applies</td><td>time, duration, timer, seconds</td></tr>
<tr><td><code>image</code></td><td>no</td><td>The name of a picture inside the ZIP package (see “With pictures”). Has no effect in a plain .csv file</td><td>img, illustration, figure, photo</td></tr>
</tbody></table>
<p>A numbering column (<code>No.</code>, <code>id</code>, <code>number</code>…) is ignored automatically. Any unknown column is ignored.</p>
<p><strong>No header row?</strong> The application then assumes this order: <code>question, option_a, option_b, option_c, option_d, correct, explanation</code>. A header row is still strongly recommended.</p>
HTML],
 ['id' => 'csv-excel', 'q' => 'How do I prepare the file in Excel, LibreOffice or Google Sheets?', 'a' => <<<'HTML'
<ol>
<li>Write one question per row, with the header row first (see the column table).</li>
<li>Save as <strong>CSV UTF-8</strong>:
  <ul><li><strong>Excel</strong>: File → Save As → “CSV UTF-8 (Comma delimited)”. Avoid the older “CSV” and “CSV Macintosh”, which are not UTF-8.</li>
  <li><strong>LibreOffice</strong>: Save As → “Text CSV” → tick “Edit filter settings” → character set <strong>Unicode (UTF-8)</strong>.</li>
  <li><strong>Google Sheets</strong>: File → Download → “Comma Separated Values (.csv)” (already UTF-8).</li></ul></li>
<li>Accepted separators: comma <code>,</code>, semicolon <code>;</code> or tab. The application detects the one used by the first row. A byte order mark (BOM) at the start is harmless.</li>
<li>An <code>.xlsx</code> or <code>.xls</code> file can also be sent directly to the “CSV / Excel” import: only the <strong>first sheet</strong> is read.</li>
</ol>
<p><strong>If accents look wrong</strong> (“Ã©” instead of “é”): the file is not UTF-8, save it again as above.</p>
<p><strong>If a cell contains a comma, a quote or a line break</strong>: wrap it in double quotes; a quote inside is written twice (<code>""</code>). Excel and LibreOffice do this for you; only do it by hand if you write the CSV in a text editor.</p>
<p><strong>Complete example</strong> (copy it into a file named <code>questions.csv</code>; the third row shows a multi-line cell):</p>
<pre class="lif-code">question,option_a,option_b,option_c,option_d,correct,explanation
"Derivative of $f(x)=x^2$ ?","$2x$","$x$","$x^2$","$2$",A,"The derivative of $x^n$ is $nx^{n-1}$."
"What is y?
x = 2
y = x + 3","3","5","6","7",B,"y = 2 + 3 = 5."</pre>
<p class="lif-note">The “CSV / Excel” import with preview only reads 4-choice questions (A, B, C, D). For true/false, written answers or per-question times, use the “Bulk import” block with a <code>.csv</code> file.</p>
HTML],
 ['id' => 'types', 'q' => 'True/false, written answer, time, formulas: how to write them?', 'a' => <<<'HTML'
<p>These features are available with the <strong>Bulk import</strong> block (<code>.csv</code> or <code>.zip</code> file) of a live session.</p>
<table class="lif-table"><thead><tr><th>Case</th><th>How to write it</th><th>Example row</th></tr></thead><tbody>
<tr><td><strong>True / false</strong></td><td>Fill only <code>option_a</code> and <code>option_b</code>; leave C and D empty; <code>correct</code> is <code>A</code> or <code>B</code></td><td><code>"The sun is a star",True,False,,,A,</code></td></tr>
<tr><td><strong>Written answer</strong></td><td>Leave all four options empty (or set <code>type</code> to <code>written</code>); <code>correct</code> holds the expected answer. The student types it</td><td><code>"Capital of Cameroon?",,,,,Yaoundé,</code></td></tr>
<tr><td>Several accepted answers</td><td>Separate them with a vertical bar <code>|</code></td><td><code>5|five</code></td></tr>
<tr><td>Numeric answer with tolerance</td><td><code>value~tolerance</code> accepts anything from value − tolerance to value + tolerance</td><td><code>2.5~0.1</code> accepts 2.4 to 2.6</td></tr>
<tr><td>How written answers are compared</td><td>Case, spaces and decimal comma/point are ignored; two numbers are compared as numbers (2.50 equals 2.5)</td><td>—</td></tr>
<tr><td><strong>Time for one question</strong></td><td><code>time_limit</code> column, in seconds, 5 to 3600. Empty: the session default</td><td><code>…,B,"Explanation",,45</code></td></tr>
<tr><td><strong>Maths formula</strong></td><td>Between two dollar signs, in LaTeX syntax (KaTeX), in the statement, options or explanation</td><td><code>$\frac{a}{b}$</code></td></tr>
<tr><td><strong>Code snippet</strong></td><td>A quoted cell with line breaks, or better: a <strong>picture</strong> of the code (readable on every screen)</td><td>see the CSV example above</td></tr>
</tbody></table>
<p class="lif-note">If the session shuffles the options for each student, the right answer follows its text: the letter in your file stays the reference for marking.</p>
HTML],
 ['id' => 'zip-steps', 'q' => 'With pictures: how do I build and send a ZIP package, step by step?', 'a' => <<<'HTML'
<p>A <strong>package</strong> is one <code>.zip</code> file holding the questions file and the pictures. This is the structure to get:</p>
<pre class="lif-code">my-exam.zip
├── questions.csv            ← a single .csv file, at the root
└── images/                  ← optional: the folder name is free
    ├── q01_graph.png
    ├── q02_diagram.png
    └── q07_code.jpg</pre>
<ol>
<li><strong>Prepare the pictures</strong> and give them simple names (see “Naming pictures”).</li>
<li><strong>Write <code>questions.csv</code></strong> with the usual columns plus an <code>image</code> column: on each row that has a picture, write exactly the file name, extension included (<code>q01_graph.png</code>). Leave the cell empty for a question without a picture.</li>
<li><strong>Put</strong> the CSV and the pictures folder together in one folder.</li>
<li><strong>Compress</strong> to ZIP:
  <ul><li><strong>Windows</strong>: select <code>questions.csv</code> and the <code>images</code> folder → right click → “Send to” → “Compressed (zipped) folder”.</li>
  <li><strong>macOS</strong>: select them → right click → “Compress 2 items”.</li>
  <li><strong>Linux</strong>: <code>zip -r my-exam.zip questions.csv images</code></li></ul></li>
<li>In the session, <strong>Bulk import</strong> block: choose the <code>.zip</code>, click <strong>“Check the package”</strong>, read the report (see “What is checked”), then click <strong>“Import N question(s)”</strong>.</li>
</ol>
<p><strong>A ready-made package</strong> to edit: click “Download a package template (ZIP)” in the Bulk import block. It holds an example CSV (with a picture, true/false, a written answer and a time), an example picture and instructions.</p>
<p class="lif-note">.rar, .7z and .tar.gz are not read: the file must be a real ZIP. Renaming a <code>.rar</code> to <code>.zip</code> does not work (“unreadable ZIP”).</p>
HTML],
 ['id' => 'naming', 'q' => 'How do I name and prepare pictures? (all the rules)', 'a' => <<<'HTML'
<h5>File names</h5>
<ul>
<li>The name written in the <code>image</code> column must be <strong>identical</strong> to the file name, <strong>extension included</strong>: <code>q01_graph.png</code>, not <code>q01_graph</code>. A typo, a different extension (<code>.jpg</code> instead of <code>.png</code>) or a different accented letter makes the picture “not found”.</li>
<li><strong>Case is ignored</strong>: <code>Q01.PNG</code> and <code>q01.png</code> are the same file.</li>
<li>The <strong>folder is ignored</strong>: <code>images/q01.png</code> and <code>q01.png</code> are the same file. Write only the name in the cell (a path such as <code>images/q01.png</code> also works, only the name counts).</li>
<li><strong>Every name must be unique</strong> in the whole ZIP. If two files in different folders share a name, only the first is used and a warning says so.</li>
<li><strong>Strong recommendation</strong>: use only unaccented letters <code>a-z</code>, digits, hyphen <code>-</code> and underscore <code>_</code>. Avoid spaces, accents, apostrophes and symbols. Accents are encoded differently depending on the system that built the ZIP, which can make a name “not found” even though it looks identical.</li>
<li>Naming suggestion: the question number then a word: <code>q01_graph.png</code>, <code>q02_diagram.png</code>, <code>q10_code.jpg</code> (two digits keep alphabetical order).</li>
<li>One picture can serve several questions: write the same name on several rows.</li>
<li>Hidden files (names starting with a dot) and the <code>__MACOSX</code> folder are ignored.</li>
</ul>
<h5>Format, size and quality</h5>
<ul>
<li>Accepted extensions: <strong>.png, .jpg, .jpeg, .gif, .webp</strong>. The content must be a real picture: a renamed PDF, SVG or document is refused.</li>
<li>Maximum size: <strong>__IMG__ per picture</strong>. Recommended width: <strong>800 to 1600 pixels</strong>. Above 1600 pixels wide the picture is scaled down automatically; above 40 million pixels in total (width × height) it is refused.</li>
<li><strong>PNG</strong> for diagrams, charts, tables and code (sharp text). <strong>JPG</strong> for photographs (lighter file).</li>
<li>Do not use an animated GIF: the animation is not kept.</li>
<li>Check that the text in the picture stays readable on a phone: large enough, strong contrast, plain background.</li>
</ul>
HTML],
 ['id' => 'zip-csv', 'q' => 'What does the questions.csv of a package with pictures look like?', 'a' => <<<'HTML'
<p>It is the same file as for an import without pictures, plus the <code>image</code> column (and, if you wish, <code>time_limit</code> and <code>type</code>). Complete example to copy:</p>
<pre class="lif-code">question,option_a,option_b,option_c,option_d,correct,explanation,image,time_limit
"What does this code return? (see picture)",2,3,4,5,B,"y = 2 + 3 is 5.",q01_code.png,60
"Which diagram is correct?",Case A,Case B,Case C,Case D,C,"See the include relation.",q02_uml.png,90
"The Sun is a star.",True,False,,,A,,,
"Capital of Cameroon?",,,,,Yaoundé,"Accepted: yaoundé, YAOUNDE.",,30</pre>
<ul>
<li>Rows 1 and 2: multiple choice with a picture and their own time. Row 3: true/false without a picture. Row 4: written answer without a picture, 30 seconds.</li>
<li><strong>One .csv file per ZIP.</strong> If there are several, only the first one found is read and the others are ignored without a warning: include only one.</li>
<li>The CSV file name is free (<code>questions.csv</code> is the convention); only the <code>.csv</code> extension matters.</li>
<li>Pictures are used <strong>only</strong> with live evaluations. In a lesson or course quiz the <code>image</code> column is ignored with a warning.</li>
</ul>
HTML],
 ['id' => 'checks', 'q' => 'What is checked before importing, and how do I read the report?', 'a' => <<<'HTML'
<p>“Check the package” <strong>saves nothing</strong>. The report shows the number of questions found, how many have a picture, the number of errors and warnings, then a row-by-row table (<strong>No.</strong>, <strong>Question</strong>, <strong>Picture</strong>: ✓ found and valid, ✗ missing or invalid, <strong>Check</strong>: OK or the reason for the error).</p>
<ul>
<li><strong>Error (red): the import is blocked.</strong> Fix the file and run “Check the package” again.</li>
<li><strong>Warning (ochre): the import is still possible.</strong> Examples: a picture used by no question, two files with the same name.</li>
<li>The <strong>“Import N question(s)”</strong> button only appears when there is no error.</li>
<li>When you click it, the package is <strong>checked a second time</strong> on the server, then saved <strong>all or nothing</strong>: pictures first, then all questions in one operation. If anything fails, nothing is imported and the pictures already saved are deleted. You can never end up with a half-done import.</li>
</ul>
HTML],
 ['id' => 'errors', 'q' => 'Error and warning messages: cause and fix', 'a' => <<<'HTML'
<p class="lif-note">The messages appear in French in the application. Their meaning:</p>
<table class="lif-table"><thead><tr><th>Message shown</th><th>Cause</th><th>Fix</th></tr></thead><tbody>
<tr><td>Question N : l’image « x.png » est introuvable dans le ZIP. (picture not found)</td><td>The <code>image</code> cell matches no file: typo, different extension, different accent, picture missing from the ZIP</td><td>Compare letter by letter with the file; rename the picture with simple characters; check it is in the ZIP</td></tr>
<tr><td>Question N : « x.png » n’est pas une image valide. (not a valid picture)</td><td>The file is damaged or not a picture (PDF, SVG, renamed document)</td><td>Export the picture again as PNG or JPG</td></tr>
<tr><td>L’image « x » dépasse __IMG__. (too big)</td><td>The file is too heavy</td><td>Shrink it (1600 pixels wide is enough) or save it as JPG</td></tr>
<tr><td>Aucun fichier .csv trouvé dans le ZIP… (no .csv found)</td><td>The ZIP holds no file ending in <code>.csv</code></td><td>Add <code>questions.csv</code> at the root, then rebuild the ZIP</td></tr>
<tr><td>Ce fichier ZIP est illisible ou endommagé. (unreadable ZIP)</td><td>Not a real ZIP (e.g. a renamed RAR) or the transfer was interrupted</td><td>Compress to ZIP again from your system and resend</td></tr>
<tr><td>Envoyez un fichier .zip (…) ou un fichier .csv. (wrong extension)</td><td>Extension not accepted</td><td>Send only a <code>.zip</code> or a <code>.csv</code></td></tr>
<tr><td>Ligne N : champs de QCM incomplets. (incomplete multiple choice)</td><td>A multiple-choice question needs the statement and options A, B, C, D (only A and B for true/false); a cell is empty</td><td>Fill them in, or leave all four options empty for a written question</td></tr>
<tr><td>Ligne N : réponse correcte QCM invalide… (invalid correct answer)</td><td><code>correct</code> holds something other than A, B, C, D (or 1 to 4). True/false: only A or B</td><td>Write a single letter</td></tr>
<tr><td>Ligne N : énoncé / réponse attendue de question ouverte vide. (written question missing statement or answer)</td><td>A written question without a statement, or without an answer in <code>correct</code></td><td>Complete the statement and the <code>correct</code> column</td></tr>
<tr><td>Ligne N : temps invalide « x » (invalid time)</td><td><code>time_limit</code> is not a whole number from 5 to 3600</td><td>Write a number of seconds (e.g. 45) or leave it empty</td></tr>
<tr><td>Fichier CSV vide. / Aucune question valide trouvée dans le CSV. (empty / no valid question)</td><td>The file holds no usable row</td><td>Check the header row and the separator</td></tr>
<tr><td>Trop de questions (__Q__ au plus par pack). / Le ZIP contient trop de fichiers (__F__ au plus). (too many)</td><td>The package exceeds the limits</td><td>Split into several packages</td></tr>
<tr><td>Le fichier dépasse la taille maximale acceptée par le serveur (__ZIP__ Mo…) (file too large)</td><td>The ZIP is bigger than the server accepts</td><td>Shrink the pictures or split into two packages</td></tr>
<tr><td><em>Warning:</em> L’image « x » n’est utilisée par aucune question. (unused picture)</td><td>A picture in the ZIP is named on no row</td><td>Harmless; remove it from the ZIP or add it to the <code>image</code> column</td></tr>
<tr><td><em>Warning:</em> Deux images portent le nom « x »… (duplicate name)</td><td>Same name in two folders</td><td>Rename one of them</td></tr>
</tbody></table>
HTML],
 ['id' => 'limits', 'q' => 'What are the limits?', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Item</th><th>Limit</th></tr></thead><tbody>
<tr><td>Size of the uploaded file (ZIP or CSV)</td><td>__ZIP__ MB (server setting; the page refuses a bigger file before sending it)</td></tr>
<tr><td>Questions per package</td><td>__Q__</td></tr>
<tr><td>Files inside the ZIP</td><td>__F__</td></tr>
<tr><td>ZIP size once unpacked</td><td>__UNP__</td></tr>
<tr><td>Size of one picture in a package</td><td>__IMG__ (then scaled to 1600 pixels wide)</td></tr>
<tr><td>Size of a picture added with the single-question form</td><td>8 MB</td></tr>
<tr><td>Picture dimensions</td><td>40 million pixels at most (width × height)</td></tr>
<tr><td>Time for a question</td><td>5 to 3600 seconds</td></tr>
<tr><td>Checks and imports</td><td>40 per hour per teacher (each click on “Check the package” or “Import” counts as one)</td></tr>
</tbody></table>
HTML],
 ['id' => 'after', 'q' => 'After the import: where to see, fix, delete, start over?', 'a' => <<<'HTML'
<ul>
<li>Imported questions appear in the session’s “Questions” list, <strong>in the order of the file’s rows</strong>.</li>
<li>They are added to the questions already there. <strong>Nothing is replaced or de-duplicated.</strong> To start from scratch, delete the old questions first.</li>
<li>Deleting <strong>one</strong> question also deletes its picture. The delete-<strong>all</strong> button of the session also deletes all their pictures.</li>
<li>To fix an imported question (statement, option, picture): delete it and add it again with the form, or delete everything and re-import a corrected file.</li>
<li>Imported into the wrong session? Questions go to the session where you clicked: open the right session before choosing the file.</li>
<li>Do a <strong>dry run before the exam</strong>: open the session as a student (with a test account) to see how pictures and formulas display on a computer and on a phone.</li>
</ul>
HTML],
 ['id' => 'tips', 'q' => 'Tips for a well-built exam', 'a' => <<<'HTML'
<ul>
<li><strong>Options of similar length.</strong> A right answer that is clearly longer than the others gives itself away. Write the four options with roughly the same number of words.</li>
<li><strong>Spread the right answers</strong> over A, B, C and D (about a quarter each) and avoid the same letter three times in a row.</li>
<li><strong>A picture must carry necessary information</strong> (code, diagram, table, curve), not decorate. Write “see the picture” in the statement so students look for it.</li>
<li>For code, a <strong>picture</strong> of the code avoids indentation and character problems; for a diagram, a sharp PNG 1000 to 1600 pixels wide is fine.</li>
<li>Adjust the time: longer for a question that requires reading a picture or code (<code>time_limit</code> column).</li>
<li>Always keep a <strong>copy of your ZIP package</strong>: it is the simplest way to rebuild or modify the whole session.</li>
</ul>
HTML],
 ],
],
];
$T = $LIF[$lifLang];
$fill = static fn(string $h): string => strtr($h, ['__IMG__' => $lifMaxImg, '__Q__' => (string)$lifMaxQ, '__F__' => (string)$lifMaxFiles, '__UNP__' => $lifMaxUnp, '__ZIP__' => $lifZipMb]);
?>
<section id="live-import-faq" class="lif" aria-labelledby="lif-h">
    <header class="lif-head">
        <div>
            <p class="t-kicker">FAQ</p>
            <h3 id="lif-h"><?= htmlspecialchars($T['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="lif-lede"><?= htmlspecialchars($T['lede'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="lif-tools">
            <a class="t-btn t-btn-ghost" href="/teacher/bulk-import.php?template=1" download><?= htmlspecialchars($T['tpl_zip'], ENT_QUOTES, 'UTF-8') ?></a>
            <a class="t-btn t-btn-ghost" href="/teacher/sample-questions.csv" download><?= htmlspecialchars($T['tpl_csv'], ENT_QUOTES, 'UTF-8') ?></a>
            <button type="button" class="t-btn t-btn-ghost" data-lif-all="open"><?= htmlspecialchars($T['expand'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="t-btn t-btn-ghost" data-lif-all="close"><?= htmlspecialchars($T['collapse'], ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </header>
    <nav class="lif-chips" aria-label="FAQ">
        <?php foreach ($T['items'] as $i => $it): ?>
            <a href="#lif-<?= $it['id'] ?>" data-lif-open="<?= $it['id'] ?>"><?= ($i + 1) ?>. <?= htmlspecialchars($it['q'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </nav>
    <?php foreach ($T['items'] as $i => $it): ?>
        <details class="lif-item" id="lif-<?= $it['id'] ?>"<?= $i === 0 ? ' open' : '' ?>>
            <summary><span class="lif-n"><?= ($i + 1) ?></span><?= htmlspecialchars($it['q'], ENT_QUOTES, 'UTF-8') ?></summary>
            <div class="lif-body"><?= $fill($it['a']) ?></div>
        </details>
    <?php endforeach; ?>
</section>
<style>
.lif { margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--line); }
.lif-head { display: flex; flex-wrap: wrap; gap: 1rem 2rem; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem; }
.lif-head h3 { font-size: 1.6rem; margin: .1rem 0 .4rem; } .lif-lede { color: var(--ink-2); max-width: 46rem; margin: 0; }
.lif-tools { display: flex; flex-wrap: wrap; gap: .5rem; }
.lif-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 1.2rem; }
.lif-chips a { font-size: .8rem; padding: .3rem .65rem; border: 1px solid var(--line); border-radius: 999px; color: var(--ink-2); text-decoration: none; background: var(--card); }
.lif-chips a:hover { border-color: var(--clay); color: var(--ink); }
.lif-item { border: 1px solid var(--line); border-radius: 14px; background: var(--card); margin-bottom: .6rem; }
.lif-item[open] { border-color: var(--line-2, var(--line)); }
.lif-item > summary { cursor: pointer; list-style: none; padding: .9rem 1.1rem; font-weight: 600; font-size: 1.02rem; display: flex; gap: .75rem; align-items: center; }
.lif-item > summary::-webkit-details-marker { display: none; }
.lif-item > summary::after { content: '+'; margin-left: auto; font-size: 1.3rem; color: var(--ink-3); font-weight: 400; }
.lif-item[open] > summary::after { content: '−'; }
.lif-n { flex: none; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: var(--clay-soft, #f3e3d9); color: var(--ink); font-size: .8rem; display: inline-flex; align-items: center; justify-content: center; }
.lif-body { padding: 0 1.2rem 1.1rem 3.5rem; color: var(--ink); font-size: .95rem; line-height: 1.6; }
.lif-body p { margin: .6rem 0; } .lif-body ul, .lif-body ol { margin: .5rem 0 .5rem 1.4rem; padding: 0; } .lif-body ul { list-style: disc outside; } .lif-body ol { list-style: decimal outside; } .lif-body ul ul { list-style: circle outside; margin-top: .3rem; } .lif-body li { margin: .35rem 0; padding-left: .2rem; } .lif-body li::marker { color: var(--clay); font-weight: 700; }
.lif-body h5 { font-size: 1rem; font-weight: 700; margin: 1.1rem 0 .3rem; }
.lif-body code { background: var(--paper-2, rgba(0,0,0,.06)); padding: .08rem .35rem; border-radius: 5px; font-size: .86em; word-break: break-word; }
.lif-code { background: #1f201c; color: #ece9df; padding: .9rem 1.1rem; border-radius: 10px; overflow-x: auto; font-size: .82rem; line-height: 1.55; white-space: pre; margin: .7rem 0; }
.lif-note { border-left: 3px solid var(--clay); padding: .5rem .8rem; background: var(--paper-2, rgba(0,0,0,.04)); border-radius: 0 8px 8px 0; color: var(--ink-2); }
.lif-table { width: 100%; border-collapse: collapse; margin: .7rem 0; font-size: .88rem; display: block; overflow-x: auto; }
.lif-table th { text-align: left; padding: .5rem .65rem; border-bottom: 2px solid var(--line); background: var(--paper-2, rgba(0,0,0,.04)); white-space: nowrap; }
.lif-table td { padding: .5rem .65rem; border-bottom: 1px solid var(--line); vertical-align: top; min-width: 8rem; }
@media (max-width: 640px) { .lif-body { padding-left: 1.2rem; } }
</style>
<script>
(function () {
  var root = document.getElementById('live-import-faq'); if (!root) return;
  function openById(id) { var d = document.getElementById('lif-' + id); if (d) { d.open = true; d.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
  root.querySelectorAll('[data-lif-open]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); openById(a.getAttribute('data-lif-open')); }); });
  root.querySelectorAll('[data-lif-all]').forEach(function (b) { b.addEventListener('click', function () { var o = b.getAttribute('data-lif-all') === 'open'; root.querySelectorAll('details.lif-item').forEach(function (d) { d.open = o; }); }); });
  window.openLiveImportFaq = function (id) {
    openById(id || 'zip-steps');
  };
})();
</script>
