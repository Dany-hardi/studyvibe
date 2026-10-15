<?php
/**
 * Terms of use, FR and EN, rendered by terms.php with the design of the privacy page.
 * Same block types as locales/privacy.php: p, ul, steps, note, table. Keep both languages in the same order.
 * This is the platform's operating text; the operator should have it reviewed by a lawyer for their country (governing law, courts, limits of liability).
 */
return [
'fr' => [
    'title' => 'Conditions d’utilisation', 'desc' => 'Les règles d’usage de StudyVibe : comptes, évaluations, certificats, contenus, candidatures et responsabilités.',
    'kicker' => 'Conditions d’utilisation', 'h1' => 'Les règles du jeu, <em>écrites simplement.</em>',
    'lede' => 'En utilisant StudyVibe, vous acceptez ces conditions. Elles décrivent ce que la plateforme vous offre, ce que nous attendons de vous et ce qui se passe quand quelque chose ne va pas. Lisez-les une fois : elles sont courtes et claires.',
    'updated' => 'Dernière mise à jour : 13 octobre 2026', 'version' => 'Version 1.0', 'back' => 'Retour à l’accueil', 'toc' => 'Dans cette page', 'top' => 'Haut de page',
    'short_h' => 'L’essentiel en une minute',
    'short' => [
        ['Un compte, une personne', 'Votre compte est personnel. Gardez votre mot de passe pour vous et donnez des informations exactes.'],
        ['Jouer franc jeu', 'Pas de triche, pas de partage des questions d’examen, pas d’usurpation. Un résultat peut être annulé en cas de fraude.'],
        ['Vos contenus restent à vous', 'Les cours appartiennent à leurs auteurs. Vous nous autorisez seulement à les afficher pour faire fonctionner le service.'],
        ['Des certificats vérifiables', 'Un certificat ou un relevé se contrôle publiquement avec son code. Un document faux ou modifié est signalé.'],
        ['Des données protégées', 'Nous traitons vos données comme décrit dans la politique de confidentialité, et vous gardez vos droits.'],
    ],
    'sections' => [
        ['id' => 'acceptance', 'h' => 'Acceptation et champ d’application', 'blocks' => [
            ['p' => 'Ces conditions régissent l’accès et l’usage de StudyVibe : le site, les espaces étudiant, enseignant et administrateur, les évaluations, les certificats, la page Carrières et les services associés (e-mails, notifications, application installable).'],
            ['p' => 'Vous les acceptez en créant un compte, en vous connectant, en rejoignant une évaluation, en envoyant une candidature ou en continuant à utiliser le service. Si vous n’êtes pas d’accord, n’utilisez pas StudyVibe. Votre établissement peut ajouter ses propres règles : en cas de contradiction, les plus protectrices pour l’étudiant s’appliquent.'],
        ]],
        ['id' => 'service', 'h' => 'Le service', 'blocks' => [
            ['p' => 'StudyVibe est une plateforme d’apprentissage : cours organisés en chapitres et leçons, devoirs, évaluations (en direct ou à rythme libre), correction automatique, relevés et certificats vérifiables, assistant d’étude, messagerie et notifications.'],
            ['p' => 'Nous améliorons le service en continu. Certaines fonctions peuvent évoluer, être suspendues pour maintenance ou retirées. Nous prévenons à l’avance quand c’est possible.'],
        ]],
        ['id' => 'accounts', 'h' => 'Comptes et accès', 'blocks' => [
            ['ul' => [
                'Vous devez fournir des informations exactes (nom, adresse e-mail, téléphone, matricule) et les tenir à jour. Votre nom apparaît sur vos relevés et certificats.',
                'Un compte est personnel et ne se prête pas. Vous êtes responsable de ce qui se fait avec votre mot de passe : choisissez-en un solide et activez la double authentification quand elle est proposée.',
                'Un compte enseignant est validé par un administrateur. Un administrateur peut suspendre un compte qui ne respecte pas ces conditions.',
                'Si vous avez moins de 18 ans, ou moins que l’âge du consentement numérique de votre pays, utilisez la plateforme avec l’accord de votre établissement ou de votre représentant légal.',
                'Prévenez-nous sans attendre si vous pensez que votre compte a été utilisé par quelqu’un d’autre.',
            ]],
        ]],
        ['id' => 'use', 'h' => 'Usage acceptable', 'blocks' => [
            ['p' => 'Vous vous engagez à ne pas :'],
            ['ul' => [
                'tricher à une évaluation, aider quelqu’un à tricher, partager les questions ou les réponses, ou passer une évaluation à la place d’un autre ;',
                'usurper l’identité d’une personne, créer de faux comptes ou fournir de fausses informations dans une candidature ;',
                'contourner les limites techniques, tenter d’accéder à des données qui ne sont pas les vôtres, tester la sécurité sans autorisation écrite ou perturber le service ;',
                'envoyer des programmes malveillants, du spam, des contenus illicites, injurieux, discriminatoires ou portant atteinte aux droits d’autrui ;',
                'collecter automatiquement des données du site (extraction massive) ou revendre l’accès à la plateforme ;',
                'copier, diffuser ou publier des contenus de cours en dehors de ce que leur auteur autorise.',
            ]],
            ['note' => 'Pour signaler une faille de sécurité, écrivez-nous : nous la corrigeons et nous vous remercions. Ne divulguez rien avant la correction.'],
        ]],
        ['id' => 'content', 'h' => 'Contenus et propriété intellectuelle', 'blocks' => [
            ['p' => 'Les cours, supports, questions et corrigés appartiennent à leurs auteurs (enseignants, établissements) ou à leurs ayants droit. L’accès à un cours vous donne un droit d’usage personnel, non exclusif et non transférable, pour apprendre.'],
            ['p' => 'La marque StudyVibe, le logiciel, le design et les textes de la plateforme sont protégés. Aucun droit ne vous est cédé en dehors de l’usage normal du service.'],
            ['p' => 'Ce que vous déposez (devoirs, notes, messages, photos de profil) reste à vous. Vous nous accordez une licence limitée, gratuite, pour le stocker, l’afficher aux personnes concernées (par exemple l’enseignant du cours) et l’utiliser uniquement pour faire fonctionner le service. Vous garantissez avoir le droit de déposer ces contenus.'],
            ['p' => 'Un enseignant qui dépose un cours garantit qu’il a le droit de le diffuser et qu’il respecte le droit d’auteur.'],
        ]],
        ['id' => 'exams', 'h' => 'Évaluations et intégrité académique', 'blocks' => [
            ['ul' => [
                'Les évaluations suivent les règles fixées par l’enseignant : horaire, durée par question, ordre des propositions, suivi des sorties d’onglet, une seule tentative, etc. La note est calculée par le serveur à partir de l’horloge du serveur.',
                'Une panne ou une coupure de connexion de votre côté ne garantit pas une nouvelle tentative : c’est à l’enseignant de décider.',
                'Un résultat peut être annulé en cas de fraude ou de problème technique. Vous pouvez le contester une fois, par la plateforme ; l’enseignant répond et sa réponse est définitive dans le cadre de la plateforme.',
                'Les enseignants peuvent demander à voir comment une évaluation s’est déroulée (connexions, sorties d’onglet) pour trancher un litige.',
            ]],
        ]],
        ['id' => 'certificates', 'h' => 'Certificats et relevés', 'blocks' => [
            ['p' => 'Un certificat est délivré à la réussite de l’évaluation finale d’un cours, avec le seuil prévu, ou exceptionnellement par un administrateur. Il porte un code et un QR code qui permettent à quiconque de vérifier publiquement son authenticité sur la page de vérification.'],
            ['p' => 'Un certificat obtenu par fraude, ou un document modifié, peut être révoqué. La page de vérification n’affiche que l’essentiel : le titulaire, le cours ou le résumé, la date et l’état du document.'],
            ['p' => 'StudyVibe fournit l’outil ; la valeur académique ou officielle d’un certificat dépend de l’établissement qui le délivre.'],
        ]],
        ['id' => 'careers', 'h' => 'Candidatures et page Carrières', 'blocks' => [
            ['ul' => [
                'Les informations d’une candidature doivent être exactes et vous appartenir. Un CV ou un document falsifié met fin à la candidature.',
                'Envoyer une candidature ne garantit ni un entretien ni un recrutement. Les offres peuvent être modifiées, suspendues ou clôturées à tout moment.',
                'Une première lecture automatique compare votre dossier aux critères du poste. Elle ne décide jamais seule : une personne examine chaque candidature qu’elle met de côté.',
                'Votre lien de suivi est personnel. Vous pouvez retirer votre candidature ou effacer vos données à tout moment depuis cette page.',
                'Le traitement de vos données de candidature est décrit dans la politique de confidentialité, section « Candidatures et recrutement ».',
            ]],
        ]],
        ['id' => 'privacy', 'h' => 'Données personnelles', 'blocks' => [
            ['p' => 'Nous traitons vos données comme l’explique la <a href="/privacy.php">politique de confidentialité</a> : ce que nous gardons, pourquoi, qui peut le voir, combien de temps, et comment exercer vos droits. Ce texte et cette politique forment un ensemble.'],
            ['p' => 'Les évaluations, cours, comptes et chiffres de la plateforme sont archivés automatiquement et protégés contre la suppression, afin de garantir l’authenticité des résultats et de permettre de prouver l’activité (par exemple le nombre de personnes évaluées). Vous pouvez demander l’effacement de vos données personnelles : votre identité est alors retirée des archives et seuls des chiffres anonymes subsistent.'],
        ]],
        ['id' => 'availability', 'h' => 'Disponibilité et modifications du service', 'blocks' => [
            ['p' => 'Nous faisons le nécessaire pour que le service soit disponible, mais nous ne pouvons pas garantir qu’il soit ininterrompu ou sans erreur : maintenance, incident d’un prestataire, coupure de réseau. Pour une évaluation importante, connectez-vous quelques minutes à l’avance et gardez l’onglet ouvert.'],
            ['p' => 'Nous pouvons faire évoluer, suspendre ou arrêter une fonction. Si une évolution change sensiblement vos droits ou vos obligations, nous vous prévenons à l’avance.'],
        ]],
        ['id' => 'liability', 'h' => 'Responsabilité', 'blocks' => [
            ['p' => 'La plateforme est fournie « en l’état ». Dans la mesure permise par la loi, StudyVibe n’est pas responsable des dommages indirects (perte de chance, perte de données non sauvegardées par vous, préjudice d’image) ni des contenus déposés par les utilisateurs, et sa responsabilité totale envers un utilisateur est limitée au montant éventuellement payé pour le service sur les douze derniers mois.'],
            ['p' => 'Rien dans ces conditions n’exclut ou ne limite une responsabilité qui ne peut pas l’être en droit : faute lourde ou dolosive, dommage corporel, ou droits impératifs du consommateur.'],
            ['p' => 'Vous êtes responsable de votre usage du service et des conséquences d’un manquement à ces conditions.'],
        ]],
        ['id' => 'termination', 'h' => 'Suspension et fin d’accès', 'blocks' => [
            ['p' => 'Vous pouvez cesser d’utiliser StudyVibe et demander la fermeture de votre compte à tout moment. Nous pouvons suspendre ou fermer un compte qui enfreint ces conditions, met en danger la sécurité du service ou a servi à une fraude, avec ou sans préavis selon la gravité, et en vous disant pourquoi chaque fois que c’est possible.'],
            ['p' => 'À la fermeture, vos données sont traitées comme l’indique la politique de confidentialité. Les clauses qui, par nature, doivent continuer à s’appliquer (propriété intellectuelle, responsabilité, litiges) survivent.'],
        ]],
        ['id' => 'changes', 'h' => 'Modification des conditions', 'blocks' => [
            ['p' => 'Nous pouvons mettre ces conditions à jour. La date et la version figurent en haut de page. Pour un changement important, nous vous prévenons par e-mail ou dans la plateforme avant son entrée en vigueur. Continuer à utiliser le service après cette date vaut acceptation ; sinon, vous pouvez fermer votre compte.'],
        ]],
        ['id' => 'law', 'h' => 'Droit applicable et litiges', 'blocks' => [
            ['p' => 'Ces conditions sont régies par le droit du pays où l’opérateur de la plateforme est établi, sous réserve des droits impératifs dont vous bénéficiez là où vous vivez. Avant toute action, écrivez-nous : nous cherchons d’abord une solution à l’amiable. À défaut, les tribunaux compétents sont ceux du lieu d’établissement de l’opérateur, sauf règle contraire protégeant le consommateur.'],
        ]],
        ['id' => 'contact', 'h' => 'Nous contacter', 'blocks' => [
            ['p' => 'Pour toute question sur ces conditions, un signalement ou une réclamation :'],
            ['p' => '<a class="pv-mail" href="mailto:{{mail}}">Nous contacter</a>'],
            ['p' => 'Indiquez votre rôle, l’adresse e-mail de votre compte et l’objet de votre message. Nous répondons en français ou en anglais.'],
        ]],
    ],
    'contact_cta' => 'Écrire à l’équipe', 'contact_h' => 'Une question sur ces règles ?', 'contact_p' => 'Nous répondons en personne, en français ou en anglais.',
    'art_alt' => 'Des personnes autour d’un grand document tamponné', 'art2_alt' => 'Une personne qui écrit à l’équipe',
],

'en' => [
    'title' => 'Terms of use', 'desc' => 'The rules for using StudyVibe: accounts, evaluations, certificates, content, applications and responsibilities.',
    'kicker' => 'Terms of use', 'h1' => 'The ground rules, <em>written simply.</em>',
    'lede' => 'By using StudyVibe you accept these terms. They describe what the platform offers you, what we expect from you and what happens when something goes wrong. Read them once: they are short and clear.',
    'updated' => 'Last updated: 13 October 2026', 'version' => 'Version 1.0', 'back' => 'Back to home', 'toc' => 'On this page', 'top' => 'Back to top',
    'short_h' => 'The essentials in a minute',
    'short' => [
        ['One account, one person', 'Your account is personal. Keep your password to yourself and give accurate information.'],
        ['Play fair', 'No cheating, no sharing exam questions, no impersonation. A result can be cancelled in case of fraud.'],
        ['Your content stays yours', 'Courses belong to their authors. You only allow us to display them to run the service.'],
        ['Verifiable certificates', 'A certificate or transcript is publicly checked with its code. A fake or altered document is flagged.'],
        ['Protected data', 'We handle your data as described in the privacy policy, and you keep your rights.'],
    ],
    'sections' => [
        ['id' => 'acceptance', 'h' => 'Acceptance and scope', 'blocks' => [
            ['p' => 'These terms govern access to and use of StudyVibe: the website, the student, teacher and administrator spaces, evaluations, certificates, the Careers page and related services (e-mails, notifications, the installable app).'],
            ['p' => 'You accept them by creating an account, signing in, joining an evaluation, sending an application or simply continuing to use the service. If you disagree, do not use StudyVibe. Your institution may add its own rules: if they conflict, the one more protective of the student applies.'],
        ]],
        ['id' => 'service', 'h' => 'The service', 'blocks' => [
            ['p' => 'StudyVibe is a learning platform: courses organised in chapters and lessons, assignments, evaluations (live or self-paced), automatic marking, transcripts and verifiable certificates, a study assistant, messaging and notifications.'],
            ['p' => 'We improve the service continuously. Some features may change, be paused for maintenance or be withdrawn. We give notice in advance when we can.'],
        ]],
        ['id' => 'accounts', 'h' => 'Accounts and access', 'blocks' => [
            ['ul' => [
                'You must give accurate information (name, e-mail address, phone, student number) and keep it up to date. Your name appears on your transcripts and certificates.',
                'An account is personal and is not lent. You are responsible for what is done with your password: choose a strong one and turn on two-factor authentication when offered.',
                'A teacher account is approved by an administrator. An administrator may suspend an account that does not respect these terms.',
                'If you are under 18, or under the age of digital consent in your country, use the platform with the agreement of your institution or legal guardian.',
                'Tell us without delay if you think someone else used your account.',
            ]],
        ]],
        ['id' => 'use', 'h' => 'Acceptable use', 'blocks' => [
            ['p' => 'You agree not to:'],
            ['ul' => [
                'cheat in an evaluation, help someone cheat, share questions or answers, or sit an evaluation in someone else’s place;',
                'impersonate anyone, create fake accounts or give false information in an application;',
                'bypass technical limits, try to reach data that is not yours, test security without written permission or disrupt the service;',
                'send malware, spam, or unlawful, abusive or discriminatory content, or content that infringes others’ rights;',
                'harvest data from the site automatically (mass scraping) or resell access to the platform;',
                'copy, distribute or publish course content beyond what its author allows.',
            ]],
            ['note' => 'To report a security flaw, write to us: we fix it and thank you. Please disclose nothing before it is fixed.'],
        ]],
        ['id' => 'content', 'h' => 'Content and intellectual property', 'blocks' => [
            ['p' => 'Courses, materials, questions and answer keys belong to their authors (teachers, institutions) or their rights holders. Access to a course gives you a personal, non-exclusive, non-transferable right to use it to learn.'],
            ['p' => 'The StudyVibe name, the software, the design and the text of the platform are protected. No right is transferred to you beyond normal use of the service.'],
            ['p' => 'What you upload (assignments, notes, messages, profile photos) stays yours. You grant us a limited, free licence to store it, show it to the people concerned (for example the course teacher) and use it only to run the service. You confirm you have the right to upload it.'],
            ['p' => 'A teacher who uploads a course confirms they have the right to share it and that they respect copyright.'],
        ]],
        ['id' => 'exams', 'h' => 'Evaluations and academic integrity', 'blocks' => [
            ['ul' => [
                'Evaluations follow the rules set by the teacher: schedule, time per question, option order, tab-switch tracking, a single attempt, and so on. The mark is computed by the server, on the server’s clock.',
                'A failure or a dropped connection on your side does not guarantee a new attempt: the teacher decides.',
                'A result may be cancelled in case of fraud or a technical problem. You can contest it once, through the platform; the teacher answers and the answer is final within the platform.',
                'Teachers may look at how an evaluation went (connections, tab switches) to settle a dispute.',
            ]],
        ]],
        ['id' => 'certificates', 'h' => 'Certificates and transcripts', 'blocks' => [
            ['p' => 'A certificate is awarded on passing a course’s final assessment at the set threshold, or exceptionally by an administrator. It carries a code and a QR code so anyone can publicly check its authenticity on the verification page.'],
            ['p' => 'A certificate obtained by fraud, or an altered document, may be revoked. The verification page only shows the essentials: the holder, the course or summary, the date and the state of the document.'],
            ['p' => 'StudyVibe provides the tool; the academic or official value of a certificate depends on the institution that awards it.'],
        ]],
        ['id' => 'careers', 'h' => 'Applications and the Careers page', 'blocks' => [
            ['ul' => [
                'The information in an application must be accurate and yours. A falsified résumé or document ends the application.',
                'Sending an application guarantees neither an interview nor a job. Positions may be changed, paused or closed at any time.',
                'A first automatic read compares your file with the position’s criteria. It never decides alone: a person reviews every application it sets aside.',
                'Your tracking link is personal. You can withdraw your application or erase your data at any time from that page.',
                'How your application data is processed is described in the privacy policy, section “Job applications”.',
            ]],
        ]],
        ['id' => 'privacy', 'h' => 'Personal data', 'blocks' => [
            ['p' => 'We process your data as explained in the <a href="/privacy.php">privacy policy</a>: what we keep, why, who can see it, how long, and how to exercise your rights. This text and that policy go together.'],
            ['p' => 'Evaluations, courses, accounts and platform figures are archived automatically and protected against deletion, to guarantee the authenticity of results and make it possible to prove activity (for example the number of people assessed). You can ask for your personal data to be erased: your identity is then removed from the archives and only anonymous figures remain.'],
        ]],
        ['id' => 'availability', 'h' => 'Availability and changes to the service', 'blocks' => [
            ['p' => 'We do what is needed to keep the service available, but cannot guarantee it is uninterrupted or error-free: maintenance, a provider incident, a network outage. For an important evaluation, sign in a few minutes early and keep the tab open.'],
            ['p' => 'We may change, pause or stop a feature. If a change significantly affects your rights or obligations, we tell you in advance.'],
        ]],
        ['id' => 'liability', 'h' => 'Liability', 'blocks' => [
            ['p' => 'The platform is provided “as is”. To the extent permitted by law, StudyVibe is not liable for indirect damages (loss of opportunity, loss of data you did not back up, reputational harm) or for content uploaded by users, and its total liability to a user is limited to any amount paid for the service in the previous twelve months.'],
            ['p' => 'Nothing in these terms excludes or limits a liability that cannot be excluded or limited by law: gross negligence or wilful misconduct, personal injury, or mandatory consumer rights.'],
            ['p' => 'You are responsible for your use of the service and for the consequences of breaching these terms.'],
        ]],
        ['id' => 'termination', 'h' => 'Suspension and end of access', 'blocks' => [
            ['p' => 'You may stop using StudyVibe and ask for your account to be closed at any time. We may suspend or close an account that breaks these terms, endangers the security of the service or was used for fraud, with or without notice depending on severity, and saying why whenever possible.'],
            ['p' => 'On closure, your data is handled as the privacy policy says. Clauses that by nature must continue to apply (intellectual property, liability, disputes) survive.'],
        ]],
        ['id' => 'changes', 'h' => 'Changes to these terms', 'blocks' => [
            ['p' => 'We may update these terms. The date and version are at the top of the page. For an important change, we tell you by e-mail or in the platform before it takes effect. Continuing to use the service after that date means you accept it; otherwise you can close your account.'],
        ]],
        ['id' => 'law', 'h' => 'Governing law and disputes', 'blocks' => [
            ['p' => 'These terms are governed by the law of the country where the platform’s operator is established, without affecting the mandatory rights you enjoy where you live. Before any action, write to us: we look for an amicable solution first. Failing that, the competent courts are those where the operator is established, unless a rule protecting consumers says otherwise.'],
        ]],
        ['id' => 'contact', 'h' => 'Contact us', 'blocks' => [
            ['p' => 'For any question about these terms, a report or a complaint:'],
            ['p' => '<a class="pv-mail" href="mailto:{{mail}}">Contact us</a>'],
            ['p' => 'Tell us your role, your account e-mail address and what your message is about. We answer in French or English.'],
        ]],
    ],
    'contact_cta' => 'Write to the team', 'contact_h' => 'A question about these rules?', 'contact_p' => 'We answer in person, in French or English.',
    'art_alt' => 'People around a large stamped document', 'art2_alt' => 'A person writing to the team',
],
];
