<?php
/**
 * Privacy policy, FR and EN, rendered by privacy.php.
 *
 * Block types inside a section: p (paragraph), ul (bullet list), steps (numbered list), note (highlighted box),
 * table (head + rows, each row is an array of cells). Strings may contain <strong>, <a>, <code> (trusted text, not user input).
 * Keep the two languages in the same order, the page reads them by index.
 */
return [

'fr' => [
    'title'    => 'Politique de confidentialité',
    'desc'     => 'Quelles données StudyVibe collecte, pourquoi, qui peut les voir, combien de temps elles sont gardées et comment exercer vos droits.',
    'kicker'   => 'Confidentialité et données personnelles',
    'h1'       => 'Vos données, <em>expliquées en détail.</em>',
    'lede'     => 'StudyVibe sert à apprendre, à passer des examens et à obtenir des certificats. Pour y arriver, la plateforme doit connaître quelques informations sur vous. Cette page dit lesquelles, ce que nous en faisons, qui y a accès, et comment vous gardez la main dessus.',
    'updated'  => 'Dernière mise à jour : 8 octobre 2026',
    'version'  => 'Version 2.0',
    'back'     => 'Retour à l’accueil',
    'toc'      => 'Dans cette page',
    'top'      => 'Haut de page',
    'short_h'  => 'L’essentiel en une minute',
    'short'    => [
        ['Ce que nous gardons', 'Votre nom, votre e-mail, votre mot de passe (sous forme chiffrée), et pour les étudiants le matricule. Ensuite, ce que vous faites sur la plateforme : leçons lues, réponses aux quiz, notes, devoirs rendus.'],
        ['Ce que nous ne faisons jamais', 'Aucune vente de données, aucune publicité, aucun traceur publicitaire. Vos résultats ne sont pas utilisés pour vous profiler à des fins commerciales.'],
        ['Qui peut voir quoi', 'Vos enseignants voient votre progression et vos notes dans leurs cours. L’équipe d’administration voit les comptes pour les gérer. Un certificat peut être vérifié publiquement avec son code.'],
        ['Combien de temps', 'Tant que votre compte existe, puis un délai court après sa fermeture. Les journaux de sécurité sont effacés automatiquement au bout de 90 jours (connexions) et d’un an (audit).'],
        ['Vos droits', 'Consulter, corriger, exporter, supprimer vos données, vous opposer à un usage ou retirer un consentement : un simple e-mail suffit, réponse sous 30 jours au plus.'],
    ],
    'sections' => [
        [
            'id' => 'scope', 'h' => 'Qui nous sommes et à qui s’adresse ce texte',
            'blocks' => [
                ['p' => '<strong>StudyVibe</strong> est une plateforme d’apprentissage en ligne : cours structurés en chapitres et leçons, quiz, devoirs, évaluations en direct, certificats et suivi de progression. Elle est exploitée par l’équipe StudyVibe, représentée par le Promoteur de la plateforme, qui est le responsable du traitement de vos données au sens de la réglementation sur la protection des données personnelles.'],
                ['p' => 'Cette politique s’applique à toute personne qui utilise StudyVibe, quel que soit son rôle :'],
                ['ul' => [
                    '<strong>les étudiants</strong>, qui suivent des cours, passent des quiz et des examens et reçoivent des certificats ;',
                    '<strong>les enseignants</strong>, qui créent des cours, lancent des évaluations en direct et suivent leurs étudiants ;',
                    '<strong>le Promoteur et l’équipe d’administration</strong>, qui gèrent les comptes, les certificats et la plateforme ;',
                    '<strong>les participants invités</strong> à une évaluation en direct, qui peuvent y entrer avec une simple adresse e-mail sans créer de compte ;',
                    '<strong>les visiteurs</strong> du site public et les personnes qui vérifient un certificat.',
                ]],
                ['p' => 'Elle vaut pour le site web, pour les e-mails envoyés par la plateforme et pour les documents qu’elle génère (relevés, rapports, certificats). Si votre établissement utilise StudyVibe pour ses propres cours, il peut avoir ses propres règles en plus : ce texte décrit ce que fait la plateforme elle-même.'],
            ],
        ],
        [
            'id' => 'data', 'h' => 'Les données que nous traitons',
            'blocks' => [
                ['p' => 'Nous ne collectons que ce dont le service a besoin pour fonctionner. Voici l’inventaire complet, par catégorie.'],
                ['table' => [
                    'head' => ['Catégorie', 'Ce que cela contient', 'D’où cela vient'],
                    'rows' => [
                        ['<strong>Compte</strong>', 'Nom complet, adresse e-mail, mot de passe (jamais lisible : seule une empreinte chiffrée est stockée), rôle (étudiant, enseignant, promoteur), langue, date de création, état de vérification de l’e-mail.', 'Vous, à l’inscription et dans votre profil.'],
                        ['<strong>Identité étudiante</strong>', 'Matricule, unique pour chaque étudiant, et photo de profil si vous en ajoutez une.', 'Vous, dans votre profil.'],
                        ['<strong>Parcours d’apprentissage</strong>', 'Cours auxquels vous êtes inscrit, dernière leçon ouverte, leçons terminées, vidéos terminées, temps passé sur chaque leçon, badges obtenus.', 'Générées par votre usage de la plateforme.'],
                        ['<strong>Évaluations</strong>', 'Réponses aux quiz de leçon, tentatives et scores aux examens de certification, résultats des évaluations en direct.', 'Vous, en répondant ; calculées par la plateforme.'],
                        ['<strong>Évaluations en direct</strong>', 'Pour un participant invité : adresse e-mail, nom saisi, réponses et score. Pour un étudiant connecté : le lien avec son compte.', 'Vous, en rejoignant la séance.'],
                        ['<strong>Devoirs et fichiers</strong>', 'Fichiers ou liens que vous rendez, votre commentaire, votre nom et votre matricule tels que saisis, date de dépôt.', 'Vous, au moment du dépôt.'],
                        ['<strong>Échanges</strong>', 'Questions et commentaires publiés sous une leçon, réponses des enseignants, notifications reçues.', 'Vous et les enseignants.'],
                        ['<strong>Assistant IA</strong>', 'Les messages que vous écrivez à l’assistant, accompagnés du texte de la leçon concernée pour qu’il puisse répondre. Voir la section dédiée.', 'Vous, en utilisant l’assistant.'],
                        ['<strong>Certificats</strong>', 'Code unique, cours ou module concerné, date de délivrance, nom de l’étudiant et de l’enseignant.', 'Générés lorsque vous réussissez.'],
                        ['<strong>Communications</strong>', 'Adresse e-mail et préférence d’abonnement à la lettre d’information ; historique des e-mails transactionnels envoyés (confirmation, réinitialisation de mot de passe, résultats).', 'Vous, en vous inscrivant ; la plateforme.'],
                        ['<strong>Sécurité et journaux</strong>', 'Adresse IP et e-mail utilisés lors des tentatives de connexion ; journal d’audit des actions importantes (inscription, connexion, changement de rôle, délivrance d’un certificat, modification de mot de passe).', 'Collectées automatiquement par les serveurs.'],
                        ['<strong>Appareil et navigateur</strong>', 'Cookie de session, cookie de langue, préférences d’affichage (thème sombre), brouillons locaux. Détail dans la section sur les cookies.', 'Votre navigateur.'],
                        ['<strong>Statistiques de fréquentation</strong>', 'Compteurs quotidiens anonymes (pages vues, étapes d’inscription) et, à l’inscription, le canal d’arrivée (par exemple un lien ou un QR code de campagne). Aucune adresse IP ni identifiant n’y est associé.', 'Collectées automatiquement.'],
                    ],
                ]],
                ['p' => 'Nous ne demandons pas de données sensibles (origine, opinions, santé, données biométriques) et nous vous invitons à ne pas en saisir dans les zones de texte libres (commentaires, devoirs, messages à l’assistant).'],
            ],
        ],
        [
            'id' => 'purposes', 'h' => 'Pourquoi nous utilisons ces données',
            'blocks' => [
                ['p' => 'Chaque usage a une raison précise et une base légale. Nous n’utilisons pas vos données pour autre chose que ce qui est écrit ici.'],
                ['table' => [
                    'head' => ['Usage', 'Données concernées', 'Base légale'],
                    'rows' => [
                        ['Créer et protéger votre compte, vous connecter, réinitialiser un mot de passe', 'Compte, sécurité', 'Exécution du service que vous avez demandé'],
                        ['Vous donner accès aux cours, enregistrer votre progression et débloquer les leçons dans l’ordre', 'Parcours, évaluations', 'Exécution du service'],
                        ['Calculer vos notes, votre taux de réussite et délivrer des certificats vérifiables', 'Évaluations, certificats, matricule', 'Exécution du service ; intérêt légitime à garantir la valeur d’un certificat'],
                        ['Permettre aux enseignants de suivre leurs étudiants et d’exporter les résultats de leurs cours', 'Parcours, évaluations, matricule, nom', 'Exécution du service ; mission d’enseignement de l’établissement'],
                        ['Faire fonctionner les évaluations en direct, avec ou sans compte', 'Évaluations en direct', 'Exécution du service'],
                        ['Répondre à vos questions via l’assistant IA', 'Assistant IA', 'Votre demande, à chaque message'],
                        ['Envoyer des e-mails de service (confirmation, résultats, alertes de cours)', 'Compte, communications', 'Exécution du service'],
                        ['Envoyer la lettre d’information de la plateforme', 'Communications', 'Votre consentement, retirable en un clic'],
                        ['Détecter les abus, bloquer les tentatives répétées de connexion, assurer la traçabilité', 'Sécurité et journaux', 'Intérêt légitime de sécurité'],
                        ['Respecter une obligation légale ou répondre à une demande valable d’une autorité', 'Selon la demande', 'Obligation légale'],
                    ],
                ]],
                ['note' => '<strong>Aucune décision automatique à effet juridique.</strong> Un score de quiz ou d’examen est calculé automatiquement, mais la délivrance ou le refus d’un diplôme officiel reste de la responsabilité de l’établissement et peut être revue par un enseignant ou par le Promoteur.'],
            ],
        ],
        [
            'id' => 'visibility', 'h' => 'Qui peut voir vos données',
            'blocks' => [
                ['p' => 'L’accès est limité par rôle. Voici ce que chacun peut consulter.'],
                ['table' => [
                    'head' => ['Qui', 'Ce qu’il peut voir'],
                    'rows' => [
                        ['<strong>Vous</strong>', 'Tout ce qui vous concerne : profil, progression, notes, relevés, certificats, devoirs, notifications.'],
                        ['<strong>Vos enseignants</strong>', 'Pour les cours dont ils sont titulaires uniquement : votre nom, votre matricule, votre progression, vos réponses aux quiz, vos devoirs et vos questions. Ils ne voient pas votre mot de passe ni vos autres cours.'],
                        ['<strong>Les autres étudiants</strong>', 'Votre nom, uniquement à côté des commentaires que vous publiez sous une leçon. Ni vos notes, ni votre progression, ni votre e-mail.'],
                        ['<strong>Le Promoteur et l’administration</strong>', 'La liste des comptes, leurs rôles et statuts, les certificats, les journaux d’audit et les statistiques globales, afin de gérer la plateforme et d’aider en cas de problème.'],
                        ['<strong>Toute personne avec un code de certificat</strong>', 'La page de vérification affiche le nom de l’étudiant, le cours, l’enseignant et la date de délivrance, rien de plus. C’est voulu : un recruteur doit pouvoir confirmer qu’un certificat est authentique.'],
                        ['<strong>Les prestataires techniques</strong>', 'Seulement ce dont ils ont besoin pour leur tâche. Voir la section suivante.'],
                    ],
                ]],
                ['p' => 'Le personnel qui a accès à des données personnelles est tenu à la confidentialité. Les accès d’administration sont nominatifs et leurs actions importantes sont consignées dans le journal d’audit.'],
            ],
        ],
        [
            'id' => 'processors', 'h' => 'Prestataires et services tiers',
            'blocks' => [
                ['p' => 'StudyVibe ne vend ni ne loue vos données. Pour fonctionner, la plateforme s’appuie néanmoins sur quelques services techniques, qui reçoivent uniquement ce qui est nécessaire.'],
                ['table' => [
                    'head' => ['Service', 'À quoi il sert', 'Ce qu’il reçoit'],
                    'rows' => [
                        ['<strong>Hébergement et base de données</strong>', 'Faire tourner le site et stocker les données de la plateforme.', 'L’ensemble des données du service, dans un environnement à accès restreint. Le lieu d’hébergement précis est communiqué sur simple demande.'],
                        ['<strong>Envoi d’e-mails (SMTP)</strong>', 'Confirmation d’adresse, réinitialisation de mot de passe, résultats, lettre d’information.', 'Votre adresse e-mail, votre nom et le contenu du message envoyé.'],
                        ['<strong>Google Gemini (assistant IA)</strong>', 'Générer les réponses et les quiz proposés par l’assistant.', 'Votre message et le texte de la leçon concernée. Pas votre nom, ni votre e-mail, ni votre matricule.'],
                        ['<strong>YouTube</strong>', 'Lire les vidéos de cours hébergées sur YouTube.', 'Lorsqu’une leçon contient une vidéo YouTube, votre navigateur contacte YouTube (Google), qui reçoit votre adresse IP et peut déposer ses propres cookies, selon sa propre politique.'],
                        ['<strong>Google Fonts, cdnjs, jsDelivr</strong>', 'Charger les polices, le lecteur PDF, les formules mathématiques et les animations.', 'Votre navigateur télécharge ces fichiers depuis ces services, qui voient votre adresse IP comme tout site web.'],
                    ],
                ]],
                ['p' => 'Certains de ces prestataires peuvent être établis hors du pays où vous vous trouvez. Dans ce cas, le transfert se fait sous les garanties prévues par la réglementation applicable (clauses contractuelles, engagements du prestataire). Pour une question précise sur un transfert, écrivez-nous.'],
            ],
        ],
        [
            'id' => 'ai', 'h' => 'L’assistant IA',
            'blocks' => [
                ['p' => 'Dans la lecture d’une leçon, vous pouvez poser une question à un assistant, lui demander un résumé ou un quiz. Voici exactement ce qui se passe.'],
                ['steps' => [
                    'Vous écrivez un message ou choisissez une action (résumer, expliquer, générer un quiz).',
                    'La plateforme envoie à Google Gemini votre message et le texte de la leçon ouverte, pour que la réponse porte sur le bon contenu.',
                    'La réponse revient et s’affiche dans le panneau. Elle n’est pas enregistrée dans votre dossier scolaire et n’influence aucune note.',
                ]],
                ['p' => 'Ne saisissez pas d’informations personnelles ou confidentielles dans vos messages : ils sont traités par un service externe. Une réponse de l’assistant peut contenir des erreurs, vérifiez-la avec le cours. L’usage de l’assistant est facultatif, rien d’obligatoire dans votre parcours ne dépend de lui.'],
            ],
        ],
        [
            'id' => 'cookies', 'h' => 'Cookies et stockage dans votre navigateur',
            'blocks' => [
                ['p' => 'StudyVibe n’utilise aucun cookie publicitaire ni outil de mesure d’audience tiers. Seuls des éléments techniques, nécessaires au fonctionnement, sont posés.'],
                ['table' => [
                    'head' => ['Nom', 'Rôle', 'Durée'],
                    'rows' => [
                        ['<code>PHPSESSID</code> (cookie)', 'Garde votre session ouverte. Protégé : inaccessible aux scripts, envoyé uniquement sur notre site, et uniquement en HTTPS en production.', 'Jusqu’à la fermeture de la session'],
                        ['Jeton CSRF (dans la session)', 'Empêche qu’un autre site déclenche une action à votre place.', 'Durée de la session'],
                        ['Canal d’arrivée (dans la session)', 'Retient le paramètre <code>src</code> d’un lien de campagne pour savoir d’où vient une inscription. Pas de cookie séparé.', 'Durée de la session'],
                        ['<code>studyvibe_lang</code> (cookie)', 'Retient votre langue (français ou anglais).', '1 an'],
                        ['<code>sv_dark</code> (stockage local)', 'Retient votre choix de thème clair ou sombre.', 'Jusqu’à effacement par vous'],
                        ['<code>sv_privacy_accepted</code> (stockage local)', 'Retient que vous avez lu l’avis de confidentialité, pour ne pas vous le remontrer.', 'Jusqu’à effacement par vous'],
                        ['<code>sv_video_notes</code> (stockage local)', 'Vos notes personnelles de lecture. Elles restent dans votre navigateur et ne sont pas envoyées à nos serveurs.', 'Jusqu’à effacement par vous'],
                        ['Brouillons d’évaluation en direct (stockage local)', 'Garde une réponse en cours si la connexion coupe, pour la renvoyer ensuite.', 'Jusqu’à l’envoi de la réponse'],
                    ],
                ]],
                ['p' => 'Vous pouvez effacer ces éléments à tout moment dans les réglages de votre navigateur. Sans le cookie de session, vous ne pouvez plus rester connecté.'],
            ],
        ],
        [
            'id' => 'retention', 'h' => 'Combien de temps nous gardons vos données',
            'blocks' => [
                ['table' => [
                    'head' => ['Donnée', 'Durée de conservation'],
                    'rows' => [
                        ['Compte, profil, matricule', 'Tant que le compte est actif. Suppression dans les 30 jours qui suivent une demande de fermeture.'],
                        ['Progression, notes, réponses, devoirs', 'Tant que le compte est actif, car ils constituent votre dossier. Supprimés avec le compte, sauf obligation de conservation.'],
                        ['Certificats', 'Conservés durablement pour que la vérification reste possible, car un certificat qui disparaît ne prouve plus rien. Vous pouvez demander son effacement : le code ne sera alors plus vérifiable.'],
                        ['Évaluations en direct avec un invité', 'Tant que la séance et ses résultats sont conservés par l’enseignant, puis suppression avec la séance.'],
                        ['Tentatives de connexion (adresse IP, e-mail)', '90 jours, puis effacement automatique. Remises à zéro après une connexion réussie.'],
                        ['Journal d’audit', '1 an, puis effacement automatique.'],
                        ['Jetons de confirmation et de réinitialisation', 'Quelques heures à 48 heures, puis invalides.'],
                        ['Lettre d’information', 'Jusqu’à votre désabonnement.'],
                        ['Sauvegardes', 'Les sauvegardes techniques peuvent contenir des données supprimées pendant une période limitée, après laquelle elles sont écrasées. Elles ne servent qu’à restaurer le service.'],
                    ],
                ]],
            ],
        ],
        [
            'id' => 'security', 'h' => 'Comment nous protégeons vos données',
            'blocks' => [
                ['ul' => [
                    '<strong>Mots de passe :</strong> jamais stockés en clair. Seule une empreinte à sens unique est conservée, et personne dans l’équipe ne peut la lire.',
                    '<strong>Chiffrement des échanges :</strong> en production, le site est servi en HTTPS, et le cookie de session n’est envoyé qu’en HTTPS.',
                    '<strong>Protection du compte :</strong> les tentatives de connexion répétées sont limitées, avec un blocage temporaire. L’adresse e-mail doit être confirmée.',
                    '<strong>Protection des actions :</strong> jeton CSRF, cookies limités à notre site, accès contrôlé par rôle côté serveur pour chaque donnée.',
                    '<strong>Fichiers :</strong> les PDF de cours, les devoirs et les photos ne sont pas accessibles par une adresse publique. Ils passent par un contrôle d’accès : il faut être connecté, inscrit au cours et avoir atteint la leçon concernée.',
                    '<strong>Intégrité des résultats :</strong> l’ordre des leçons, les vidéos et les quiz sont contrôlés côté serveur, pas seulement dans votre navigateur.',
                    '<strong>Traçabilité :</strong> les actions sensibles sont consignées dans un journal d’audit.',
                ]],
                ['p' => 'Aucun système n’est infaillible. Si un incident touchait vos données personnelles, nous évaluerions le risque, corrigerions la faille et vous informerions, ainsi que l’autorité compétente lorsque la réglementation l’exige, dans les meilleurs délais et au plus tard dans les 72 heures suivant la découverte quand cela s’applique. Si vous pensez avoir trouvé une faille, écrivez-nous avant d’en parler publiquement : nous répondrons vite et nous vous remercierons.'],
            ],
        ],
        [
            'id' => 'rights', 'h' => 'Vos droits et comment les exercer',
            'blocks' => [
                ['p' => 'Selon la réglementation applicable, notamment le RGPD pour les personnes concernées dans l’Union européenne et les textes nationaux de protection des données qui s’appliquent à vous, vous disposez des droits suivants :'],
                ['table' => [
                    'head' => ['Droit', 'Ce que cela veut dire en pratique'],
                    'rows' => [
                        ['<strong>Accès</strong>', 'Savoir si nous traitons vos données et en obtenir une copie.'],
                        ['<strong>Rectification</strong>', 'Corriger une information inexacte. Votre nom et votre matricule se modifient directement dans <em>Profil</em>.'],
                        ['<strong>Effacement</strong>', 'Demander la suppression de votre compte et de vos données, sauf celles que la loi nous oblige à garder.'],
                        ['<strong>Portabilité</strong>', 'Recevoir vos données dans un format courant (CSV ou PDF) pour les réutiliser ailleurs.'],
                        ['<strong>Limitation</strong>', 'Demander que le traitement soit suspendu pendant la vérification d’une contestation.'],
                        ['<strong>Opposition</strong>', 'Vous opposer à un traitement fondé sur notre intérêt légitime.'],
                        ['<strong>Retrait du consentement</strong>', 'Pour la lettre d’information : lien de désabonnement dans chaque e-mail, effet immédiat. Pour l’assistant IA : cessez simplement de l’utiliser.'],
                        ['<strong>Directives après le décès</strong>', 'Indiquer ce que doivent devenir vos données, lorsque la loi le permet.'],
                    ],
                ]],
                ['steps' => [
                    'Écrivez à l’adresse de contact ci-dessous depuis l’adresse e-mail de votre compte, avec pour objet « Mes données ».',
                    'Dites ce que vous voulez : consulter, corriger, exporter, supprimer, vous opposer.',
                    'Nous vérifions que la demande vient bien de vous. Dans certains cas, nous pouvons demander une confirmation supplémentaire.',
                    'Nous répondons au plus tard sous 30 jours. Ce délai peut être prolongé si la demande est complexe, et nous vous le dirons alors.',
                ]],
                ['p' => 'L’exercice de vos droits est gratuit. Si votre demande est manifestement abusive ou répétée sans raison, nous pouvons la refuser ou demander des frais raisonnables, en expliquant pourquoi. Si vous estimez que vos droits ne sont pas respectés, vous pouvez aussi saisir l’autorité de protection des données de votre pays.'],
            ],
        ],
        [
            'id' => 'special', 'h' => 'Situations particulières',
            'blocks' => [
                ['ul' => [
                    '<strong>Fermeture du compte :</strong> à votre demande, le compte est désactivé puis effacé sous 30 jours. Vos commentaires publiés peuvent être anonymisés plutôt que supprimés pour ne pas casser les fils de discussion.',
                    '<strong>Changement d’adresse e-mail :</strong> contactez-nous, nous mettons à jour le compte après vérification.',
                    '<strong>Matricule déjà utilisé :</strong> un matricule est unique. Si le vôtre est déjà enregistré sur un autre compte, la plateforme vous demande de le corriger, sans jamais indiquer à qui il appartient.',
                    '<strong>Enseignant qui quitte la plateforme :</strong> ses cours peuvent être réattribués ou archivés pour que les étudiants gardent accès à leurs résultats et à leurs certificats.',
                    '<strong>Compte d’établissement :</strong> lorsqu’un établissement inscrit lui-même ses étudiants, il en est responsable pour ce qui concerne le choix des personnes inscrites. StudyVibe reste responsable du fonctionnement de la plateforme.',
                    '<strong>Demande d’une autorité :</strong> nous ne communiquons des données que sur demande écrite et valable d’une autorité compétente, et seulement ce qui est exigé.',
                    '<strong>Évaluation en direct sans compte :</strong> votre adresse e-mail sert à vous identifier pendant la séance et à vous envoyer vos résultats. Elle n’est pas utilisée pour vous inscrire à la lettre d’information.',
                ]],
            ],
        ],
        [
            'id' => 'minors', 'h' => 'Mineurs',
            'blocks' => [
                ['p' => 'StudyVibe s’adresse en priorité à l’enseignement supérieur. Si vous avez moins de 18 ans, ou moins de l’âge de consentement numérique de votre pays, utilisez la plateforme avec l’accord de votre établissement ou de votre représentant légal. Un parent ou tuteur peut exercer les droits décrits plus haut au nom de l’enfant. Si nous apprenons qu’un compte a été créé sans cet accord, nous pouvons le suspendre et effacer ses données.'],
            ],
        ],
        [
            'id' => 'changes', 'h' => 'Modifications de ce texte',
            'blocks' => [
                ['p' => 'Nous mettons cette politique à jour quand le service évolue, par exemple lorsqu’un nouvel outil traite des données ou qu’un prestataire change. La date et la version figurent en haut de page. Pour un changement important, nous vous prévenons par e-mail ou par un message dans la plateforme avant son entrée en vigueur, et nous redemandons votre accord lorsqu’il est requis. Les versions précédentes peuvent être fournies sur demande.'],
            ],
        ],
        [
            'id' => 'contact', 'h' => 'Nous contacter',
            'blocks' => [
                ['p' => 'Pour toute question, demande liée à vos données, signalement de sécurité ou réclamation, écrivez au Promoteur de la plateforme :'],
                ['p' => '<a class="pv-mail" href="mailto:danielwilfriedtakou@gmail.com">danielwilfriedtakou@gmail.com</a>'],
                ['p' => 'Indiquez l’adresse e-mail de votre compte et, si possible, l’objet de votre demande. Nous accusons réception rapidement et nous répondons dans le délai de 30 jours indiqué plus haut.'],
            ],
        ],
    ],
    'contact_cta' => 'Écrire à l’équipe',
    'contact_h'   => 'Une question sur vos données ?',
    'contact_p'   => 'Nous répondons en personne, en français ou en anglais.',
    'art_alt'     => 'Des personnes protégées par un grand bouclier de sécurité',
    'art2_alt'    => 'Une personne qui soulève un téléphone protégé par un cadenas',
],

'en' => [
    'title'    => 'Privacy policy',
    'desc'     => 'What data StudyVibe collects, why, who can see it, how long it is kept and how to exercise your rights.',
    'kicker'   => 'Privacy and personal data',
    'h1'       => 'Your data, <em>explained in detail.</em>',
    'lede'     => 'StudyVibe exists to help you learn, sit exams and earn certificates. To do that, the platform has to know a few things about you. This page says which ones, what we do with them, who has access, and how you stay in control.',
    'updated'  => 'Last updated: 8 October 2026',
    'version'  => 'Version 2.0',
    'back'     => 'Back to home',
    'toc'      => 'On this page',
    'top'      => 'Back to top',
    'short_h'  => 'The essentials in one minute',
    'short'    => [
        ['What we keep', 'Your name, your email, your password (in encrypted form), and for students the student number. Then what you do on the platform: lessons read, quiz answers, grades, assignments handed in.'],
        ['What we never do', 'We never sell data, show ads or use advertising trackers. Your results are not used to profile you for commercial purposes.'],
        ['Who sees what', 'Your teachers see your progress and grades in their courses. The administration team sees accounts in order to manage them. A certificate can be checked publicly with its code.'],
        ['How long', 'As long as your account exists, then a short delay after it is closed. Security logs are erased automatically after 90 days (sign-in attempts) and one year (audit).'],
        ['Your rights', 'See, correct, export or delete your data, object to a use or withdraw consent: a simple email is enough, and we answer within 30 days at most.'],
    ],
    'sections' => [
        [
            'id' => 'scope', 'h' => 'Who we are and who this text is for',
            'blocks' => [
                ['p' => '<strong>StudyVibe</strong> is an online learning platform: courses organised in chapters and lessons, quizzes, assignments, live evaluations, certificates and progress tracking. It is run by the StudyVibe team, represented by the Platform Promoter, who is the controller of your data under data protection law.'],
                ['p' => 'This policy applies to everyone who uses StudyVibe, whatever their role:'],
                ['ul' => [
                    '<strong>students</strong>, who follow courses, take quizzes and exams and receive certificates;',
                    '<strong>teachers</strong>, who create courses, run live evaluations and follow their students;',
                    '<strong>the Promoter and the administration team</strong>, who manage accounts, certificates and the platform;',
                    '<strong>guest participants</strong> in a live evaluation, who can join with just an email address and no account;',
                    '<strong>visitors</strong> of the public site and people who verify a certificate.',
                ]],
                ['p' => 'It covers the website, the emails the platform sends and the documents it generates (transcripts, reports, certificates). If your institution uses StudyVibe for its own courses, it may have additional rules of its own: this text describes what the platform itself does.'],
            ],
        ],
        [
            'id' => 'data', 'h' => 'The data we process',
            'blocks' => [
                ['p' => 'We only collect what the service needs to work. Here is the complete inventory, by category.'],
                ['table' => [
                    'head' => ['Category', 'What it contains', 'Where it comes from'],
                    'rows' => [
                        ['<strong>Account</strong>', 'Full name, email address, password (never readable: only an encrypted fingerprint is stored), role (student, teacher, promoter), language, creation date, email verification status.', 'You, at sign-up and in your profile.'],
                        ['<strong>Student identity</strong>', 'Student number (matricule), unique to each student, and a profile photo if you add one.', 'You, in your profile.'],
                        ['<strong>Learning path</strong>', 'Courses you are enrolled in, last lesson opened, lessons finished, videos finished, time spent on each lesson, badges earned.', 'Generated by your use of the platform.'],
                        ['<strong>Assessments</strong>', 'Lesson quiz answers, certification exam attempts and scores, live evaluation results.', 'You, by answering; computed by the platform.'],
                        ['<strong>Live evaluations</strong>', 'For a guest: email address, name entered, answers and score. For a signed-in student: the link to their account.', 'You, when joining the session.'],
                        ['<strong>Assignments and files</strong>', 'Files or links you hand in, your comment, your name and matricule as entered, submission date.', 'You, at submission.'],
                        ['<strong>Exchanges</strong>', 'Questions and comments posted under a lesson, teacher replies, notifications received.', 'You and the teachers.'],
                        ['<strong>AI assistant</strong>', 'The messages you write to the assistant, sent together with the text of the lesson concerned so it can answer. See the dedicated section.', 'You, by using the assistant.'],
                        ['<strong>Certificates</strong>', 'Unique code, course or module concerned, issue date, name of the student and of the teacher.', 'Generated when you pass.'],
                        ['<strong>Communications</strong>', 'Email address and newsletter subscription preference; history of transactional emails sent (confirmation, password reset, results).', 'You, when subscribing; the platform.'],
                        ['<strong>Security and logs</strong>', 'IP address and email used in sign-in attempts; audit log of important actions (sign-up, sign-in, role change, certificate issued, password change).', 'Collected automatically by the servers.'],
                        ['<strong>Device and browser</strong>', 'Session cookie, language cookie, display preferences (dark theme), local drafts. Details in the cookies section.', 'Your browser.'],
                        ['<strong>Visit statistics</strong>', 'Anonymous daily counters (page views, sign-up steps) and, at sign-up, the channel you came from (for example a campaign link or QR code). No IP address or identifier is attached.', 'Collected automatically.'],
                    ],
                ]],
                ['p' => 'We do not ask for sensitive data (origin, opinions, health, biometric data) and we ask you not to enter any in free-text areas (comments, assignments, messages to the assistant).'],
            ],
        ],
        [
            'id' => 'purposes', 'h' => 'Why we use this data',
            'blocks' => [
                ['p' => 'Every use has a specific reason and a legal basis. We do not use your data for anything other than what is written here.'],
                ['table' => [
                    'head' => ['Use', 'Data involved', 'Legal basis'],
                    'rows' => [
                        ['Create and protect your account, sign you in, reset a password', 'Account, security', 'Performing the service you asked for'],
                        ['Give you access to courses, record your progress and unlock lessons in order', 'Learning path, assessments', 'Performing the service'],
                        ['Compute your grades and pass rate and issue verifiable certificates', 'Assessments, certificates, matricule', 'Performing the service; legitimate interest in keeping a certificate meaningful'],
                        ['Let teachers follow their students and export the results of their courses', 'Learning path, assessments, matricule, name', 'Performing the service; the institution’s teaching mission'],
                        ['Run live evaluations, with or without an account', 'Live evaluations', 'Performing the service'],
                        ['Answer your questions through the AI assistant', 'AI assistant', 'Your request, with each message'],
                        ['Send service emails (confirmation, results, course alerts)', 'Account, communications', 'Performing the service'],
                        ['Send the platform newsletter', 'Communications', 'Your consent, withdrawable in one click'],
                        ['Detect abuse, block repeated sign-in attempts, ensure traceability', 'Security and logs', 'Legitimate security interest'],
                        ['Comply with a legal obligation or answer a valid request from an authority', 'Depends on the request', 'Legal obligation'],
                    ],
                ]],
                ['note' => '<strong>No automated decision with legal effect.</strong> A quiz or exam score is computed automatically, but granting or refusing an official diploma remains the institution’s responsibility and can be reviewed by a teacher or by the Promoter.'],
            ],
        ],
        [
            'id' => 'visibility', 'h' => 'Who can see your data',
            'blocks' => [
                ['p' => 'Access is limited by role. Here is what each person can see.'],
                ['table' => [
                    'head' => ['Who', 'What they can see'],
                    'rows' => [
                        ['<strong>You</strong>', 'Everything about you: profile, progress, grades, transcripts, certificates, assignments, notifications.'],
                        ['<strong>Your teachers</strong>', 'Only for courses they teach: your name, matricule, progress, quiz answers, assignments and questions. They do not see your password or your other courses.'],
                        ['<strong>Other students</strong>', 'Your name, only next to the comments you post under a lesson. Not your grades, your progress or your email.'],
                        ['<strong>The Promoter and administration</strong>', 'The list of accounts, their roles and statuses, certificates, audit logs and overall statistics, to manage the platform and help when something goes wrong.'],
                        ['<strong>Anyone with a certificate code</strong>', 'The verification page shows the student’s name, the course, the teacher and the issue date, nothing more. This is intentional: an employer must be able to confirm that a certificate is genuine.'],
                        ['<strong>Technical providers</strong>', 'Only what they need for their task. See the next section.'],
                    ],
                ]],
                ['p' => 'Staff who can access personal data are bound by confidentiality. Administration access is personal, and important actions are recorded in the audit log.'],
            ],
        ],
        [
            'id' => 'processors', 'h' => 'Providers and third-party services',
            'blocks' => [
                ['p' => 'StudyVibe does not sell or rent your data. To work, the platform still relies on a few technical services, which receive only what is necessary.'],
                ['table' => [
                    'head' => ['Service', 'What it is for', 'What it receives'],
                    'rows' => [
                        ['<strong>Hosting and database</strong>', 'Run the site and store the platform’s data.', 'All the service’s data, in a restricted-access environment. The exact hosting location is provided on request.'],
                        ['<strong>Email delivery (SMTP)</strong>', 'Address confirmation, password reset, results, newsletter.', 'Your email address, your name and the content of the message sent.'],
                        ['<strong>Google Gemini (AI assistant)</strong>', 'Generate the answers and quizzes the assistant offers.', 'Your message and the text of the lesson concerned. Not your name, email or matricule.'],
                        ['<strong>YouTube</strong>', 'Play course videos hosted on YouTube.', 'When a lesson contains a YouTube video, your browser contacts YouTube (Google), which receives your IP address and may set its own cookies, under its own policy.'],
                        ['<strong>Google Fonts, cdnjs, jsDelivr</strong>', 'Load fonts, the PDF reader, math formulas and animations.', 'Your browser downloads these files from those services, which see your IP address like any website would.'],
                    ],
                ]],
                ['p' => 'Some of these providers may be established outside the country where you are. In that case the transfer takes place under the safeguards provided by the applicable regulation (contractual clauses, provider commitments). For a specific question about a transfer, write to us.'],
            ],
        ],
        [
            'id' => 'ai', 'h' => 'The AI assistant',
            'blocks' => [
                ['p' => 'While reading a lesson, you can ask an assistant a question, request a summary or a quiz. Here is exactly what happens.'],
                ['steps' => [
                    'You write a message or choose an action (summarise, explain, generate a quiz).',
                    'The platform sends Google Gemini your message and the text of the open lesson, so the answer is about the right content.',
                    'The answer comes back and is shown in the panel. It is not saved in your academic record and affects no grade.',
                ]],
                ['p' => 'Do not enter personal or confidential information in your messages: they are processed by an external service. An assistant answer can contain mistakes, so check it against the course. Using the assistant is optional, nothing required in your path depends on it.'],
            ],
        ],
        [
            'id' => 'cookies', 'h' => 'Cookies and storage in your browser',
            'blocks' => [
                ['p' => 'StudyVibe uses no advertising cookies and no third-party audience measurement. Only technical items needed for the service to work are set.'],
                ['table' => [
                    'head' => ['Name', 'Role', 'Duration'],
                    'rows' => [
                        ['<code>PHPSESSID</code> (cookie)', 'Keeps your session open. Protected: unreadable by scripts, sent only to our site, and only over HTTPS in production.', 'Until the session ends'],
                        ['CSRF token (in the session)', 'Prevents another site from triggering an action on your behalf.', 'Session length'],
                        ['Arrival channel (in the session)', 'Remembers the <code>src</code> parameter of a campaign link to know where a sign-up came from. No separate cookie.', 'Session length'],
                        ['<code>studyvibe_lang</code> (cookie)', 'Remembers your language (French or English).', '1 year'],
                        ['<code>sv_dark</code> (local storage)', 'Remembers your light or dark theme choice.', 'Until you clear it'],
                        ['<code>sv_privacy_accepted</code> (local storage)', 'Remembers that you read the privacy notice, so it is not shown again.', 'Until you clear it'],
                        ['<code>sv_video_notes</code> (local storage)', 'Your personal reading notes. They stay in your browser and are not sent to our servers.', 'Until you clear it'],
                        ['Live evaluation drafts (local storage)', 'Keeps an answer in progress if the connection drops, to send it afterwards.', 'Until the answer is sent'],
                    ],
                ]],
                ['p' => 'You can clear these items at any time in your browser settings. Without the session cookie you cannot stay signed in.'],
            ],
        ],
        [
            'id' => 'retention', 'h' => 'How long we keep your data',
            'blocks' => [
                ['table' => [
                    'head' => ['Data', 'Retention period'],
                    'rows' => [
                        ['Account, profile, matricule', 'As long as the account is active. Deleted within 30 days of a closure request.'],
                        ['Progress, grades, answers, assignments', 'As long as the account is active, since they make up your record. Deleted with the account, unless a retention duty applies.'],
                        ['Certificates', 'Kept long term so verification stays possible, because a certificate that disappears proves nothing. You can ask for it to be erased: the code will then no longer be verifiable.'],
                        ['Live evaluations with a guest', 'As long as the session and its results are kept by the teacher, then deleted with the session.'],
                        ['Sign-in attempts (IP address, email)', '90 days, then automatic erasure. Reset after a successful sign-in.'],
                        ['Audit log', '1 year, then automatic erasure.'],
                        ['Confirmation and reset tokens', 'A few hours up to 48 hours, then invalid.'],
                        ['Newsletter', 'Until you unsubscribe.'],
                        ['Backups', 'Technical backups may contain deleted data for a limited period, after which they are overwritten. They are used only to restore the service.'],
                    ],
                ]],
            ],
        ],
        [
            'id' => 'security', 'h' => 'How we protect your data',
            'blocks' => [
                ['ul' => [
                    '<strong>Passwords:</strong> never stored in clear. Only a one-way fingerprint is kept, and nobody on the team can read it.',
                    '<strong>Encrypted traffic:</strong> in production the site is served over HTTPS, and the session cookie is only sent over HTTPS.',
                    '<strong>Account protection:</strong> repeated sign-in attempts are limited, with a temporary lock. The email address must be confirmed.',
                    '<strong>Action protection:</strong> CSRF token, cookies limited to our site, role-based access checked on the server for every piece of data.',
                    '<strong>Files:</strong> course PDFs, assignments and photos have no public address. They go through an access check: you must be signed in, enrolled in the course and have reached the lesson concerned.',
                    '<strong>Result integrity:</strong> lesson order, videos and quizzes are checked on the server, not only in your browser.',
                    '<strong>Traceability:</strong> sensitive actions are recorded in an audit log.',
                ]],
                ['p' => 'No system is infallible. If an incident affected your personal data, we would assess the risk, fix the flaw and inform you, as well as the competent authority when regulation requires it, as soon as possible and within 72 hours of discovery where that applies. If you think you found a vulnerability, write to us before discussing it publicly: we will answer quickly and thank you.'],
            ],
        ],
        [
            'id' => 'rights', 'h' => 'Your rights and how to exercise them',
            'blocks' => [
                ['p' => 'Under the applicable regulation, notably the GDPR for people in the European Union and the national data protection laws that apply to you, you have the following rights:'],
                ['table' => [
                    'head' => ['Right', 'What it means in practice'],
                    'rows' => [
                        ['<strong>Access</strong>', 'Know whether we process your data and get a copy.'],
                        ['<strong>Rectification</strong>', 'Correct inaccurate information. Your name and matricule can be edited directly in <em>Profile</em>.'],
                        ['<strong>Erasure</strong>', 'Ask for your account and data to be deleted, except what the law requires us to keep.'],
                        ['<strong>Portability</strong>', 'Receive your data in a common format (CSV or PDF) to reuse elsewhere.'],
                        ['<strong>Restriction</strong>', 'Ask for processing to be paused while a dispute is checked.'],
                        ['<strong>Objection</strong>', 'Object to processing based on our legitimate interest.'],
                        ['<strong>Withdrawing consent</strong>', 'For the newsletter: unsubscribe link in every email, immediate effect. For the AI assistant: simply stop using it.'],
                        ['<strong>Post-mortem directives</strong>', 'State what should happen to your data, where the law allows it.'],
                    ],
                ]],
                ['steps' => [
                    'Write to the contact address below from the email address of your account, with the subject “My data”.',
                    'Say what you want: see, correct, export, delete, object.',
                    'We check that the request really comes from you. In some cases we may ask for extra confirmation.',
                    'We answer within 30 days at most. This can be extended if the request is complex, and we will tell you if so.',
                ]],
                ['p' => 'Exercising your rights is free. If a request is clearly abusive or repeated without reason, we may refuse it or ask for reasonable fees, explaining why. If you believe your rights are not respected, you can also lodge a complaint with the data protection authority of your country.'],
            ],
        ],
        [
            'id' => 'special', 'h' => 'Special situations',
            'blocks' => [
                ['ul' => [
                    '<strong>Closing the account:</strong> on your request, the account is deactivated then erased within 30 days. Your published comments may be anonymised rather than deleted so discussion threads do not break.',
                    '<strong>Changing your email address:</strong> contact us, we update the account after verification.',
                    '<strong>Matricule already in use:</strong> a matricule is unique. If yours is already registered on another account, the platform asks you to correct it, without ever saying who holds it.',
                    '<strong>A teacher leaving the platform:</strong> their courses can be reassigned or archived so students keep access to their results and certificates.',
                    '<strong>Institution accounts:</strong> when an institution enrols its own students, it is responsible for the choice of who is enrolled. StudyVibe remains responsible for how the platform works.',
                    '<strong>Request from an authority:</strong> we only disclose data on a written and valid request from a competent authority, and only what is required.',
                    '<strong>Live evaluation without an account:</strong> your email address is used to identify you during the session and to send you your results. It is not used to subscribe you to the newsletter.',
                ]],
            ],
        ],
        [
            'id' => 'minors', 'h' => 'Minors',
            'blocks' => [
                ['p' => 'StudyVibe is aimed mainly at higher education. If you are under 18, or under the age of digital consent in your country, use the platform with the agreement of your institution or legal guardian. A parent or guardian can exercise the rights described above on the child’s behalf. If we learn that an account was created without that agreement, we may suspend it and erase its data.'],
            ],
        ],
        [
            'id' => 'changes', 'h' => 'Changes to this text',
            'blocks' => [
                ['p' => 'We update this policy when the service changes, for example when a new tool processes data or a provider changes. The date and version are shown at the top of the page. For an important change, we notify you by email or by a message in the platform before it takes effect, and we ask for your agreement again where it is required. Earlier versions can be provided on request.'],
            ],
        ],
        [
            'id' => 'contact', 'h' => 'Contact us',
            'blocks' => [
                ['p' => 'For any question, data request, security report or complaint, write to the Platform Promoter:'],
                ['p' => '<a class="pv-mail" href="mailto:danielwilfriedtakou@gmail.com">danielwilfriedtakou@gmail.com</a>'],
                ['p' => 'Please give the email address of your account and, if possible, the subject of your request. We acknowledge receipt quickly and answer within the 30-day period stated above.'],
            ],
        ],
    ],
    'contact_cta' => 'Write to the team',
    'contact_h'   => 'A question about your data?',
    'contact_p'   => 'A real person answers, in French or in English.',
    'art_alt'     => 'People protected by a large security shield',
    'art2_alt'    => 'A person lifting a phone protected by a padlock',
],

];
