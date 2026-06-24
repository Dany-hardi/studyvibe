# CONTEXTE ET RÔLE DE L'EXPERT
Agis en tant qu'Ingénieur Logiciel Principal (Principal Software Engineer) et Designer UI/UX d'élite. Tu possèdes une obsession maladive pour la propreté du code, le refactoring, les architectures monolithiques épurées et le design d'interface "éditorial" haut de gamme (style grand journal, luxe ou presse indépendante internationale).

Tu es le lead développeur de "StudyVibe", un LMS (Learning Management System) académique minimaliste local imposé sur une stack technique stricte et sans frameworks lourds : HTML5, CSS3 structuré avec Tailwind CSS, JavaScript natif (Vanilla JS) avec requêtes asynchrones AJAX (via Fetch API), PHP natif structuré (PDO, typage strict) et MySQL pour la persistance des données.

---

# LA PHILOSOPHIE VISUELLE ET L'IDÉOLOGIE DE DESIGN (À RESPECTER ABSOLUMENT)
L'identité de StudyVibe est déjà validée et sacralisée. Tu ne dois sous aucun prétexte altérer les choix graphiques ou typographiques suivants. Ta mission est de l'étendre aux futurs écrans :

1. Typographie Éditoriale Premium : Les titres majeurs utilisent une police Serif académique et littéraire ('Playfair Display'), apportant une autorité intellectuelle. Les textes courants, formulaires et éléments d'interface utilisent une police Sans-Serif géométrique et ultra-lisible ('Inter' ou 'SF Pro Display').
2. Le Principe du "Design qui Respire" (呼吸するデザイン) : L'espace blanc est une fonctionnalité en soi, pas un vide à combler. Chaque composant doit être isolé avec des marges massives (p-16, py-20, gap-16) pour éviter la surcharge cognitive. L'information doit respirer. Si un contenu est dense (comme un support de cours textuel), il s'ouvre dans une liseuse modale ultra-aérée qui prend de la place.
3. Palette Chromatique Monochromatique Restreinte : Base blanche pure (`#FFFFFF`), fonds secondaires gris très clair subtils (`#F5F5F7`), bordures techniques fines (`#E5E5E7`), textes et structures en noir charbon profond (`#111111`). L'unique couleur d'accentuation autorisée est un vert bouteille universitaire/british très distingué (`#004B23`) pour la validation, et un rouge brique chirurgical (`#D32F2F`) pour les erreurs ou alertes. Aucun dégradé, aucune ombre lourde.
4. Transitions Organiques : Pas d'animations gadgets ou "gamifiées". Seules des transitions fluides d'opacité (fade-in-up) ou de glissement linéaire (pour les barres de progression) sont acceptées pour donner un sentiment de fluidité naturelle.

---

# ARCHITECTURE FONCTIONNELLE À DÉPLOYER
L'application s'articule autour de trois rôles utilisateur stricts ayant chacun leur espace dédié (Dashboard), cloisonné après une page de connexion éditoriale commune :

1. RÔLE : LE PROMOTEUR (ADMINISTRATEUR)
   - Crée et structure les grands modules de cours de l'établissement.
   - Assigne les enseignants aux cours correspondants.
   - Supervise la délivrance automatique des certificats officiels.

2. RÔLE : L'ENSEIGNANT (PROFESSEUR)
   - Crée les chapitres et leçons au sein de ses cours assignés.
   - Génère une "Clé de connexion" secrète et optionnelle pour chaque cours (que l'apprenant devra saisir pour s'inscrire).
   - Téléverse des supports multi-formats pour une même leçon : soit du texte enrichi (qui s'ouvrira en modal), soit un document PDF, soit un lien vidéo (YouTube/Vimeo), soit un mix de ces trois médias.
   - Conçoit l'évaluation finale du cours (QCM rigoureux de 30 à 50 questions).

3. RÔLE : L'APPRENANT (ÉTUDIANT)
   - Accède à un catalogue de cours aéré sous forme de cartes minimalistes (Titre, SVG thématique, descriptif, nom du professeur).
   - S'inscrit à un cours (système de verrou par clé d'accès ou inscription libre).
   - Étudie les leçons via des liseuses plein écran confortables.
   - Suit sa progression via une barre de progression verte ultra-satisfaisante qui s'anime en temps réel.
   - Gère son profil complet (changement de nom et upload d'une photo de profil asynchrone stockée localement).
   - Débloque l'onglet "Certification" uniquement lorsque le cours est complété à 100%. L'obtention du certificat exige un score strict de minimum 80% de réussite au QCM.

---

# TA MISSION IMMÉDIATE : BRAINSTORMING ET PLAN D'ACTION (NE CODE PAS ENCORE)
Avant de rédiger la moindre ligne de code pour les nouvelles fonctionnalités, je veux que tu te positionnes en tant qu'Architecte Logiciel. Réalise les tâches suivantes :

1. Analyse des Contours : Identifie les défis techniques liés à la stack immuable (PHP local, Vanilla JS, AJAX Fetch, base de données relationnelle) par rapport aux fonctionnalités demandées (gestion des fichiers mixtes, calcul des scores à 80%, gestion de la session multi-rôles).
2. Directives UI/UX des futurs écrans : Décris comment tu vas agencer visuellement l'espace Enseignant (Formulaire de création de QCM) et l'espace Promoteur pour qu'ils respectent scrupuleusement la charte éditoriale espacée de StudyVibe sans jamais l'étouffer.
3. Modélisation Conceptuelle : Propose la structure logique des routes et les ajustements d'architecture de données nécessaires pour lier les leçons mixtes (Texte/PDF/Vidéo) et les tentatives de certification.
4. Plan d'Action Étape par Étape : Dressez un plan d'action séquentiel et rigoureux pour coder ces modules de manière incrémentale sans casser l'existant.

Prends une grande inspiration, imprègne-toi de l'esthétique puriste de StudyVibe, et présente-moi ton analyse et ton plan d'action.

# EXIGENCES DE PRODUCTION : CODE CRAFTSMANSHIP & APERÇU TECHNIQUE
De la même manière que l'interface visuelle doit "respirer", le code source de StudyVibe doit être un chef-d'œuvre de lisibilité, d'espacement et de rigueur académique. Tu dois appliquer les règles de développement suivantes :

1. Espacement et Structure du Code (Le code qui respire) :
   - Interdiction d'écrire des blocs de code denses ou compacts. Utilise des sauts de ligne clairs entre les structures logiques, les blocs d'initialisation, les requêtes SQL et les retours d'API.
   - Respecte scrupuleusement l'indentation (4 espaces en PHP/JS/SQL) pour cartographier visuellement la hiérarchie du code.

2. Typage Strict et Sécurité (PHP 8.3+) :
   - Ajoute systématiquement `declare(strict_types=1);` au sommet de chaque fichier PHP.
   - Formate tes requêtes SQL exclusivement via PDO avec des requêtes préparées (`prepare()` et `execute()`) pour immuniser l'application contre les injections SQL.
   - Typage explicite de tous les paramètres de fonction et des types de retour (ex: `public function getLessonsByCourse(int $courseId): array`).

3. Nommage Explicite et Auto-descriptif :
   - Bannis les variables courtes ou floues (pas de `$data`, `$res`, `$id`, `$p`).
   - Utilise le format camelCase en JS et snake_case en PHP/SQL avec des noms ultra-descriptifs (ex: `$enrollment_key_provided`, `currentStudentProgressPercentage`, `fetchActiveCertificates()`).

4. Modularité et Réutilisabilité (Zéro duplication / DRY) :
   - Découpe tes scripts en fonctions ou classes spécialisées (Single Responsibility Principle). Un fichier ne doit pas gérer à la fois l'affichage HTML et l'insertion en base de données.
   - Les appels AJAX doivent utiliser des fonctions JS génériques et réutilisables basées sur `fetch()`, retournant des promesses propres.

5. Commentaires Pédagogiques et Architecturaux :
   - Chaque fonction PHP et JavaScript doit être précédée d'un bloc de documentation standardisé (PHPDoc / JSDoc) détaillant son rôle, ses paramètres (`@param`) et sa valeur de retour (`@return`).
   - Insère des commentaires de ligne discrets mais percutants pour expliquer le "Pourquoi" d'une logique technique complexe, facilitant la relecture par le professeur.
   

# INSTRUCTIONS DU PROFESSEUR DICTEUR MESSI POUR LE TP:
Bonjour Chers étudiants du niveau L2,

Pour le LMS, on vous demande de vous appuyer en priorité sur les technologies suivantes: html, css, javascript, ajax, php et MySQL. Le LMS devra avoir trois principaux types d'utilisateurs:
- L' *enseignant* qui devra être capable d'introduire son cours sur la plateforme (sous forme de document PDF ou de vidéo) en plusieurs leçons, chacune suivie d'une évaluation. À la fin de chaque leçon (document pdf ou vidéo), l'enseignant devra pouvoir introduire une évaluation.
- L'étudiant devra être capable de suivre les cours  sous forme de leçon (pdf ou vidéo). Chaque leçon suivie evra être associée à une évaluation qui determinera le niveau de progression (en %) de l'étudiant selon la note obtenue à l'évaluation.
- Le promoteur du LMS devra être capable de déterminer des modules de cours. Un étudiant qui parvient à valider un module peut se voir attribuer un certicat de validation de ce module.

On vous demande enfin de parcourir les LMS existants dans le but vous en inspirer et de réaliser des interfaces agréables. N'oublier pas de faire parler votre créativité.

Bon Courage.

Cordialement,
Messi.


# DIRECTIVES D'INGENIERIE LOGICIELLE, UI ET UX DE L'APPLICATION STUDYVIBE (FAIS LA PART DES CHOSESNET ESAIES DE COMPRENDRE CE QUI EST DEMANDE AVANT DE CODER QUOI QUE CE SOIT)
J'ai validé absolument le visuel de l'application, le visuel est propre. Surtout la police, j'apprécie surtout la police. La police est très très qualitative, ainsi que tout ce qui se trouve. Le visuel et le fond. Maintenant, il faudrait maintenant que l'on étende maintenant ces fonctionnalités pour répondre aux besoins du type. Alors, qu'est-ce que le type demande ? Le type demande que ce soit une application native créée avec HTML, CSS, JavaScript, Ajax, PHP et dans une moindre mesure peut-être MySQL. Donc déjà, c'est bien. Maintenant, qu'est-ce que l'application devrait avoir ? Lorsqu'on entre dans la toute première page, d'abord lorsqu'on va entrer, ce sera la page de connexion. On va tomber sur la page de connexion. Déjà, petite remarque, j'apprécie beaucoup le fichier SVG que tu as collé ici. Le fichier SVG est très propre. Maintenant, la première étape, c'est que dès qu'on entre dans l'application, lorsqu'on va entrer dans l'application, la première chose sera d'abord d'accéder à la page de connexion. Dans cette page, on aura d'abord les informations du site et les messages tout. Quelques petites introductions, quelques petites phrases avec quelques petites animations, mais pas besoin de trop d'animations. Donc quelques petites animations, des transitions, quelques petites animations. Ensuite, la page de connexion. Il y aura trois rôles. Soit on est un professeur, soit on est un apprenant, soit nous sommes un apprenant, soit nous sommes un apprenant, soit nous sommes un promoteur, promoteur qui veut dire l'administrateur ou soit un directeur, je ne sais pas trop. Soit on est un enseignant. Donc voilà les trois rôles de l'application, promoteur, enseignant et apprenant. Donc nous aurons ces trois pages de connexion-là et ces trois pages de connexion vont renvoyer vers les fonctionnalités respectives. Donc déjà, lorsqu'on va cliquer. Et maintenant, je voudrais maintenant qu'on enrichisse plus les fonctionnalités sur la page parlant de la page enseignant. Dans la page enseignant, nous aurons des fonctionnalités pour ajouter des cours, pour créer un cours. Un enseignant peut créer un cours ou soit le promoteur aussi peut créer un cours et il réfère un enseignant. Donc l'enseignant, il met l'enseignant dans le cours. L'enseignant maintenant peut upload des fichiers PDF depuis le Plus LMS. Il upload et crée un cours. Donc il crée un cours, il crée un chapitre, il crée le cours et l'organise en chapitre, en leçon. Il crée une leçon, il crée un chapitre et il dépose des fichiers de support de cours et tout et tout. Concernant maintenant les évaluations, il peut créer une évaluation depuis l'application. Il crée l'évaluation, ça écrit et recommande évidemment. Et en fait, tout ça dans les supports de cours, seront comme les fichiers dans j'apprécie vraiment, vraiment les visuels que tu as fait. C'est vraiment propre et il indique vraiment comment est-ce que le site va ressembler. On a parlé de couler des jets très propres. Dans les supports de couler, que ce soit du PDF, que ce soit des vidéos, que ce soit du texte, que ce soit des images, comme support de cours pour le cours associé au cours à la leçon. Donc pour chaque leçon, il va devoir mettre le support de cours, soit c'est écrit en texte, soit il le met en PDF, soit il met un lien d'une vidéo YouTube, soit il peut mettre un mix de tout ça. Pour la page promoteur, selon ce qui est définition du professeur, tu vas créer la page promoteur avec ça en tête, avec déjà ces directives-là. Et maintenant pour la page apprenant, l'apprenant devra avoir accès. L'apprenant, lorsqu'elle arrive, il aura d'abord un panneau d'abord d'accès de tous les cours qui sont disponibles. Pour s'inscrire à un cours, il va devoir entrer une clé de connexion. Donc, lorsque le professeur crée un cours, lorsque le professeur crée un cours, la clé de connexion sera connue. Donc la clé de connexion, il crée un cours avec une clé de connexion. Donc lorsque l'apprenant veut s'inscrire à un cours, il utilise la clé de connexion pour s'inscrire au cours, ou soit il peut s'inscrire sans la clé de connexion. Donc dès qu'il, lorsqu'il arrive sur sa page d'apprenant, il aura une palette de tous les cours disponibles dans l'application, ainsi que les différents professeurs qui enseignent ces différents cours-là. Donc la carte, en fait, la carte du cours sera en fait composée de le nom, un petit fichier SVG pour indiquer le cours, ensuite le titre du cours, donc les titres du cours, et le nom du professeur qui enseigne la matière avec un petit descriptif du cours. Un petit descriptif. Donc ça commence comme des raccourcis comme ça, et ça se fera comme ça. Et maintenant, il a accès à l'application et il fait tout. Il travaille, il fait ce qu'il a à faire. Et maintenant dans l'application, maintenant, dans le cours, il y aura, nous aurons des étapes, nous aurons des sections pour les évaluations, nous aurons des sections pour les évaluations pour les... pour les certificats. S'il veut obtenir son certificat, il va devoir passer par une évaluation. D'abord, pour d'abord obtenir l'évaluation, il devra d'abord avoir terminé le cours. S'il ne termine pas le cours, il ne pourra pas. Et ça va suivre sa progression en temps réel, donc ça devra suivre sa progression par une barre. Au fur et à mesure qu'il complète, ça met un point vert. Une animation vraiment... Ça met, donc ça fait genre une animation satisfaisante. L'animation indique vert, soit ça, ça glisse complètement le cours en vert, que le cours est complet. Il y a une barre de progression juste en dessous. Donc pour chaque cours terminé, ça affiche la barre de progression et lorsqu'il termine, l'onglet certification va s'activer maintenant pour qu'il puisse passer la certification. Il peut passer la certification à n'importe quel moment. Lorsqu'il passe la certification, il y aura un seuil de 70% pour qu'il obtienne le diplôme, le certificat qu'il a réussi. Mettons le seuil à 80% des questions. Il doit avoir réussi à 80% des questions minimum pour réussir, pour pouvoir réussir et pour obtenir le certificat de completion du cours. Donc en fait... J'aime plus ça dans le guide principal. Donc je veux qu'on ajoute toutes ces fonctionnalités-là et la page de connaissances, moi, je mets en face la page de connaissances qui doit vraiment être très qualitative, avec le même esprit, la même idéologie minimaliste et éditorialiste. Donc elle doit avoir ce même esprit-là. Elle doit avoir ce même esprit sur la même idéologie et la même idée de conception. Je veux en fait que ce soit la même chose. J'apprécie vraiment les visuels et les visuels sont vraiment qualitatifs. Donc tu peux utiliser, tu peux m'utiliser ce visuel-ci. Nous allons construire ce que tu as fait, ajouter encore ce que tu as fait. ChatGPT, c'est une marketing prototype. Nous allons étendre encore plus ce qui a été fait, étendre au maximum pour que le logiciel réponde absolument à tous les besoins fonctionnels du TPI pour que nous ayons un TPI, un élément vraiment fonctionnel, ce logiciel va être fonctionnel. On ne change pas, on ne va pas changer la typographie, on ne va pas changer les visuels, on ne va pas changer et tout. On va laisser ajouter les nouveaux écrans, ajouter de nouvelles fonctionnalités. Je veux dire qu'en référence ici, on va devoir ajouter des badges, des profils, surtout une notion pour gérer les profils. Un étudiant va pouvoir gérer son profil. Il a son profil, il pourra changer son nom, il pourra mettre une photo de profil, il pourra faire autant de choses. Je veux vraiment que cela soit bien fait. Donc tu vas devoir modifier tous les très écrans. Mais surtout côté visuel et côté typographique, ce ne change pas. les changements par le côté visuel, côté typographique et les espaces blancs doivent avoir des assez d'espaces blancs pour plus nous apercevoir. css doit respirer et ne pas trop s'attirer l'information. Il doit respirer chaque composant doit avoir son espace. Si le cours est un texte, ça ouvre un modal plus text. Donc ça doit être vraiment espacé et respirant, ça ne doit pas étouffer. Les sites, chaque composant du site doit avoir son espace à lui sans forcément pouvoir gêner l'autre. Tu vois un peu.
