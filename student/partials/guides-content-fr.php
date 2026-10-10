<?php
/** Student guides, French. Returns [id => ['q' => question, 't' => teaser, 'a' => answer html]]. Formal tone, "vous". */
return [
'create-account' => ['q' => 'Comment créer mon compte ?', 't' => 'Trois minutes, quatre informations, un e-mail à confirmer.', 'a' => <<<'HTML'
<p>Vous pouvez créer votre compte de deux façons : depuis la page d’accueil de StudyVibe, ou directement depuis le lien d’un cours que votre enseignant vous a envoyé. Dans les deux cas, le principe est le même.</p>
<ol>
<li>Choisissez <strong>Créer un compte</strong> et indiquez votre <strong>nom complet</strong> et votre <strong>adresse e-mail</strong>.</li>
<li>Saisissez votre <strong>numéro de téléphone</strong> (par exemple <code>6 12 34 56 78</code> ou <code>+237 612 345 678</code>). Il sert aux rappels d’évaluations et il vous sera demandé de le vérifier par SMS après votre première connexion.</li>
<li>Choisissez un <strong>mot de passe d’au moins 8 caractères</strong>.</li>
<li>Validez, puis ouvrez le message de StudyVibe reçu dans votre boîte mail et cliquez sur le bouton de confirmation.</li>
</ol>
<p class="lif-note">Votre nom, tel que vous l’écrivez ici, figurera sur vos relevés et sur vos certificats. Prenez le temps de l’écrire comme sur vos documents officiels.</p>
<p>Une adresse e-mail ne peut servir qu’à un seul compte. Si le message « Cette adresse électronique est déjà enregistrée » s’affiche, c’est que vous avez déjà un compte : utilisez l’onglet « Déjà inscrit » ou la procédure de mot de passe oublié.</p>
HTML],
'email-confirm' => ['q' => 'Je n’ai pas reçu l’e-mail de confirmation', 't' => 'Où regarder, combien de temps le lien reste valable, comment en demander un nouveau.', 'a' => <<<'HTML'
<p>Après votre inscription, StudyVibe vous envoie un message pour confirmer que l’adresse est bien la vôtre. S’il tarde, voici quoi faire, dans l’ordre.</p>
<ol>
<li><strong>Patientez deux ou trois minutes</strong>, puis actualisez votre boîte de réception.</li>
<li>Regardez dans les <strong>courriers indésirables (spam)</strong> et dans l’onglet « Promotions » si votre messagerie en propose un.</li>
<li>Vérifiez que l’adresse saisie à l’inscription ne contient <strong>pas de faute de frappe</strong>.</li>
<li>Sur la page « Confirmez votre e-mail », utilisez le bouton qui permet de <strong>renvoyer le message</strong>. Un nouvel e-mail est envoyé tout de suite.</li>
</ol>
<p>Le lien de confirmation reste valable <strong>48 heures</strong>. Au-delà, il n’est plus accepté, mais rien n’est perdu : demandez-en simplement un nouveau depuis la même page.</p>
<p>Une fois le bouton de l’e-mail cliqué, revenez sur la page ouverte : elle vérifie automatiquement votre confirmation et vous laisse entrer.</p>
HTML],
'login-help' => ['q' => 'Je n’arrive pas à me connecter ou j’ai oublié mon mot de passe', 't' => 'Messages d’erreur, pause de sécurité et réinitialisation du mot de passe.', 'a' => <<<'HTML'
<h5>Le message « Adresse électronique ou mot de passe incorrect »</h5>
<p>Vérifiez l’adresse (celle de l’inscription) et le mot de passe, en faisant attention aux majuscules et au verrouillage du clavier.</p>
<h5>Plusieurs essais ratés d’affilée</h5>
<p>Par sécurité, après <strong>5 tentatives échouées</strong>, la connexion est suspendue pendant <strong>15 minutes</strong>. Attendez ce délai avant de réessayer, ou réinitialisez directement votre mot de passe.</p>
<h5>Réinitialiser mon mot de passe</h5>
<ol>
<li>Sur la page de connexion, choisissez <strong>Mot de passe oublié</strong> et saisissez votre adresse e-mail.</li>
<li>Ouvrez le message reçu et cliquez sur le lien. Il est valable <strong>2 heures</strong>.</li>
<li>Choisissez un nouveau mot de passe, puis connectez-vous.</li>
</ol>
<p class="lif-note">Pour protéger les comptes, le message affiché est toujours le même, que l’adresse existe ou non (« Si cette adresse existe, un e-mail a été envoyé »). Si rien n’arrive, regardez dans vos spams et vérifiez l’adresse.</p>
<h5>Le message « Ce compte a été désactivé »</h5>
<p>Votre compte a été suspendu par l’administration de la plateforme. Contactez-la : ni vous ni votre enseignant ne pouvez le réactiver vous-même.</p>
HTML],
'profile' => ['q' => 'Mon profil, ma photo et mon matricule', 't' => 'Ce que vous pouvez modifier, et pourquoi le matricule compte.', 'a' => <<<'HTML'
<p>Ouvrez votre profil en cliquant sur votre nom en bas du menu (ou sur votre photo, sur téléphone).</p>
<ul>
<li><strong>Photo</strong> : JPG, PNG ou GIF, 2 Mo au maximum.</li>
<li><strong>Nom complet</strong> : vous pouvez le corriger. Il apparaît sur vos relevés et vos certificats.</li>
<li><strong>Adresse e-mail</strong> : elle <strong>ne peut pas être modifiée</strong>, car elle identifie votre compte. Si vous avez perdu l’accès à cette boîte, contactez l’administration.</li>
<li><strong>Matricule</strong> : il figure sur vos relevés, sur vos rapports et dans les exports de vos enseignants. Il est demandé pour enregistrer vos notes et pour déposer un devoir.</li>
<li><strong>Préférences</strong> : langue (français ou anglais) et thème clair ou sombre.</li>
</ul>
<h5>Les contrôles faits sur le matricule</h5>
<ul>
<li>Il doit avoir un <strong>format valide</strong> ; sinon le message « Ce matricule ne semble pas valide » apparaît.</li>
<li>Les chiffres du début indiquent l’<strong>année</strong> : une année qui n’est pas encore arrivée est refusée.</li>
<li>Il doit être <strong>unique</strong> : s’il est déjà enregistré sur un autre compte, la plateforme vous le dit. Corrigez-le si vous vous êtes trompé ; s’il est bien le vôtre, contactez l’administration.</li>
</ul>
<p>Si on vous demande « Votre matricule est requis », saisissez-le simplement dans la fenêtre : cela ne prend que quelques secondes et vous n’aurez plus à le refaire.</p>
HTML],
'security' => ['q' => 'Téléphone, SMS et double authentification', 't' => 'Vérifier ou changer votre numéro, recevoir des rappels, protéger votre compte.', 'a' => <<<'HTML'
<p>Tout se trouve dans <strong>Profil → Sécurité du compte</strong>.</p>
<h5>Votre numéro de téléphone</h5>
<p>Pour vérifier ou changer votre numéro, saisissez-le, demandez le code, puis tapez les 6 chiffres reçus par SMS. Le code est <strong>valable 10 minutes</strong>. Si le SMS tarde, vérifiez le numéro puis redemandez un code.</p>
<h5>Rappels par SMS</h5>
<p>Vous pouvez recevoir un SMS lorsqu’une évaluation en direct est programmée dans l’un de vos cours. C’est un réglage que vous activez ou désactivez à votre convenance ; les codes de vérification, eux, sont toujours envoyés.</p>
<h5>Double authentification (recommandée)</h5>
<ol>
<li>Installez une application d’authentification sur votre téléphone (Google Authenticator, Microsoft Authenticator, Authy…).</li>
<li>Scannez le QR code affiché (ou saisissez la clé indiquée si le scan est impossible).</li>
<li>Saisissez le code à 6 chiffres que l’application affiche pour activer la protection.</li>
</ol>
<p>À chaque connexion, on vous demandera alors ce code en plus du mot de passe. <strong>Conservez précieusement vos codes de secours</strong> : chacun ne sert qu’une fois et remplace l’application si vous perdez votre téléphone. Ils ne sont affichés qu’une seule fois.</p>
<p class="lif-note">Certains établissements imposent la double authentification : dans ce cas, la plateforme vous invite à l’activer avant de continuer.</p>
HTML],
'notifications' => ['q' => 'Notifications et e-mails : que vais-je recevoir ?', 't' => 'La cloche, les e-mails et ce qui les déclenche.', 'a' => <<<'HTML'
<p>La <strong>cloche</strong> (en bas du menu sur ordinateur, en haut sur téléphone) rassemble vos notifications. Un petit compteur indique celles que vous n’avez pas lues ; « Tout marquer comme lu » le remet à zéro.</p>
<p>Vous êtes prévenu, dans l’application et le plus souvent aussi par e-mail, lorsque :</p>
<ul>
<li>votre enseignant <strong>répond à votre question</strong> sous une leçon ou à votre demande ;</li>
<li>un <strong>devoir est noté</strong> et que la note est publiée, ou qu’une <strong>nouvelle version</strong> vous est demandée ;</li>
<li>une <strong>évaluation en direct</strong> est programmée ou lancée, et <strong>15 minutes avant son début</strong> si vous y êtes inscrit ;</li>
<li>une <strong>décision</strong> est prise sur une contestation ;</li>
<li>vous obtenez un <strong>certificat</strong>.</li>
</ul>
<p>Si vous ne voyez pas un e-mail attendu, regardez dans les courriers indésirables. Une note de devoir que l’enseignant a choisi de publier plus tard ne génère aucun message avant la publication : c’est normal.</p>
HTML],

'join-course' => ['q' => 'Comment rejoindre un cours ?', 't' => 'Par le lien de votre enseignant ou par le catalogue.', 'a' => <<<'HTML'
<h5>Avec le lien envoyé par votre enseignant</h5>
<ol>
<li>Ouvrez le lien. Vous arrivez sur la page de présentation du cours (titre, module, enseignant).</li>
<li>Si vous êtes déjà connecté, il suffit de cliquer sur <strong>S’inscrire maintenant</strong>.</li>
<li>Si vous n’avez pas encore de compte, choisissez <strong>Créer un compte</strong> ; si vous en avez un, l’onglet <strong>Déjà inscrit</strong>. Vous revenez ensuite automatiquement sur la page du cours pour finir l’inscription.</li>
</ol>
<h5>Depuis votre espace</h5>
<p>Allez dans <strong>Cours → Catalogue</strong>, repérez le cours (la mention « Accès libre » ou « Clé requise » vous dit tout de suite si une clé est demandée) puis cliquez sur <strong>S’inscrire</strong>.</p>
<p>Une fois inscrit, le cours apparaît dans <strong>Cours → Mes cours</strong> et sur votre accueil, avec votre progression. L’inscription est immédiate ; elle ne demande pas la validation de l’enseignant.</p>
HTML],
'enroll-key' => ['q' => 'Le cours demande une clé d’inscription', 't' => 'Où la trouver, comment la saisir, que faire si elle est refusée.', 'a' => <<<'HTML'
<p>Certains enseignants protègent l’accès à leur cours par une <strong>clé d’inscription</strong> (par exemple <code>CODE2026</code>). C’est <strong>votre enseignant qui vous la communique</strong> : en cours, par message ou par e-mail. Elle n’est pas affichée ailleurs.</p>
<ol>
<li>Cliquez sur <strong>S’inscrire</strong>. Une fenêtre « Ce cours demande une clé » s’ouvre.</li>
<li>Saisissez la clé et validez.</li>
</ol>
<p>Vous n’avez pas à vous soucier des majuscules, des espaces en trop ni des tirets typographiques que certains claviers de téléphone ajoutent : la plateforme les ignore pour la comparaison.</p>
<p>Si vous obtenez « Clé d’inscription incorrecte », recopiez-la depuis le message de l’enseignant plutôt que de la retaper, et vérifiez qu’il s’agit bien de la clé de <em>ce</em> cours. Si cela ne fonctionne toujours pas, demandez-lui de la confirmer : il a pu la changer.</p>
HTML],
'enroll-blocked' => ['q' => 'Je ne peux pas m’inscrire : « pas publié », « pas encore ouvert », « terminé »', 't' => 'Les trois raisons pour lesquelles une inscription est refusée.', 'a' => <<<'HTML'
<p>L’inscription est refusée quand le calendrier du cours ne le permet pas. Le message vous indique la raison exacte.</p>
<ul>
<li><strong>« Ce cours n’est pas encore publié. »</strong> L’enseignant prépare encore son contenu. Revenez plus tard ou demandez-lui quand il l’ouvrira.</li>
<li><strong>« Les inscriptions ouvrent le … »</strong> Le cours a une date de début. Vous pourrez vous inscrire à partir de ce jour.</li>
<li><strong>« La période d’inscription à ce cours est terminée. »</strong> La date de fin est passée. Seul l’enseignant peut décider de ré-ouvrir le cours : écrivez-lui, en lui expliquant votre situation.</li>
</ul>
<p>Si le lien affiche « Cours introuvable » ou « Lien invalide », il est incomplet ou a été mal copié. Demandez-en un nouveau à votre enseignant.</p>
HTML],
'my-courses' => ['q' => 'Retrouver mes cours et suivre ma progression', 't' => 'L’accueil, « Mes cours » et le pourcentage d’avancement.', 'a' => <<<'HTML'
<ul>
<li><strong>Accueil</strong> : la leçon à reprendre, la prochaine évaluation, votre dernier résultat et vos certificats. Le bouton « Reprendre » vous ramène à la leçon où vous vous étiez arrêté.</li>
<li><strong>Cours → Mes cours</strong> : tous vos cours, avec le nombre de leçons terminées et un pourcentage de progression. La recherche accepte un titre de cours, un module ou le nom d’un enseignant.</li>
<li><strong>Résultats</strong> : la progression, le meilleur score et le nombre de tentatives, cours par cours.</li>
</ul>
<p>La progression est le rapport entre les leçons que vous avez terminées et celles qui sont publiées. Elle peut donc baisser si l’enseignant ajoute de nouvelles leçons. À 100 %, l’examen final de certification s’ouvre.</p>
HTML],
'library' => ['q' => 'La bibliothèque : supports, PDF et vidéos', 't' => 'Les ressources déposées par vos enseignants, hors des leçons.', 'a' => <<<'HTML'
<p>Dans <strong>Cours → Bibliothèque</strong>, vous trouvez les supports que vos enseignants déposent pour compléter leurs leçons : PDF, vidéos, notes de cours et liens vers des ressources en ligne.</p>
<ul>
<li>Chaque ressource propose le bouton qui convient : <strong>Télécharger</strong> un document, <strong>Voir la vidéo</strong>, <strong>Lire</strong> un texte ou ouvrir une ressource en ligne.</li>
<li>Seules les ressources de <strong>vos</strong> cours sont visibles.</li>
</ul>
<p>Si la bibliothèque est vide, c’est que vos enseignants n’y ont encore rien publié ; elle se remplira au fil du cours.</p>
HTML],

'lesson-flow' => ['q' => 'Comment se déroule une leçon, et comment la valider ?', 't' => 'Contenu, validation, quiz : l’ordre à suivre.', 'a' => <<<'HTML'
<p>Chaque leçon propose un contenu (texte, document PDF, vidéo, ou un mélange), parfois suivi d’un quiz et d’un devoir. Voici le parcours habituel.</p>
<ol>
<li><strong>Ouvrez la leçon</strong> depuis « Mes cours » ou le bouton « Reprendre » de l’accueil. Le plan du cours vous permet de voir où vous en êtes.</li>
<li><strong>Parcourez le contenu en entier</strong> : lisez le texte, tournez toutes les pages du PDF, regardez la vidéo jusqu’au bout.</li>
<li>Le bouton <strong>« Marquer la leçon comme terminée »</strong> s’active, et le <strong>quiz</strong> se débloque s’il y en a un.</li>
<li>Répondez aux questions du quiz : la leçon est validée quand <strong>toutes les questions ont reçu une réponse</strong>, qu’elle soit juste ou fausse.</li>
<li>S’il y a un devoir, il se rend dans la même leçon (voir la section « Devoirs »).</li>
</ol>
<p>Les leçons s’enchaînent dans l’ordre : une fois la leçon validée, la suivante s’ouvre. Quand toutes les leçons sont terminées, l’examen final apparaît dans votre espace.</p>
HTML],
'lesson-locked' => ['q' => 'Une leçon est verrouillée ou expirée : pourquoi ?', 't' => 'L’ordre obligatoire et les dates limites fixées par l’enseignant.', 'a' => <<<'HTML'
<h5>« Terminez d’abord la leçon … pour ouvrir celle-ci »</h5>
<p>Les leçons s’ouvrent dans l’ordre. Une leçon reste verrouillée tant que la précédente n’est pas validée. Le message cite la leçon à terminer : ouvrez-la, terminez son contenu puis son quiz, et la suivante se déverrouille.</p>
<h5>« Le délai d’accès à cette leçon est dépassé »</h5>
<p>L’enseignant peut fixer une date limite pour le quiz d’une leçon. Passée cette date, la leçon n’est plus accessible pour la valider, et elle apparaît comme <strong>expirée</strong>. Si tout un cours est expiré, la plateforme l’indique (« Toutes les leçons de ce cours ont expiré »). La date reste modifiable par votre enseignant : si vous avez un empêchement justifié, écrivez-lui depuis <strong>Résultats → Mes demandes</strong>.</p>
<h5>L’examen final est grisé</h5>
<p>Il apparaît avec la mention « Certification après 100 % » tant que vous n’avez pas terminé toutes les leçons du cours. Il peut aussi être fermé par une date limite d’évaluation fixée par l’enseignant.</p>
HTML],
'video-pdf' => ['q' => 'Vidéos et documents PDF : ce qu’il faut savoir', 't' => 'Pas de saut en avant, pages à lire, plusieurs vidéos dans l’ordre.', 'a' => <<<'HTML'
<p>Pour que la validation d’une leçon reflète un vrai apprentissage, la plateforme vérifie que le contenu a réellement été consulté.</p>
<h5>Les vidéos</h5>
<ul>
<li>Regardez la vidéo <strong>dans l’ordre et jusqu’au bout</strong>. Un saut en avant n’est pas pris en compte (« Pas de saut en avant : regardez la vidéo dans l’ordre »).</li>
<li>Si des passages n’ont pas été vus, le lecteur vous invite à reprendre un peu plus tôt.</li>
<li>Quand une leçon comporte <strong>plusieurs vidéos</strong>, la suivante se débloque lorsque la précédente est terminée.</li>
<li>Si la connexion coupe, le message « Connexion impossible pour le moment » apparaît : réessayez dans un instant, votre progression déjà enregistrée n’est pas perdue.</li>
</ul>
<h5>Les documents PDF</h5>
<ul>
<li>Le compteur « Pages lues » avance à mesure que vous tournez les pages. Lisez chaque page pour continuer.</li>
<li>Vous pouvez agrandir, réduire, passer en plein écran ou télécharger le document.</li>
<li>Si un document ne s’ouvre pas dans la plateforme, utilisez « Télécharger » et lisez-le sur votre appareil.</li>
</ul>
HTML],
'lesson-quiz' => ['q' => 'Le quiz de la leçon : comment ça marche ?', 't' => 'Réponse enregistrée au clic, correction immédiate, score en pourcentage.', 'a' => <<<'HTML'
<ul>
<li>Le quiz se <strong>débloque</strong> quand le contenu est terminé. Tant qu’il est verrouillé, un message vous dit ce qu’il reste à faire.</li>
<li>Chaque réponse est <strong>enregistrée dès que vous la choisissez</strong> ; il n’y a pas de bouton « envoyer » à la fin.</li>
<li>La <strong>correction s’affiche tout de suite</strong> : « Correct » ou « Incorrect », avec la bonne réponse et, quand l’enseignant l’a prévue, une explication.</li>
<li>Un compteur indique le nombre de questions restantes.</li>
<li>La leçon est validée quand toutes les questions ont reçu une réponse. Votre <strong>score est le pourcentage de bonnes réponses</strong> ; il apparaît dans <strong>Résultats → Quiz de leçon</strong>.</li>
</ul>
<p class="lif-note">Les quiz de leçon servent à vous entraîner et à suivre votre progression. La certification d’un cours, elle, se joue sur l’examen final (voir la section « Certificats »).</p>
HTML],
'notes' => ['q' => 'Prendre des notes pendant une leçon', 't' => 'Notes horodatées sur les vidéos, enregistrées sur votre appareil.', 'a' => <<<'HTML'
<p>Dans la lecture d’une leçon, l’onglet <strong>Notes</strong> vous permet de garder vos remarques.</p>
<ul>
<li>Pour une <strong>vidéo</strong>, indiquez la minute concernée au format <code>mm:ss</code> (par exemple <code>12:40</code>) puis votre remarque.</li>
<li>Pour un texte ou un PDF, écrivez simplement votre note.</li>
<li>Vous pouvez supprimer une note à tout moment.</li>
</ul>
<p class="lif-note">Vos notes sont enregistrées <strong>sur l’appareil que vous utilisez</strong>, pas sur votre compte. Vous les retrouverez sur le même appareil et le même navigateur, mais pas sur un autre téléphone ou un autre ordinateur. Si vous effacez les données de votre navigateur, elles disparaissent : pour les conserver, copiez-les dans un document personnel.</p>
HTML],
'ai' => ['q' => 'L’assistant de la leçon : ce qu’il peut faire', 't' => 'Résumer, expliquer simplement, vous poser des questions.', 'a' => <<<'HTML'
<p>L’onglet <strong>Assistant</strong> de la leçon vous propose trois aides, à partir du contenu de la leçon que vous avez ouverte :</p>
<ul>
<li><strong>Résumer</strong> la leçon en quelques points ;</li>
<li><strong>Expliquer simplement</strong> les notions qui vous semblent difficiles ;</li>
<li><strong>Me poser des questions</strong> pour vous tester, sans note à la clé.</li>
</ul>
<p>Vous pouvez aussi lui poser une question libre sur la leçon. Ouvrez d’abord une leçon : l’assistant a besoin d’un contenu pour répondre.</p>
<p class="lif-note">L’assistant est une aide à la révision, il peut se tromper. Il ne remplace ni la leçon ni votre enseignant, et ses réponses n’ont aucune valeur de correction officielle. En cas de doute, posez votre question à l’enseignant dans l’onglet « Questions ».</p>
HTML],
'ask-teacher' => ['q' => 'Poser une question à mon enseignant sur une leçon', 't' => 'L’onglet « Questions » : qui voit quoi, et comment est-on prévenu ?', 'a' => <<<'HTML'
<ol>
<li>Ouvrez la leçon concernée, puis l’onglet <strong>Questions</strong>.</li>
<li>Écrivez votre question de manière précise (ce que vous avez compris, ce qui vous bloque) et publiez-la.</li>
<li>Votre enseignant est prévenu. Vous le serez à votre tour, par <strong>notification et par e-mail</strong>, dès qu’il répond.</li>
</ol>
<ul>
<li>Seuls les étudiants <strong>inscrits au cours</strong> et son enseignant peuvent lire et écrire dans ces échanges.</li>
<li>L’enseignant peut masquer un message qui n’a pas sa place ; restez courtois et centré sur la leçon.</li>
<li>Il y a une limite d’envois par heure pour éviter les abus : si elle est atteinte, patientez un peu avant d’écrire de nouveau.</li>
</ul>
<p>Pour un sujet qui ne concerne pas une leçon précise (un problème de note, un résultat), utilisez plutôt <strong>Résultats → Écrire à mon enseignant</strong> (voir la section « Aide »).</p>
HTML],

'asg-submit' => ['q' => 'Comment rendre un devoir ?', 't' => 'Fichier ou lien, formats acceptés, taille maximale.', 'a' => <<<'HTML'
<p>Le devoir se trouve dans la leçon concernée, sous le quiz. Il indique son titre, ses consignes, les formats acceptés, la date limite et, quand l’enseignant en a prévu une, la <strong>grille de notation</strong> (« Voici sur quoi vous serez noté »).</p>
<ol>
<li>Lisez les consignes et la grille.</li>
<li>Joignez un <strong>document</strong> (20 Mo au maximum) <strong>et/ou</strong> indiquez un <strong>lien</strong> direct vers votre travail (GitHub, Drive…). L’un des deux au moins est obligatoire.</li>
<li>Si vous le souhaitez, ajoutez une note pour l’enseignant.</li>
<li>Cliquez sur <strong>Déposer mon devoir</strong>. Un message confirme l’envoi et votre enseignant est prévenu.</li>
</ol>
<ul>
<li><strong>Votre nom et votre matricule viennent de votre profil</strong> : on ne les saisit pas à chaque dépôt. Si votre matricule manque, la plateforme vous invite à le compléter d’abord.</li>
<li>Le <strong>contenu</strong> du fichier est contrôlé, pas seulement son extension : un PDF doit vraiment être un PDF. Un fichier dont le contenu ne correspond pas à son format, un exécutable ou un script est refusé.</li>
<li>Si le fichier dépasse 20 Mo, compressez-le ou déposez-le sur un espace de stockage et indiquez le lien.</li>
</ul>
HTML],
'asg-deadline' => ['q' => 'Date limite, retard et nouvelle version', 't' => 'Ce qui se passe après l’échéance, et comment renvoyer votre travail.', 'a' => <<<'HTML'
<h5>Après la date limite</h5>
<p>L’enseignant choisit une règle, et la plateforme l’applique de façon stricte. Soit le dépôt est <strong>fermé</strong> (« La date limite est dépassée : le dépôt est fermé »), soit il reste <strong>ouvert avec la mention « en retard »</strong> sur votre travail. Le message affiché sous le devoir vous dit laquelle s’applique.</p>
<h5>Rendre une nouvelle version</h5>
<ul>
<li><strong>Avant la date limite</strong>, vous pouvez en général redéposer une version améliorée. Certains enseignants désactivent cette possibilité : le devoir est alors verrouillé après le premier dépôt (« l’enseignant n’autorise pas de nouveau dépôt »).</li>
<li>Si votre enseignant <strong>demande une nouvelle version</strong>, le statut devient « Nouvelle version demandée », son commentaire s’affiche, et vous pouvez renvoyer votre travail <strong>même après la date limite</strong>.</li>
<li>Les versions précédentes sont conservées avec leur note et leur commentaire : rien n’est effacé.</li>
</ul>
<h5>Les statuts</h5>
<p>À rendre · Rendu, en attente de note · Noté · Nouvelle version demandée · Non rendu (échéance passée sans dépôt). Ils sont visibles dans <strong>Résultats → Mes devoirs</strong>.</p>
<p class="lif-note">Vous avez un empêchement sérieux avant l’échéance ? Écrivez à votre enseignant à l’avance depuis « Écrire à mon enseignant » : lui seul peut vous accorder du temps.</p>
HTML],
'asg-mark' => ['q' => 'Voir ma note, mon retour et la grille de notation', 't' => 'Où trouver la note, quand elle apparaît, et la copie annotée.', 'a' => <<<'HTML'
<p>Votre note et le retour de l’enseignant s’affichent dans la leçon et dans <strong>Résultats → Mes devoirs</strong> (« Voir le retour »).</p>
<ul>
<li><strong>La note</strong> est donnée sur le maximum fixé par l’enseignant (20 par défaut).</li>
<li><strong>Avec une grille</strong>, vous voyez le détail critère par critère : points obtenus, niveau et commentaire. La note finale est la somme des critères.</li>
<li><strong>La copie annotée</strong> (ou le corrigé) est téléchargeable depuis le même endroit quand l’enseignant l’a jointe.</li>
<li>Vous êtes prévenu par notification et par e-mail.</li>
</ul>
<h5>« En attente de correction » alors que mon enseignant a déjà corrigé ?</h5>
<p>Certains enseignants choisissent de <strong>publier toutes les notes en même temps</strong>, après l’avoir relu l’ensemble des copies. Tant que la publication n’a pas eu lieu, vous ne voyez rien : ni note, ni commentaire, ni grille, ni copie annotée, et vous ne recevez aucun message. C’est volontaire et équitable pour toute la classe. Vous serez prévenu dès que votre note sera publiée.</p>
<p class="lif-note">Les notes de devoirs sont indiquées à part. Elles ne comptent ni dans le résultat d’un cours ni dans le certificat, qui reposent sur l’examen final.</p>
HTML],
'asg-review' => ['q' => 'Ma note me semble injuste : puis-je demander une révision ?', 't' => 'Oui : une demande écrite, une réponse de l’enseignant.', 'a' => <<<'HTML'
<p>Oui, c’est prévu et c’est simple. Dans <strong>Résultats → Mes devoirs</strong>, le bouton <strong>Demander une révision</strong> apparaît à côté d’une note publiée.</p>
<ol>
<li>Cliquez sur le bouton : une fenêtre s’ouvre.</li>
<li>Expliquez <strong>précisément</strong> ce que vous souhaitez faire relire (par exemple « la partie 3 est traitée dans l’annexe »). Il faut au moins <strong>20 caractères</strong>.</li>
<li>Envoyez. L’enseignant est prévenu par notification et e-mail.</li>
</ol>
<ul>
<li>Il y a <strong>une seule demande par note</strong> : soignez-la. Après envoi, la mention « Demande envoyée » s’affiche.</li>
<li>L’enseignant vous répond ; vous êtes prévenu par e-mail et notification et vous lisez sa réponse dans <strong>Résultats → Mes demandes</strong>. Une demande reçoit une seule réponse.</li>
<li>Si la note est modifiée, c’est l’enseignant qui le fait dans son propre outil ; vous le verrez alors dans la leçon.</li>
</ul>
<p class="lif-note">Argumentez : citez la consigne, la grille ou l’endroit de votre copie. Une demande précise et courtoise reçoit une réponse plus utile.</p>
HTML],
'asg-original' => ['q' => 'Les travaux sont-ils comparés entre eux ?', 't' => 'Ce que l’enseignant peut vérifier, et ce que cela signifie pour vous.', 'a' => <<<'HTML'
<p>L’enseignant dispose d’un outil qui compare les travaux rendus par les étudiants d’un même devoir et signale les <strong>passages communs</strong>. Les consignes recopiées par tout le monde (l’énoncé, un modèle de page de garde) sont ignorées.</p>
<ul>
<li>L’outil ne compare que les étudiants <strong>entre eux</strong>, pas avec Internet.</li>
<li>Un pourcentage élevé n’est jamais une preuve : c’est une raison, pour l’enseignant, de relire attentivement et, au besoin, de vous demander des explications.</li>
<li>Vous ne voyez pas cette analyse. Elle n’entre dans aucune note automatique.</li>
</ul>
<p><strong>Le conseil est simple :</strong> travaillez avec vos camarades si c’est autorisé, mais rédigez vous-même votre rendu. Si vous citez une source, indiquez-la. En cas de doute sur ce qui est permis, demandez à votre enseignant avant le dépôt.</p>
HTML],

'eval-kinds' => ['q' => 'Évaluation en direct ou évaluation libre : quelle différence ?', 't' => 'Une séance à heure fixe, ou une épreuve à faire à votre rythme.', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th></th><th>En direct</th><th>Libre (asynchrone)</th></tr></thead><tbody>
<tr><td><strong>Quand ?</strong></td><td>À l’heure fixée par l’enseignant. Tous les étudiants commencent ensemble.</td><td>À tout moment, <strong>jusqu’à la date limite</strong>.</td></tr>
<tr><td><strong>Rythme</strong></td><td>L’enseignant lance les questions ; elles s’enchaînent pour tout le monde.</td><td>Vous avancez seul, question après question.</td></tr>
<tr><td><strong>Où la trouver ?</strong></td><td>Onglet « Évaluations » (« Rejoindre ») ou lien/code de la séance.</td><td>Onglet « Évaluations » et la page des évaluations libres.</td></tr>
<tr><td><strong>Rappel</strong></td><td>E-mail 15 minutes avant (et SMS si activé).</td><td>Pas de rappel : surveillez la date limite.</td></tr>
</tbody></table>
<p>Dans les deux cas, chaque question a une <strong>durée</strong> annoncée, la note est un <strong>pourcentage de bonnes réponses</strong> et il n’y a <strong>aucun point négatif</strong> : une question sans réponse vaut simplement 0.</p>
<p>La liste des évaluations qui vous concernent, avec le compte à rebours avant la clôture, se trouve dans l’onglet <strong>Évaluations</strong>.</p>
HTML],
'eval-join' => ['q' => 'Comment rejoindre une évaluation en direct ?', 't' => 'Le lien, la salle d’attente, et le cas sans compte.', 'a' => <<<'HTML'
<h5>Si vous avez un compte StudyVibe</h5>
<ol>
<li>Connectez-vous, puis ouvrez le <strong>lien de la séance</strong> reçu de l’enseignant, ou cliquez sur <strong>Rejoindre</strong> dans l’onglet « Évaluations » de votre espace (ou « Entrer dans la salle » quand la séance est en cours).</li>
<li>Vous êtes reconnu automatiquement : votre nom et votre adresse viennent de votre compte.</li>
<li>Cliquez sur <strong>Rejoindre la séance</strong> : vous entrez dans la <strong>salle d’attente</strong>.</li>
<li>Restez sur la page. Au signal de l’enseignant, un compte à rebours démarre et les questions apparaissent toutes seules.</li>
</ol>
<h5>Si vous n’avez pas de compte</h5>
<p>Vous pouvez participer en tant qu’<strong>invité</strong>, avec seulement votre nom complet et une adresse e-mail. Seules les adresses se terminant par <strong>gmail.com</strong>, <strong>icloud.com</strong> ou <strong>facsciences-uy1.cm</strong> sont acceptées. Vos résultats vous sont envoyés à cette adresse. Si l’adresse correspond déjà à un compte StudyVibe, la page le reconnaît et reprend le nom du compte.</p>
<h5>Messages possibles</h5>
<ul>
<li><strong>« Séance introuvable »</strong> : le code ou le lien est faux ou périmé. Redemandez-le.</li>
<li><strong>« Téléévaluation désactivée »</strong> : l’enseignant a fermé la séance.</li>
<li><strong>« Date limite dépassée »</strong> : pour une évaluation libre, l’échéance est passée.</li>
</ul>
HTML],
'eval-reminder' => ['q' => 'Les rappels avant une évaluation', 't' => 'E-mail 15 minutes avant, SMS en option, et si l’heure change.', 'a' => <<<'HTML'
<p>Pour une évaluation <strong>en direct</strong>, vous êtes prévenu à plusieurs moments :</p>
<ul>
<li><strong>À la programmation</strong> : vous êtes en général informé qu’une séance est prévue (date, heure, cours).</li>
<li><strong>15 minutes avant le début</strong> : un e-mail de rappel est envoyé automatiquement, <strong>une seule fois</strong>, à tous les étudiants inscrits. Il contient l’heure, le lien et les consignes utiles.</li>
<li><strong>Par SMS</strong> si vous avez activé les rappels dans <em>Profil → Sécurité du compte</em> et vérifié votre numéro.</li>
<li><strong>Dans l’application</strong> : une notification et, sur l’accueil, le bloc « Prochaine évaluation » avec le compte à rebours.</li>
</ul>
<p><strong>Si l’enseignant déplace la séance</strong>, le rappel est recalculé : vous êtes prévenu du nouvel horaire et recevez un nouveau rappel 15 minutes avant la nouvelle heure.</p>
<p>Pour une évaluation <strong>libre</strong>, il n’y a pas de rappel à heure fixe puisque vous la passez quand vous voulez : notez la date limite.</p>
<p class="lif-note">Pas de rappel reçu ? Regardez vos courriers indésirables et vérifiez l’adresse de votre compte. Le rappel est envoyé aux étudiants inscrits à la séance.</p>
HTML],
'eval-prepare' => ['q' => 'Bien se préparer avant le début', 't' => 'Matériel, connexion, calme : la liste à cocher avant de rejoindre la salle.', 'a' => <<<'HTML'
<p>Un peu de préparation évite l’essentiel des problèmes le jour J. Faites ces vérifications <strong>10 à 15 minutes avant</strong> l’heure :</p>
<ul>
<li><strong>Appareil chargé</strong> et branché si possible. Un téléphone suffit, mais un écran plus grand est plus confortable pour lire un énoncé avec une image.</li>
<li><strong>Connexion stable</strong> : préférez le Wi-Fi ou un forfait de données suffisant. Évitez les endroits où le réseau est capricieux.</li>
<li><strong>Connectez-vous à l’avance</strong> et vérifiez que vous apercevez bien la salle d’attente de la séance.</li>
<li><strong>Un seul onglet</strong>, notifications du téléphone coupées. Si l’enseignant a activé la surveillance de l’examen, quitter la fenêtre est enregistré (voir la carte dédiée).</li>
<li><strong>De quoi écrire</strong> si l’enseignant l’autorise : brouillon, calculatrice.</li>
<li><strong>Un endroit calme</strong>. Une question dure quelques dizaines de secondes à quelques minutes : on n’a pas le temps de revenir en arrière.</li>
</ul>
<p>Enfin, relisez le message de votre enseignant : il y précise souvent ce qui est autorisé ou non.</p>
HTML],
'eval-during' => ['q' => 'Pendant l’épreuve : minuteur, réponses, questions écrites', 't' => 'Comment répondre, ce qui est enregistré, ce qui arrive quand le temps passe.', 'a' => <<<'HTML'
<ul>
<li>Chaque question s’affiche avec son <strong>minuteur</strong>. La durée par défaut est fixée par l’enseignant, et certaines questions ont leur propre durée.</li>
<li>Pour une question à choix, cliquez sur votre réponse puis sur <strong>Soumettre ma réponse</strong>. Le message « Réponse enregistrée » confirme que c’est pris en compte ; patientez ensuite : la question suivante arrive.</li>
<li>Les propositions peuvent être <strong>mélangées</strong> d’un étudiant à l’autre. La correction suit le texte de la bonne réponse : ne vous fiez pas à l’ordre vu par votre voisin.</li>
<li>Pour une <strong>question à réponse écrite</strong>, tapez votre réponse dans le champ. La comparaison ne tient pas compte des majuscules, des accents, des espaces en trop ni de la ponctuation ; écrivez simplement le mot ou la valeur demandés, sans phrase inutile.</li>
<li>Une image peut accompagner l’énoncé : cliquez sur « Agrandir l’image » pour la voir en grand.</li>
<li>Quand le temps est écoulé, la question se ferme <strong>automatiquement</strong>. Une question sans réponse vaut 0.</li>
<li>À la fin, un message « Évaluation terminée ! » apparaît et vos résultats sont prêts.</li>
</ul>
<p class="lif-note">Le calcul est simple : bonnes réponses ÷ nombre total de questions × 100. Il n’y a pas de point négatif ; répondre vaut toujours mieux que laisser vide.</p>
HTML],
'eval-connection' => ['q' => 'Ma connexion a coupé, ou j’ai rechargé la page', 't' => 'Ce qui est sauvegardé et comment reprendre sans perdre votre place.', 'a' => <<<'HTML'
<h5>Coupure brève en répondant</h5>
<p>Si le réseau devient instable au moment où vous répondez, un bandeau orange apparaît : « Connexion instable — Réponse mise en attente ». Ne cliquez pas à répétition : votre réponse est conservée et <strong>envoyée automatiquement</strong> dès que la connexion revient.</p>
<h5>La page s’est rechargée ou fermée</h5>
<ul>
<li>Rouvrez le lien de la séance <strong>avec le même compte</strong> (ou le même navigateur et la même adresse e-mail si vous êtes invité) : la plateforme retrouve votre inscription et vous remet dans la salle.</li>
<li>En évaluation en direct, la séance <strong>continue sans vous attendre</strong> : vous reprenez à la question en cours. Les questions déjà fermées ne peuvent pas être rejouées ; elles comptent 0 si elles n’ont pas de réponse.</li>
<li>Ne quittez pas volontairement la séance en cours d’épreuve, sauf si vous renoncez : une inscription sans résultat est alors supprimée.</li>
</ul>
<h5>Évaluation libre</h5>
<p>Le minuteur d’une question déjà commencée continue de s’écouler pendant un rechargement : ne comptez pas dessus pour gagner du temps. Vous pouvez, <strong>jusqu’à la date limite</strong>, relancer l’évaluation depuis la liste (bouton « Recommencer ») ; la nouvelle tentative <strong>remplace</strong> la précédente.</p>
<p class="lif-note">Panne longue ou problème technique pendant une évaluation en direct ? Écrivez à votre enseignant dès que vous le pouvez, avec l’heure et ce que vous avez vu : c’est lui qui décide de la suite (voir « Mon résultat a été annulé »).</p>
HTML],
'eval-integrity' => ['q' => 'Quitter la fenêtre pendant l’épreuve', 't' => 'La surveillance de l’examen, quand elle est activée par l’enseignant.', 'a' => <<<'HTML'
<p>Pour garantir l’équité, un enseignant peut activer la <strong>surveillance de l’examen</strong> sur sa séance. Concrètement, quand elle est activée :</p>
<ul>
<li>Chaque fois que vous <strong>quittez la fenêtre</strong> de l’examen (changement d’onglet, d’application, écran de veille…), le fait est <strong>compté</strong>.</li>
<li>À votre retour, un bandeau vous le rappelle : « Vous avez quitté la fenêtre de l’examen. Cela a été enregistré et votre enseignant peut le voir. »</li>
<li>L’enseignant voit ce compteur dans son analyse de la séance. Cela ne vous disqualifie pas automatiquement : l’interprétation lui revient.</li>
</ul>
<p>Pendant les questions, le <strong>copier-coller</strong> et le <strong>clic droit</strong> sont aussi désactivés sur l’énoncé.</p>
<h5>Comment éviter une fausse alerte</h5>
<ul>
<li>Fermez les autres applications et coupez les notifications du téléphone avant de commencer.</li>
<li>Évitez de consulter vos messages. Si une urgence vous oblige à quitter, dites-le ensuite à votre enseignant.</li>
</ul>
HTML],
'eval-results' => ['q' => 'Où voir mon résultat, mon rapport et mon relevé ?', 't' => 'Score, correction question par question, PDF et e-mail.', 'a' => <<<'HTML'
<ul>
<li><strong>Immédiatement après la séance</strong> : la page de fin propose « Consulter mes résultats et explications en ligne ».</li>
<li><strong>Plus tard</strong> : dans votre espace, onglet <strong>Évaluations → Passées</strong> et dans <strong>Résultats</strong>, avec « Voir le rapport ».</li>
<li><strong>Par e-mail</strong> : vos résultats sont envoyés à l’adresse utilisée pour participer (c’est aussi le cas pour un invité sans compte).</li>
</ul>
<h5>Dans le rapport</h5>
<ul>
<li>Votre <strong>score final</strong>, en pourcentage de bonnes réponses, avec un repère de réussite à <strong>50 %</strong>.</li>
<li>La <strong>revue question par question</strong> : votre réponse, la bonne réponse et, quand l’enseignant l’a rédigée, sa justification.</li>
<li>Un bouton pour <strong>imprimer</strong> ou <strong>télécharger en PDF</strong>.</li>
</ul>
<p>Le repère de 50 % est un affichage : la décision de réussite d’un cours appartient à l’enseignant et à l’établissement. Les évaluations en direct ne remplacent pas l’examen de certification.</p>
<p>Un résultat qui vous semble erroné (une question mal formulée, deux bonnes réponses possibles) ? Utilisez <strong>Question sur ce résultat</strong> à côté du score pour écrire à votre enseignant.</p>
HTML],
'eval-cancelled' => ['q' => 'Mon résultat a été annulé : que puis-je faire ?', 't' => 'Ce que cela signifie, et comment le contester une fois.', 'a' => <<<'HTML'
<p>L’enseignant peut <strong>annuler</strong> un résultat, par exemple en cas de problème technique, d’incident ou de suspicion de fraude. Votre résultat apparaît alors avec la mention <strong>« Résultat annulé »</strong> et n’est plus compté.</p>
<h5>Contester</h5>
<ol>
<li>Cliquez sur <strong>Contester</strong> à côté du résultat (ou sur le lien reçu dans l’e-mail d’annulation, y compris si vous aviez participé en invité).</li>
<li>Expliquez calmement ce qui s’est passé, avec les faits (heure, coupure, message d’erreur). Il faut au moins <strong>20 caractères</strong>, et 2 000 au plus.</li>
<li>Envoyez : la mention devient <strong>« Contestation en cours »</strong> et l’enseignant est prévenu par notification et e-mail.</li>
</ol>
<ul>
<li>Vous pouvez <strong>contester une seule fois</strong> par résultat : préparez bien votre message.</li>
<li>L’enseignant <strong>rétablit</strong> votre résultat ou <strong>maintient</strong> l’annulation, en vous expliquant sa décision. Vous en êtes informé et pouvez la relire via <strong>Voir la décision</strong>.</li>
<li>Seul le propriétaire du résultat peut le contester.</li>
</ul>
HTML],
'eval-missed' => ['q' => 'J’ai manqué une évaluation', 't' => 'Ce qui est possible selon le type d’évaluation, et comment demander.', 'a' => <<<'HTML'
<h5>Évaluation libre</h5>
<p>Si la date limite n’est pas passée, vous pouvez encore la passer. Si elle l’est, la plateforme affiche « Date limite dépassée » et la participation n’est plus possible de votre côté.</p>
<h5>Évaluation en direct</h5>
<p>Une fois la séance terminée, elle ne peut plus être rejouée par l’étudiant. Seul votre enseignant peut décider d’une solution (nouvelle séance, autre modalité). Écrivez-lui sans attendre :</p>
<ol>
<li>Allez dans <strong>Résultats → Écrire à mon enseignant</strong>.</li>
<li>Choisissez le cours, donnez un objet clair (« Absence à l’évaluation du 12 octobre ») et expliquez la raison.</li>
<li>Joignez, si possible, les éléments qui la justifient dans votre message (dates, circonstances).</li>
</ol>
<p>Pour ne plus rater une séance : activez les rappels SMS, vérifiez votre numéro, et ajoutez la date à votre agenda dès que la séance est programmée.</p>
HTML],

'final-exam' => ['q' => 'L’examen final de certification', 't' => 'Quand il s’ouvre, sa durée, le seuil de 80 % et les 3 tentatives.', 'a' => <<<'HTML'
<ul>
<li><strong>Quand ?</strong> Il s’ouvre quand vous avez terminé toutes les leçons du cours (« Certification après 100 % »). Il peut aussi être fermé après une date limite fixée par l’enseignant, ou à la fin du cours.</li>
<li><strong>Durée</strong> : l’épreuve a un temps limité (90 minutes par défaut, ajusté par l’enseignant). Le temps restant est affiché, et <strong>l’envoi est automatique</strong> à la fin (« Temps écoulé : envoi automatique »).</li>
<li><strong>Réponses</strong> : répondez à toutes les questions avant de valider (« Valider l’épreuve »).</li>
<li><strong>Seuil de réussite : 80 %.</strong> Au-dessus, le certificat est créé et envoyé par e-mail. En dessous, aucun certificat n’est délivré.</li>
<li><strong>Tentatives</strong> : au maximum <strong>3 par période de 24 heures</strong>. Le nombre restant s’affiche à l’ouverture. Passé ce plafond : « Limite de 3 tentatives par 24 heures atteinte. Réessayez demain. »</li>
</ul>
<h5>Si vous échouez</h5>
<p>Un <strong>relevé de tentative</strong> est ajouté à l’onglet Résultats (« Certifications non validées »). Il résume votre score, sans détailler les réponses ni donner la correction : révisez les leçons, puis recommencez après votre pause.</p>
<p>« Abandonner » met fin à l’épreuve en cours : ne l’utilisez que si vous renoncez vraiment.</p>
HTML],
'certificate' => ['q' => 'Obtenir, imprimer et vérifier mon certificat', 't' => 'Où il se trouve, son code de vérification et la page publique.', 'a' => <<<'HTML'
<p>Un certificat est délivré automatiquement quand vous obtenez <strong>au moins 80 %</strong> à l’examen final d’un cours terminé. Vous en êtes prévenu à l’écran, et un e-mail est envoyé.</p>
<ul>
<li>Retrouvez-le dans l’onglet <strong>Certificats</strong> : « Ouvrir et imprimer » l’affiche en pleine page, prêt à imprimer ou à enregistrer en PDF.</li>
<li>Il porte <strong>votre nom</strong>, le titre du cours, la date de délivrance et un <strong>code de vérification unique</strong>, avec un QR code.</li>
<li>Toute personne (un employeur, un établissement) peut <strong>vérifier son authenticité</strong> sur la page publique de vérification, en saisissant le code ou en scannant le QR code. Aucun compte n’est nécessaire.</li>
</ul>
<p class="lif-note">Votre nom est celui de votre profil au moment de la délivrance. S’il comporte une faute, corrigez-le dans votre profil puis contactez l’administration au sujet du document déjà délivré.</p>
<p>Pas de certificat alors que vous pensez l’avoir mérité ? Vérifiez dans <strong>Résultats</strong> que votre meilleur score atteint 80 %, et que le cours est bien terminé à 100 %.</p>
HTML],
'transcript' => ['q' => 'Mes résultats et mon relevé de notes', 't' => 'Ce que contient l’onglet Résultats et comment imprimer le relevé.', 'a' => <<<'HTML'
<p>L’onglet <strong>Résultats</strong> récapitule votre parcours :</p>
<ul>
<li><strong>Chiffres clés</strong> : cours terminés, moyenne de vos certifications, temps d’étude.</li>
<li><strong>Par cours</strong> : progression, meilleur score, nombre de tentatives.</li>
<li><strong>Quiz de leçon</strong> : leçon, date et score.</li>
<li><strong>Certifications non validées</strong> : un rapport par tentative.</li>
<li><strong>Mes devoirs</strong> : statut, échéance et note (voir la section « Devoirs »).</li>
<li><strong>Mes demandes à mes enseignants</strong> : vos questions et leurs réponses.</li>
</ul>
<h5>Le relevé imprimable</h5>
<p>Le bouton <strong>Relevé imprimable</strong> génère un document daté qui récapitule votre progression et vos tentatives, avec votre nom et votre matricule. Il est donné à titre informatif (« non contractuel »). Pour un document officiel, adressez-vous à l’administration de votre établissement.</p>
HTML],

'contact-teacher' => ['q' => 'Écrire à mon enseignant', 't' => 'Révision de note, question sur un résultat, message libre.', 'a' => <<<'HTML'
<p>Une note à revoir, une question sur un résultat, une absence à justifier : tout passe par <strong>Résultats → Mes demandes à mes enseignants</strong>. Votre enseignant garde une trace de chaque demande et vous êtes prévenu de sa réponse.</p>
<table class="lif-table"><thead><tr><th>Type de demande</th><th>Où la lancer</th><th>Limite</th></tr></thead><tbody>
<tr><td>Révision d’une note de devoir</td><td>Bouton « Demander une révision » dans « Mes devoirs »</td><td>Une demande par note publiée</td></tr>
<tr><td>Question sur un résultat d’évaluation</td><td>Bouton « Question sur ce résultat » dans « Évaluations »</td><td>Une demande par résultat</td></tr>
<tr><td>Message libre</td><td>« Écrire à mon enseignant » : choisissez le cours et l’objet</td><td>Un cours dans lequel vous êtes inscrit</td></tr>
</tbody></table>
<ul>
<li>Écrivez au moins <strong>20 caractères</strong> (2 000 au plus) et soyez précis.</li>
<li>Statuts : « En attente de réponse » puis « Répondu ». La réponse apparaît sous votre demande, avec une notification et un e-mail.</li>
<li>Il y a une limite d’envois par heure pour éviter les abus.</li>
</ul>
<p>Pour une question sur le contenu d’une leçon, préférez l’onglet <strong>Questions</strong> de la leçon : la réponse profitera aussi aux autres étudiants du cours.</p>
HTML],
'privacy' => ['q' => 'Mes données et ma vie privée', 't' => 'Ce qui est conservé, à quoi cela sert, et vos droits.', 'a' => <<<'HTML'
<p>StudyVibe conserve votre <strong>adresse e-mail et votre nom</strong> pour gérer votre compte, ainsi que votre <strong>temps d’étude</strong> et vos <strong>réponses aux quiz</strong> pour calculer vos résultats. Il n’y a <strong>aucun traceur publicitaire</strong>.</p>
<ul>
<li>Vos <strong>notes personnelles</strong> prises pendant les leçons restent sur votre appareil.</li>
<li>Votre <strong>numéro de téléphone</strong> sert aux rappels et aux codes de vérification.</li>
<li>Votre enseignant voit votre progression, vos résultats et vos devoirs <strong>dans ses propres cours</strong>, pas ceux des autres.</li>
<li>Les fichiers de vos devoirs ne sont téléchargeables que par vous, par l’enseignant du cours et par l’administration.</li>
</ul>
<h5>Vos droits</h5>
<p>Vous pouvez demander à <strong>consulter</strong>, <strong>corriger</strong> ou <strong>supprimer</strong> vos données à tout moment. Pour cela, écrivez à l’administration de la plateforme. La politique de confidentialité complète est accessible depuis le lien « Politique de confidentialité » en bas des pages.</p>
HTML],
'devices' => ['q' => 'Téléphone, tablette ou ordinateur : suis-je bien équipé ?', 't' => 'Compatibilité, mode sombre, langue et confort de lecture.', 'a' => <<<'HTML'
<ul>
<li>L’espace étudiant fonctionne sur <strong>ordinateur, tablette et téléphone</strong>. Sur téléphone, la navigation passe en bas de l’écran et la cloche en haut.</li>
<li>Utilisez un <strong>navigateur récent</strong> (Chrome, Edge, Firefox, Safari), à jour.</li>
<li>La <strong>langue</strong> (FR / EN) se change en un clic ; le <strong>thème sombre</strong> est disponible pour les yeux fatigués.</li>
<li>Les <strong>vidéos</strong> demandent une connexion correcte ; les <strong>PDF</strong> et le texte sont plus légers.</li>
<li>Si une page ne s’affiche pas comme prévu, essayez d’actualiser, puis de vous reconnecter. Désactivez les bloqueurs de publicités sur le site si une vidéo ou un document ne se charge pas.</li>
</ul>
<p>Pour passer une évaluation, un écran un peu plus grand est préférable, mais un téléphone suffit pour répondre.</p>
HTML],
'still-stuck' => ['q' => 'Je n’ai pas trouvé ma réponse', 't' => 'Qui contacter selon la nature du problème.', 'a' => <<<'HTML'
<table class="lif-table"><thead><tr><th>Votre problème</th><th>Qui contacter</th></tr></thead><tbody>
<tr><td>Contenu d’une leçon, consigne d’un devoir, barème, règle d’une évaluation</td><td>Votre <strong>enseignant</strong> : onglet « Questions » de la leçon, ou « Écrire à mon enseignant »</td></tr>
<tr><td>Note de devoir ou résultat d’évaluation</td><td>Votre <strong>enseignant</strong> : « Demander une révision » ou « Question sur ce résultat »</td></tr>
<tr><td>Résultat annulé</td><td>Votre <strong>enseignant</strong> : bouton « Contester »</td></tr>
<tr><td>Compte désactivé, e-mail à changer, matricule déjà utilisé, suppression de données</td><td>L’<strong>administration</strong> de la plateforme</td></tr>
<tr><td>Clé d’inscription, cours non publié, date d’ouverture ou de fin</td><td>Votre <strong>enseignant</strong></td></tr>
</tbody></table>
<p>Quand vous écrivez, donnez le <strong>cours</strong>, la <strong>date</strong> et ce que vous avez vu à l’écran (une capture d’écran aide beaucoup). Plus votre message est précis, plus vite la réponse est utile.</p>
HTML],
];
