# -*- coding: utf-8 -*-
"""27 structured (written-answer) questions, marked by the AI against the reference answer and the essential points."""
S = []
def op(lang, text, ref, notes, expl, img="", t=420):
    S.append(dict(lang=lang, kind="open", text=text.strip("\n"), ref=ref.strip("\n"), notes=notes.strip("\n"), expl=expl.strip("\n"), img=img, t=t))

# 1 -------------------------------------------------------------------------------------------------------------------
op("fr",
"""Une équipe développe une application de gestion des bourses pour une université, selon un cycle en V. Quatre activités sont menées :
(a) relecture croisée du cahier des charges par deux analystes de l'équipe ;
(b) exécution de la recette par des gestionnaires de bourses, sur un jeu de dossiers réels anonymisés ;
(c) inspection du code d'un module avec une liste de contrôle de règles de codage ;
(d) exécution automatique d'un test d'intégration entre le module de calcul et la base de données.

Pour chacune des quatre activités : indiquez s'il s'agit de vérification ou de validation, situez-la dans le cycle en V et nommez le document de référence par rapport auquel on contrôle. Concluez en expliquant pourquoi une équipe qui ne ferait que de la vérification ne pourrait pas affirmer que le produit est le bon.""",
"""(a) Vérification (activité statique de revue) au niveau de la spécification des exigences : on contrôle la cohérence, la complétude et la non-ambiguïté du cahier des charges (par rapport aux besoins exprimés / aux normes de rédaction).
(b) Validation : recette utilisateur au sommet du V, par rapport à l'expression des besoins des utilisateurs (cahier des charges) : on vérifie que le produit répond au besoin réel.
(c) Vérification statique (inspection) au niveau du codage / de la conception détaillée, par rapport aux standards de codage et à la conception détaillée.
(d) Vérification dynamique : test d'intégration, par rapport à la conception architecturale (spécification de conception).
Conclusion : vérifier = « construit-on le produit correctement ? » (conformité aux spécifications) ; valider = « construit-on le bon produit ? » (adéquation au besoin). Si la spécification est erronée, incomplète ou ne reflète pas le besoin, un produit parfaitement conforme à ses spécifications reste inadapté : seule la validation avec les utilisateurs (recette) le révèle.""",
"""ESSENTIEL : (a) vérification, niveau exigences ; (b) validation, recette, par rapport aux besoins utilisateurs ; (c) vérification statique, niveau codage/conception détaillée ; (d) vérification dynamique, intégration, par rapport à la conception architecturale.
ESSENTIEL : définition correcte de vérification (produit construit correctement / conformité aux spécifications) et de validation (bon produit / adéquation au besoin).
ESSENTIEL : la conclusion doit dire que des spécifications fausses ou incomplètes passent la vérification mais pas la validation.
Accepter (a) comme « validation des exigences » seulement si l'étudiant précise que cette relecture est faite par l'équipe et non par les utilisateurs ; dans ce cas le raisonnement doit rester cohérent.""",
"Vérification : conformité aux spécifications (statique ou dynamique). Validation : adéquation au besoin réel, avec les utilisateurs. (a) vérification des exigences ; (b) validation (recette) ; (c) vérification statique du code ; (d) vérification dynamique de l'intégration.",
t=420)

# 2 -------------------------------------------------------------------------------------------------------------------
op("en",
"""A tax authority must deliver an electronic filing platform by a date fixed by a new law, 14 months from now. The filing and calculation rules are stable and published. The integration with the payment APIs of three commercial banks is poorly understood, and each bank may change its interface during the project. Public procurement rules require fixed milestones and documented deliverables, but the minister wants a usable subset in production after 6 months.

Compare a pure V-model with Scrum for this project, then defend a justified hybrid. Your answer must address risk, change, feedback, documentation and the fixed deadline, and must say which parts of the system follow which approach.""",
"""A pure V-model fits the stable, well-specified part (filing and calculation rules): fixed scope, early detailed specification, traceability, formal milestones and documents required by procurement. Its weaknesses: late feedback, high cost of change, nothing usable in production before the end, which contradicts the 6-month objective and the uncertainty on the bank APIs.
Scrum (or another iterative/incremental approach) fits the uncertain part (bank integrations): short time-boxed iterations, early integration and feedback, early spikes to retire the API risk, ability to absorb interface changes (adapters / anti-corruption layer isolating the banks behind one internal payment interface). Its weaknesses: weak natural fit with fixed milestones and heavy documentation, and with a fixed scope contract.
Hybrid: a plan-driven backbone (architecture, requirements baseline, compliance documentation, milestones aligned with the end of increments) with agile iterations inside the increments; the first increment (month 6) delivers a usable subset (for example filing and calculation for simple cases with one bank); remaining increments add banks and features. The deadline is fixed, so time is the constraint and the scope is the variable: prioritise (for example MoSCoW), keep a buffer, cut or defer lower-priority features rather than the date. Documentation is produced incrementally and baselined at each milestone to satisfy procurement. Risks are managed explicitly (risk-driven ordering: bank integration spikes first).""",
"""ESSENTIAL: recommends a hybrid and explicitly assigns the stable core (filing/calculation) to a plan-driven V approach and the uncertain bank integrations to iterative/agile work.
ESSENTIAL: addresses the uncertainty and changes of the bank APIs (early spikes, adapters/isolation, short feedback loops).
ESSENTIAL: addresses the 6-month usable subset through incremental delivery.
ESSENTIAL: treats the fixed deadline as a time-box with variable scope (prioritisation, deferral) and addresses documentation/milestones required by procurement.
Fair weaknesses of both pure approaches must be given.""",
"A good answer splits the project: V-model backbone for the stable rules and the compliance documentation, Scrum-like increments for the uncertain bank integrations, an early incremental release at month 6, a fixed date with variable scope, and documents produced and baselined at each increment.",
t=480)

# 3 -------------------------------------------------------------------------------------------------------------------
op("fr",
"""La figure représente la classe GestionnaireScolarité d'une application d'université et les classes dont elle dépend.

1) Qualifiez la cohésion de la classe (avec la typologie de Myers/Constantine, de la plus faible à la plus forte) et le couplage avec les classes dépendantes (types de couplage), en justifiant avec des éléments précis de la figure.
2) Donnez deux conséquences concrètes de cette conception sur l'évolution et sur les tests du logiciel.
3) Proposez un découpage en au moins quatre classes en nommant la responsabilité de chacune, le principe de conception appliqué, et la façon dont vous réduiriez le couplage avec SmtpClient et MySqlConnection.""",
"""1) Cohésion très faible : la classe regroupe des responsabilités sans lien (inscription, calcul de moyennes, génération de bulletin PDF, envoi d'e-mails, persistance SQL, import CSV, planification d'examens) : cohésion coïncidente ou logique (« tout ce qui touche à la scolarité »), loin de la cohésion fonctionnelle ; elle viole le principe de responsabilité unique (SRP). Couplage : vers Etudiant, Cours, Note : couplage par données/estampille (objets du domaine passés en paramètres), normal. Vers PdfLib, SmtpClient, MySqlConnection, CsvParser : dépendances directes vers des classes concrètes de technologie, couplage fort (à l'implémentation) qui viole l'inversion de dépendances (DIP).
2) Conséquences : changer de bibliothèque PDF, de serveur SMTP ou de base impose de modifier cette même classe énorme (plusieurs raisons de changer, risque de régression, conflits de fusion dans l'équipe) ; impossible de tester le calcul de moyenne ou l'inscription sans base de données, serveur de mail ni bibliothèque PDF (tests lents, fragiles, pas unitaires).
3) Découpage : InscriptionService (inscrire, règles d'inscription), CalculNotesService (moyenne, seuil d'admission), BulletinService (génération du bulletin) avec une interface BulletinRenderer (implémentation PDF), NotificationService avec une interface Notifier (implémentation SMTP), EtudiantRepository (interface de persistance, implémentation MySQL), ImportNotesService (CSV), PlanificationExamens. Principes : SRP, haute cohésion, séparation des préoccupations, DIP : les services dépendent d'interfaces injectées (injection par constructeur) ; SMTP et MySQL derrière des interfaces (Notifier, Repository) permettent de les remplacer par des faux dans les tests.""",
"""ESSENTIEL : identifie la cohésion faible (responsabilités multiples sans lien, SRP) avec des exemples de méthodes de la figure.
ESSENTIEL : distingue le couplage vers les classes du domaine (acceptable) du couplage fort vers les classes techniques concrètes (PdfLib, SmtpClient, MySqlConnection, CsvParser).
ESSENTIEL : au moins deux conséquences pertinentes (modifications multiples, testabilité).
ESSENTIEL : au moins quatre classes ou services avec une responsabilité unique chacune et usage d'interfaces/abstractions injectées pour l'e-mail et la base (DIP).""",
"La classe est un « god object » : cohésion coïncidente/logique, couplage fort avec des technologies concrètes. On la scinde en services à responsabilité unique et on place les technologies derrière des interfaces injectées.",
img="godclass_fr.png", t=480)

# 4 -------------------------------------------------------------------------------------------------------------------
op("en",
"""Listing 1 is a method that runs in production in an online shop.

(a) Identify at least six distinct code smells or design-principle violations in it, quoting the line numbers.
(b) For three of them, name the standard refactoring or design pattern that removes the problem and describe how the code changes.
(c) Explain why the method is hard to test and what you must introduce to test its calculation in isolation.""",
"""(a) Smells: long method doing several jobs (pricing, tax, discount, payment, logging, mailing, persistence): SRP violation, lines 2-21. If/else chains on a String code: country (7-9) and payment type (11-16): switch on type, OCP violation, to be replaced by polymorphism/Strategy. Magic numbers (1.1925, 1.20, 1.19, 0.9) lines 7-10. Feature envy / Law of Demeter: line 5 digs into o.items.get(i).price and qty to compute what Order or OrderLine should compute. Primitive obsession: country and payType are Strings. Boolean flag parameter vip (line 2, 10). Public fields accessed directly (o.items, o.cardNo, o.email): data class without behaviour. Hard-wired static collaborators (Bank, PayPal, Mailer, Database) lines 13, 16, 18, 19: DIP violation. Mixing I/O (System.out lines 12, 15) with domain calculation. Duplicated structure between the two payment branches.
(b) Extract Method / Move Method (total computation moves to Order.total() and OrderLine.subtotal()); Replace Conditional with Polymorphism or Strategy (a TaxPolicy per country, a PaymentMethod per type, chosen through a map or factory); Replace Magic Number with Symbolic Constant / Introduce Parameter Object (a Customer or Discount object instead of a boolean); Dependency Injection (constructor injection of interfaces).
(c) Calculation, payment, mail and database are tangled and the collaborators are static or hard-coded, so a test would charge a card, send an email and write to the database. To test the calculation: separate the pure computation (total with tax and discount) into a method or class with no I/O, and inject the collaborators as interfaces (PaymentGateway, Mailer, OrderRepository) so tests use fakes.""",
"""ESSENTIAL: at least six valid, distinct smells with correct line references (for example long method/SRP, conditional chains/OCP, magic numbers, feature envy, primitive obsession, boolean flag, hard-wired static dependencies/DIP, I/O mixed with logic).
ESSENTIAL: three refactorings or patterns correctly named (Extract/Move Method, Replace Conditional with Polymorphism or Strategy, Symbolic Constant, Parameter Object, Dependency Injection...) with a correct description of the change.
ESSENTIAL: testability explanation naming the static/hard-coded collaborators and the remedy: dependency injection of abstractions and separation of the pure calculation.""",
"Many smells share one root: the method has too many reasons to change and hard-wires its collaborators. Extract the calculation, put conditionals behind polymorphism and inject the collaborators.",
img="smell_order_en.png", t=540)

# 5 -------------------------------------------------------------------------------------------------------------------
op("fr",
"""La figure montre l'état des dépôts après un incident. Bob a rebasé la branche feature sur la nouvelle pointe de main (M4) puis a poussé avec l'option de forçage. Alice avait déjà basé son travail sur l'ancienne version de feature et a écrit deux commits locaux, A1 et A2.

1) Expliquez précisément pourquoi B1' et B2' sont des commits différents de B1 et B2, alors que leurs modifications sont les mêmes.
2) Décrivez ce qui se passe si Alice exécute un pull avec fusion, puis ce qui se passe avec un pull avec rebase.
3) Quelle règle d'équipe aurait évité l'incident, et que doit faire Alice maintenant pour récupérer proprement son travail ?
4) Proposez une stratégie de branches adaptée à une équipe de six personnes qui intègre chaque jour.""",
"""1) L'identité d'un commit est son empreinte (SHA) calculée sur son contenu complet : arbre de fichiers, commit(s) parent(s), auteur, dates et message. Le rebase rejoue les modifications sur un autre parent (M4 au lieu de M2) : le parent change, donc l'arbre et l'empreinte aussi : ce sont de nouveaux commits (B1', B2'), même si le diff est le même.
2) Pull avec fusion : Git récupère B1', B2' ; la base commune des deux lignes est M2 ; la fusion rassemble l'ancienne ligne (B1, B2, A1, A2) et la nouvelle (M3, M4, B1', B2') : les modifications de Bob apparaissent deux fois dans l'historique, des conflits sont probables, et on obtient un commit de fusion inutile. Pull avec rebase : les commits locaux d'Alice sont rejoués sur origin/feature ; Git détecte que B1 et B2 ont le même patch que B1' et B2' et les écarte ; il reste A1' et A2' posés sur B2' (des conflits restent possibles).
3) Règle : ne jamais réécrire l'historique d'une branche déjà publiée et partagée ; réserver le rebase aux branches locales non publiées ; si un forçage est indispensable, utiliser un forçage protégé (force-with-lease), prévenir l'équipe et protéger les branches partagées. Pour récupérer : récupérer (fetch) l'état distant puis rejouer uniquement les commits A1 et A2 sur la nouvelle pointe, par exemple rebase --onto origin/feature B2 (ancienne base) ou cherry-pick de A1 et A2 sur origin/feature, puis pousser normalement.
4) Stratégie : développement sur tronc (trunk-based) ou GitHub flow : main toujours livrable et protégé ; branches de fonctionnalité courtes (un jour ou deux) ; intégration quotidienne ; revue de code par demande de fusion ; intégration continue obligatoire avant la fusion ; drapeaux de fonctionnalité pour livrer du travail inachevé ; pas de branche longue durée.""",
"""ESSENTIEL : explique que l'empreinte dépend notamment du parent, que le rebase change le parent et crée donc de nouveaux commits.
ESSENTIEL : décrit correctement le comportement du pull avec fusion (doublons, conflits probables, commit de fusion) et du pull avec rebase (rejeu des commits d'Alice, détection des commits équivalents).
ESSENTIEL : énonce la règle de ne pas réécrire l'historique partagé (et mentionne un forçage protégé) et donne une récupération correcte (rebase --onto ou cherry-pick des seuls commits A1 et A2).
ESSENTIEL : stratégie de branches cohérente avec l'intégration quotidienne (branches courtes, tronc protégé, intégration continue).""",
"Un commit est identifié par son empreinte, qui dépend de son parent : un rebase crée de nouveaux commits. D'où le principe : ne pas réécrire l'historique publié, et récupérer ses commits avec rebase --onto ou cherry-pick.",
img="gitgraph_fr.png", t=480)

# 6 -------------------------------------------------------------------------------------------------------------------
op("fr",
"""La figure donne la spécification de la fonction net(m, fidèle).

1) Partitionnez le domaine d'entrée en classes d'équivalence valides et invalides, et expliquez pourquoi la règle R5 ne peut pas être testée indépendamment des règles R2 à R4.
2) Donnez un jeu de tests aux valeurs limites d'au moins dix cas : valeur de m, valeur de fidèle et résultat attendu, calculé selon la spécification.
3) Montrez, avec un exemple chiffré, comment un développeur peut appliquer R5 dans le mauvais ordre (plafonnement avant ou après l'ajout des deux points) et quel cas de test révèle l'erreur.""",
"""1) Classes : invalide : m <= 0 (et éventuellement valeur non numérique/absente) ; valides : 0 < m < 50 000 (remise de base 0 %), 50 000 <= m < 200 000 (5 %), m >= 200 000 (10 %), chacune croisée avec fidèle = oui / non. R5 dépend de la remise de base : la remise totale = min(base + 2, 10) pour un fidèle, donc son effet (surtout le plafond à 10 %) n'existe qu'en combinaison avec R2-R4 ; il faut croiser les classes.
2) Valeurs limites et résultats (arrondi à l'entier le plus proche, 0,5 vers le haut) : m = 0 -> erreur « montant invalide » ; m = -1 -> erreur ; m = 0,01 non fidèle -> 0 ; m = 49 999,99 non fidèle -> 50 000 ; m = 49 999,99 fidèle (2 %) -> 49 000 ; m = 50 000 non fidèle (5 %) -> 47 500 ; m = 50 000 fidèle (7 %) -> 46 500 ; m = 199 999,99 non fidèle (5 %) -> 190 000 ; m = 199 999,99 fidèle (7 %) -> 186 000 ; m = 200 000 non fidèle (10 %) -> 180 000 ; m = 200 000 fidèle (12 % plafonné à 10 %) -> 180 000 ; m = 200 001 fidèle -> 180 001 (200 001 x 0,9 = 180 000,9 -> 180 001).
3) Pour m = 200 000 et fidèle : correct : base 10 % + 2 = 12 %, plafonné à 10 % -> 180 000. Si le développeur plafonne la base à 10 % puis ajoute 2 points sans replafonner : 12 % -> 176 000 (erreur). Le cas m = 200 000, fidèle = oui (et tout m >= 200 000 fidèle) révèle l'erreur ; de même m = 190 000 fidèle : 5 % + 2 = 7 % -> 176 700 (pas de plafonnement en jeu).""",
"""ESSENTIEL : classes invalides (m <= 0) et trois classes valides croisées avec fidèle ; explication de l'interdépendance R5 / R2-R4 (le plafond dépend de la remise de base).
ESSENTIEL : valeurs limites autour de 0, 50 000 et 200 000 (juste en dessous, sur la limite, juste au-dessus), avec au moins dix cas et des résultats exacts : par exemple 50 000 non fidèle -> 47 500 ; 50 000 fidèle -> 46 500 ; 200 000 non fidèle -> 180 000 ; 200 000 fidèle -> 180 000.
ESSENTIEL : l'exemple d'ordre incorrect doit être chiffré (200 000 fidèle : 180 000 attendu contre 176 000 si le plafond n'est pas appliqué après l'ajout) et désigner le cas qui le détecte.
Vérifier les calculs ; une erreur arithmétique sur un cas non essentiel ne suffit pas à échouer si les autres points sont corrects.""",
"Il faut croiser les classes de montant avec le statut fidèle, tester chaque limite (0, 50 000, 200 000) de part et d'autre, et vérifier le plafonnement : 200 000 fidèle doit donner 180 000, pas 176 000.",
img="spec_remise_fr.png", t=600)

# 7 -------------------------------------------------------------------------------------------------------------------
op("en",
"""Use the activity network in the figure (durations in days).

(1) Compute for every activity the earliest start and finish and the latest start and finish. Give the critical path and the project duration.
(2) Give the total float of activities C and D, and the free float of C. Explain the difference between total float and free float.
(3) The project manager can shorten activity G by two days. What are the new project duration and critical path(s)? What happens if G is shortened by only one day?""",
"""(1) Forward pass (ES-EF): A 0-3; B 0-4; C 3-5; D 3-8; E 4-7; F 8-12 (waits for D); G 7-13; H 13-15 (waits for G); I 15-18. Backward pass (LS-LF): I 15-18; H 13-15; G 7-13; F 9-13; E 4-7; D 4-9; C 7-9; B 0-4; A 1-4 (min of LS of C and D, which is 4). Critical path B-E-G-H-I (zero float), duration 4+3+6+2+3 = 18 days. The path A-D-F-H-I lasts 17 days.
(2) Total float = LS - ES (or LF - EF): C = 7 - 3 = 4 days; D = 4 - 3 = 1 day. Free float = the delay of an activity that does not delay the earliest start of any successor: C: ES of F (8) - EF of C (5) = 3 days. Total float can be shared with the following activities and delays them; free float only concerns the immediate successors and does not move them.
(3) G shortened by 2 days (4 days): path B-E-G-H-I = 16, path A-D-F-H-I = 17: new duration 17 days, the critical path becomes A-D-F-H-I only. G shortened by 1 day (5 days): both paths last 17 days: duration 17 days with two critical paths (B-E-G-H-I and A-D-F-H-I); no further gain is possible by shortening only G.""",
"""ESSENTIAL: critical path B-E-G-H-I and duration 18 days, backed by the forward and backward passes (ES/EF/LS/LF mostly correct).
ESSENTIAL: total floats C = 4 and D = 1, free float of C = 3, with a correct definition of both floats.
ESSENTIAL: G reduced by 2 gives 17 days with critical path A-D-F-H-I; G reduced by 1 gives 17 days with two critical paths.""",
"The longest path (B-E-G-H-I, 18 days) is critical. Shortening G only helps until the second path (A-D-F-H-I, 17 days) becomes critical: after one day it is a tie, after two days the new path A-D-F-H-I alone is critical and the duration is 17.",
img="pert_en.png", t=600)

# 8 -------------------------------------------------------------------------------------------------------------------
op("fr",
"""Selon la norme de maintenance du logiciel (ISO/IEC 14764), classez chacun des six événements suivants dans la catégorie correcte (corrective, adaptative, perfective ou préventive) et justifiez chaque choix en une phrase :
(1) un bogue de calcul d'arrondi est corrigé après la plainte d'un utilisateur ;
(2) le logiciel est migré vers une nouvelle version majeure du système de gestion de base de données, qui n'est plus compatible avec l'ancienne ;
(3) un module est restructuré pour réduire sa complexité, sans qu'aucun utilisateur ne l'ait demandé ;
(4) un nouvel état de rapport demandé par les utilisateurs est ajouté ;
(5) le format de la déclaration fiscale change par décret et le logiciel est mis à jour ;
(6) des index sont ajoutés sur une base de données pour éviter la dégradation prévisible des temps de réponse lorsque le volume doublera.
Indiquez enfin quelle catégorie représente en général la plus grande part du coût de maintenance, et pourquoi.""",
"""(1) Corrective : on corrige un défaut constaté. (2) Adaptative : le logiciel est adapté à un changement de son environnement technique (le SGBD). (3) Préventive : on améliore la maintenabilité pour éviter des problèmes futurs, sans changement fonctionnel ni demande. (4) Perfective : amélioration/évolution fonctionnelle demandée par les utilisateurs. (5) Adaptative : adaptation à un changement de l'environnement réglementaire/externe. (6) Préventive : action anticipant une dégradation prévisible avant qu'elle n'apparaisse (une réponse perfective, amélioration de performance, est acceptable si elle est justifiée par l'amélioration de la performance).
La maintenance perfective (évolutive) représente en général la plus grande part du coût (de l'ordre de la moitié) : les utilisateurs demandent sans cesse de nouvelles fonctionnalités et améliorations pendant toute la vie du logiciel, plus que de corrections.""",
"""ESSENTIEL : (1) corrective, (2) adaptative, (3) préventive, (4) perfective, (5) adaptative, chacune avec une justification correcte ; (6) préventive acceptée (ou perfective si justifiée par la performance).
ESSENTIEL : la maintenance perfective est la plus coûteuse, avec une explication fondée sur les demandes d'évolution des utilisateurs.
Au plus une erreur de classement parmi (1) à (5) tolérée si la justification est cohérente ; deux erreurs ou plus : incorrect.""",
"Corrective : défaut ; adaptative : changement d'environnement technique ou réglementaire ; perfective : nouvelle fonctionnalité ou amélioration ; préventive : réduction de risques futurs. La perfective domine le coût.",
t=420)

# 9 -------------------------------------------------------------------------------------------------------------------
op("en",
"""The figure is a traceability matrix: the modules each test exercises and the requirements each test verifies. A change has just been made in modules M3 and M5.

(1) List the tests affected by the change and the requirements concerned.
(2) Give a smallest regression suite that exercises both changed modules and every requirement impacted, and justify why it is minimal.
(3) Identify two weaknesses of the test suite visible in the matrix and say what to do about each.
(4) Explain why selecting regression tests from this matrix alone is not safe, and what additional information is needed.""",
"""(1) Tests exercising M3: T2, T3, T4; tests exercising M5: T7, T8. Affected tests: T2, T3, T4, T7, T8. Requirements verified by these tests: R1 (T2), R2 (T2, T3), R3 (T4), R5 (T7, T8): impacted requirements R1, R2, R3, R5.
(2) Minimal suite of three tests: T2 (M3; verifies R1 and R2), T4 (M3, M4; verifies R3) and T7 or T8 (M5; verifies R5). It is minimal because among the tests that touch a changed module R1 is covered only by T2, R3 only by T4, and R5 needs one of T7/T8: three distinct tests are needed (T2 already covers R1 and R2 together), so no suite of two tests can work.
(3) R4 is not verified by any test: a requirements coverage gap (write the missing test). Redundant tests: T6 verifies the same module and requirement as T1 (M1/R1 is already covered by T1), and T5 (M4, R3) is covered by T4: candidates to merge or remove to reduce maintenance and run time, after checking that they do not detect different faults.
(4) The matrix only records direct coverage. A change can break modules that depend on the changed ones (M4 is used by tests T4, T5, T8 and might depend on M3 or M5): you need a dependency or impact analysis (call graph, data flow, changed lines), coverage data by line or function, and risk/defect history to select and prioritise tests; also unchanged modules reached indirectly must be considered.""",
"""ESSENTIAL: affected tests T2, T3, T4, T7, T8 and impacted requirements R1, R2, R3, R5.
ESSENTIAL: a minimal suite of three tests (T2, T4, and T7 or T8) with a valid minimality argument.
ESSENTIAL: names the uncovered requirement R4 and a redundancy (T5 or T6) with an appropriate action.
ESSENTIAL: explains that direct coverage in the matrix ignores indirect impact and asks for dependency/impact analysis (call graph, data flow) or similar information.""",
"A matrix gives direct coverage only. Here T2, T4 and T7 or T8 are enough to re-test the change; R4 has no test and T5/T6 are redundant, and a real selection also needs dependency information.",
img="trace_en.png", t=540)

# 10 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Une startup lance une application de transfert d'argent mobile pour des zones rurales : réseaux 2G/3G instables, téléphones d'entrée de gamme, utilisateurs peu alphabétisés, réglementation financière stricte.

Parmi les caractéristiques de qualité de la norme ISO/IEC 25010, choisissez les quatre les plus critiques et classez-les par priorité en justifiant. Décrivez ensuite, pour deux d'entre elles, une tactique d'architecture ou de conception concrète. Identifiez enfin un conflit entre deux de ces caractéristiques et expliquez le compromis que vous retiendriez.""",
"""Réponse attendue (exemple, d'autres choix argumentés sont acceptés) : 1) Fiabilité (disponibilité, tolérance aux pannes, récupérabilité) : réseaux instables et argent en jeu : une transaction ne doit jamais être perdue ni exécutée deux fois. 2) Sécurité (confidentialité, intégrité, non-répudiation, authenticité) : argent réel, fraude, réglementation. 3) Utilisabilité (accessibilité, facilité d'apprentissage) : utilisateurs peu alphabétisés : icônes, langues locales, parcours courts. 4) Efficacité de performance (consommation de bande passante, temps de réponse) ou portabilité/compatibilité (téléphones d'entrée de gamme).
Tactiques : fiabilité : opérations idempotentes avec identifiant de transaction, file d'attente locale avec renvoi (store-and-forward), repli USSD/SMS quand les données ne passent pas, journalisation transactionnelle côté serveur. Sécurité : authentification par code PIN lié à l'appareil, chiffrement TLS, journal d'audit infalsifiable, limitation du nombre d'essais, signature des transactions. Performance : messages compacts, compression, cache local, client léger.
Conflit : sécurité contre utilisabilité (authentification forte contre utilisateurs peu alphabétisés) ou sécurité contre performance (chiffrement coûteux sur téléphone modeste) ou fiabilité (renvois) contre exactitude (doublons). Compromis argumenté : authentification adaptée au risque (PIN simple pour les petits montants, confirmation renforcée au-delà d'un seuil) pour rester conforme à la réglementation sans bloquer les utilisateurs.""",
"""ESSENTIEL : quatre caractéristiques ISO/IEC 25010 correctement nommées (fiabilité, sécurité, utilisabilité, efficacité de performance, adéquation fonctionnelle, compatibilité, portabilité, maintenabilité) hiérarchisées avec une justification liée au contexte donné ; pour une application financière, l'absence de la sécurité ou de la fiabilité doit être justifiée explicitement.
ESSENTIEL : deux tactiques concrètes et précises (pas des généralités).
ESSENTIEL : un vrai conflit entre deux caractéristiques et une décision de compromis argumentée.""",
"Il s'agit de relier les caractéristiques de qualité au contexte (réseau instable, appareils modestes, utilisateurs peu alphabétisés, argent réel), de proposer des tactiques concrètes et d'assumer un compromis, par exemple une authentification adaptée au risque.",
t=540)

# 11 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""La figure est un diagramme de cas d'utilisation d'une bibliothèque universitaire. Il contient au moins sept erreurs de modélisation (de notation, de sémantique ou de niveau d'abstraction).

Repérez-en au moins six, expliquez pourquoi chacune est incorrecte et donnez la correction.""",
"""1) La relation « include » est dessinée dans le mauvais sens : la flèche doit aller du cas de base (Emprunter un livre) vers le cas inclus (Vérifier l'adhésion).
2) « extend » : la flèche va du cas de base vers l'extension, alors qu'elle doit aller de l'extension vers le cas de base ; de plus S'authentifier est une étape obligatoire (il faut être authentifié pour emprunter) : c'est une inclusion, pas une extension (comportement optionnel et conditionnel).
3) Une association relie directement deux cas d'utilisation (Emprunter un livre — Rendre un livre) : on ne relie pas deux cas par une association ; les liens entre cas sont include, extend ou la généralisation ; les acteurs participent aux cas par des associations.
4) Une association relie deux acteurs (Membre — Bibliothécaire, « valide ») : le diagramme de cas d'utilisation ne modélise pas les échanges entre acteurs ; on utilise la généralisation entre acteurs s'il y a une hiérarchie de rôles, ou on supprime le lien.
5) Le cas « Payer l'amende » est dessiné hors de la frontière du système : un cas d'utilisation fait partie du système ; seuls les acteurs sont à l'extérieur.
6) « Mettre à jour la table EXEMPLAIRE » est un détail d'implémentation, pas un objectif d'acteur : le cas doit exprimer un but métier (par exemple « Enregistrer un nouvel exemplaire »).
7) « Catalogue des livres » est un nom (un objet), pas une action : un cas d'utilisation se nomme par un verbe d'action vue de l'acteur (« Consulter le catalogue »).""",
"""ESSENTIEL : au moins six des sept erreurs, chacune avec une justification et une correction correctes : (1) sens de include ; (2) sens et nature de extend (obligatoire donc include) ; (3) association entre deux cas d'utilisation ; (4) association entre deux acteurs ; (5) cas hors de la frontière du système ; (6) cas trop technique, niveau d'implémentation ; (7) nom de cas qui n'est pas une action.
Une explication fausse pour une erreur ne compte pas pour cette erreur.""",
"Sept erreurs : include inversé ; extend inversé et mal choisi ; association cas-cas ; association acteur-acteur ; cas hors système ; cas trop technique ; nom de cas non verbal.",
img="uc_bib_fr.png", t=540)

# 12 ------------------------------------------------------------------------------------------------------------------
op("en",
"""Use the use case 'Book a ticket' of the figure. Write its textual specification: actors, preconditions, trigger, a main success scenario with at least eight numbered steps alternating actor and system actions, at least two alternative flows and two exception flows (each tied to a step number), the success postcondition and the minimal guarantee.

Show where the inclusions and the extension points of the diagram appear in your steps. Then explain which changes of the diagram are required if a passenger must also be able to reserve a seat for 24 hours without paying immediately.""",
"""Actors: Passenger (primary); Payment provider (secondary, used by Pay). Preconditions: the passenger has selected an itinerary and a date; train services exist. Trigger: the passenger asks to book a ticket.
Main scenario (example): 1 Passenger chooses the itinerary and the date. 2 System shows the available trains and fares. 3 Passenger selects a train and the number of passengers. 4 System checks availability and holds the places for a limited time. 5 Passenger confirms the choice. [Extension point: Choose a seat, at step 5; Apply a rail pass, at step 6.] 6 System shows the price. 7 Passenger asks to pay. 8 System runs the included use case Pay (the payment provider authorises the amount). 9 System confirms the booking and issues the ticket. 10 System runs the included use case Send the e-ticket.
Alternative flows: A1 at step 3: the passenger changes the date: back to step 2. A2 at step 5 (extension): the passenger chooses a seat (Choose a seat extends the base use case): the system shows the seat map and records the seat, then step 6 resumes. A3 at step 6 (extension): the passenger applies a rail pass: the system recalculates the price. Exception flows: E1 at step 4: no more places: the system proposes other trains and the use case resumes at step 2 or ends. E2 at step 8: payment refused or times out: the system releases the held places and offers another means of payment or cancels (no ticket issued). E3 at step 10: e-ticket sending fails: the ticket remains available in the account and the system retries.
Postconditions: success: the booking is recorded, places allocated, payment captured, ticket issued and sent. Minimal guarantee: whatever happens, no payment is taken without a ticket and held places are released on abandon.
Change for the 24-hour reservation: Pay is currently a mandatory inclusion, so payment cannot be skipped. It must become optional: Pay becomes an extension (with a condition such as 'pay now') or a separate use case 'Reserve for 24 hours' is added as an alternative flow; Send the e-ticket must then depend on payment (included by Pay or an extension of it), not on Book a ticket, because no ticket is issued without payment; the specification gains an expiry rule and a follow-up use case to pay before expiry.""",
"""ESSENTIAL: complete structure: actors, preconditions, trigger, a numbered main scenario of at least eight steps alternating actor and system, at least two alternative and two exception flows tied to step numbers, success postcondition and minimal guarantee.
ESSENTIAL: the included use cases (Pay, Send the e-ticket) are invoked at appropriate steps; the extension points (Choose a seat, Apply a rail pass) are located at steps and handled as optional.
ESSENTIAL: for the 24-hour reservation, recognises that the mandatory inclusion of Pay prevents it and proposes a correct change (Pay becomes an extension or an alternative use case is added) and re-attaches Send the e-ticket to payment.""",
"A use case specification ties every alternative and exception to a step, shows where included use cases are called and where extension points allow optional behaviour, and states success and minimal guarantees. Booking without immediate payment makes Pay optional.",
img="uc_ticket_en.png", t=720)

# 13 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Comparez le diagramme d'objets au diagramme de classes de la figure. Listez toutes les violations (il y en a au moins sept) en désignant l'objet ou le lien fautif et la règle du diagramme de classes qui est enfreinte. Dites ensuite lesquelles seraient détectées par un simple contrôle de multiplicités et lesquelles nécessitent une autre règle.""",
"""1) t3 est lié aux deux projets p1 et p2 : une tâche est une partie composée d'un seul projet à la fois (composition exclusive ; multiplicité 1 côté Projet).
2) t4 n'appartient à aucun projet : la multiplicité 1 côté Projet impose exactement un projet.
3) t2 est assignée à deux développeurs d1 et d2 : multiplicité 1 côté Développeur (une tâche a exactement un développeur).
4) d1 a quatre tâches (t1, t2, t3, t4) : la multiplicité 0..3 côté Tâche limite un développeur à trois tâches.
5) m1 est une instance de Membre, classe abstraite : une classe abstraite ne peut pas être instanciée.
6) Les équipes e1 et e2 n'ont chacune qu'un seul développeur : multiplicité 2..5 côté Développeur de l'association avec Équipe.
7) Un lien existe entre t1 et e2 (Tâche — Équipe) alors qu'aucune association n'existe entre ces classes : un lien doit être l'instance d'une association.
Contrôle de multiplicités : 2, 3, 4, 6 (et 1 en partie : le nombre de projets d'une tâche). Autres règles : 5 (classe abstraite non instanciable), 7 (lien sans association) et l'exclusivité de la composition pour 1 (une partie ne peut appartenir à deux composites).""",
"""ESSENTIEL : au moins sept violations distinctes, chacune avec l'objet ou le lien fautif et la règle enfreinte : t3 dans deux projets (composition exclusive), t4 sans projet, t2 avec deux développeurs, d1 avec quatre tâches, m1 instance d'une classe abstraite, équipes avec un seul développeur (e1, e2), lien t1-e2 sans association.
ESSENTIEL : distingue les violations de multiplicité de celles qui relèvent d'autres règles (classe abstraite, lien sans association, exclusivité de la composition).""",
"Un diagramme d'objets valide respecte les multiplicités, l'exclusivité des compositions, l'interdiction d'instancier une classe abstraite et le fait que tout lien instancie une association.",
img="objviol_fr.png", t=600)

# 14 ------------------------------------------------------------------------------------------------------------------
op("en",
"""The class diagram in the figure contains at least six design or notation flaws. Identify at least five of them, explain why each is a flaw and say how you would correct the diagram.""",
"""1) OrderLine is a composed part of both Order and Cart: a part in a composition belongs to at most one whole. Fix: keep the composition with Order and give the cart its own part class (CartLine), or make Cart an aggregation/association if lines must be shared (usually convert a cart into an order by creating order lines).
2) Invoice inherits from Order: an invoice is not a kind of order (it fails the 'is-a' test and the Liskov substitution principle): inheritance used for reuse. Fix: association or dependency between Invoice and Order (an invoice is issued for an order), or a common abstraction such as Document.
3) The multiplicity 1..* on the Customer side of 'places' means an order can be placed by several customers: it should be exactly 1 (an order has one customer).
4) Redundant attribute: Order.customer duplicates the association to Customer (association ends already express it) and Customer.customerName duplicates name. Fix: remove the attributes and name the association end (role).
5) Order.totalPrice is derivable from the order lines: it should be a derived attribute (/totalPrice) computed by an operation, not stored redundantly.
6) The attribute email in Customer is public (+): breaks encapsulation. Fix: private attribute with accessor operations.""",
"""ESSENTIAL: at least five of the six flaws with a correct explanation and correction: (1) OrderLine part of two composites; (2) Invoice inheriting from Order (is-a / LSP); (3) wrong multiplicity of customers per order; (4) redundant attributes duplicating association or another attribute; (5) stored derived attribute totalPrice; (6) public attribute breaking encapsulation.""",
"Typical flaws: a shared part in two compositions, inheritance for reuse, a wrong multiplicity, redundant or derivable attributes, and public attributes.",
img="classcrit_en.png", t=600)

# 15 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""La figure montre un diagramme de séquence du paiement de frais de scolarité, avec le contrat (les opérations) des classes concernées.

1) Relevez au moins quatre incohérences ou défauts (entre le diagramme et les interfaces, ou internes au diagramme) et corrigez chacun.
2) Décrivez ensuite en prose le scénario corrigé complet, y compris le cas de refus du paiement.""",
"""Défauts : (1) Le message débiter(montant) ne respecte pas la signature Paiement.débiter(montant, compte) : le paramètre compte manque. (2) Les appels synchrones débiter et autoriser retournent un booléen utilisé ensuite (garde [accepté]) mais aucun message de retour n'est dessiné : il manque les retours (booléen) de Banque vers Paiement puis de Paiement vers Portail. (3) Le message archiver() est envoyé à :Banque après la destruction de sa ligne de vie (croix), et Banque n'a pas d'opération archiver dans son contrat : un message ne peut pas être envoyé à un objet détruit ; il faut l'adresser à la bonne classe (par exemple Paiement ou un journal) et avant la destruction, ou supprimer la destruction. (4) Le fragment alt n'a qu'un opérande [accepté] : le cas de refus n'est pas modélisé (il manque un opérande [else] avec un message d'échec à l'étudiant, absent aussi du contrat de Portail). (5) La garde [accepté] ne correspond à aucune valeur retournée visible dans le diagramme.
Scénario corrigé : l'étudiant appelle payer(montant) sur Portail ; Portail appelle débiter(montant, compte) sur Paiement ; Paiement appelle autoriser(compte, montant) sur Banque ; Banque retourne un booléen à Paiement qui le retourne à Portail ; fragment alt : [accepté = vrai] Portail envoie confirmer() à l'étudiant ; [sinon] Portail notifie le refus à l'étudiant (nouvelle opération, par exemple refuser(), à ajouter au contrat) ; l'archivage éventuel est un message à l'objet qui a cette opération, envoyé tant que la ligne de vie existe.""",
"""ESSENTIEL : au moins quatre défauts distincts parmi : paramètre compte manquant dans débiter, retours manquants des appels synchrones, message envoyé après destruction / opération absente du contrat de Banque, alt sans branche [else], garde sans valeur retournée.
ESSENTIEL : chaque défaut est accompagné d'une correction correcte.
ESSENTIEL : le scénario corrigé inclut les retours et la branche de refus.""",
"Un diagramme de séquence doit rester cohérent avec le diagramme de classes (signatures, opérations existantes), montrer les retours utiles aux gardes, ne pas envoyer de message à un objet détruit et couvrir les branches d'un alt.",
img="seq_defect_fr.png", t=600)

# 16 ------------------------------------------------------------------------------------------------------------------
op("en",
"""The figure shows the state machine of a print job.

(1) Find at least four defects of the model (consider determinism, completeness, reachability and termination) and correct each of them.
(2) In the machine as drawn, assume that the non-determinism on submit is resolved in favour of the transition to Printing. A job of 3 pages is submitted and then cancelled. Give the exact ordered list of actions executed, and state the UML rule that fixes the order between exit actions, transition effects and entry actions.""",
"""(1) Defects: a) Non-determinism: from Idle two transitions on submit have overlapping guards [pages > 0] and [pages >= 1] (the same condition): both are enabled. Fix: disjoint guards, for example [pages > 0 and printer free] to Printing and [pages > 0 and printer busy] to Queued. b) Incompleteness: no behaviour for submit with pages = 0 (rejection or error transition). c) Archived is unreachable (no incoming transition): add a transition (for example after completion) or remove it. d) Error is a trap state with no outgoing transition and it is not a final state: add reset/clear back to Idle (or a final state). e) Printing has no completion transition: a job can never finish normally: add a 'done' transition to Idle (or Archived). f) Queued has no cancel transition.
(2) Initial state Idle (entry showReady already done). submit(3): leave Idle (no exit action), transition has no effect, entering Printing runs entry / startMotor. cancel: leave Printing: exit / stopMotor, then the transition effect logCancel, then enter Idle: entry / showReady. Order: startMotor, stopMotor, logCancel, showReady.
UML rule: when a transition fires, exit actions of the states left run first (innermost to outermost), then the effect of the transition, then the entry actions of the states entered (outermost to innermost).""",
"""ESSENTIAL: at least four defects among: overlapping guards on submit (non-determinism), missing case pages = 0, unreachable Archived, trap state Error, Printing with no completion transition, Queued without cancel; each with a correct fix.
ESSENTIAL: the action order startMotor, stopMotor, logCancel, showReady.
ESSENTIAL: the UML rule exit actions, then transition effect, then entry actions.""",
"Check the model for non-determinism, completeness, reachability and termination. When a transition fires: exit actions, then transition effect, then entry actions.",
img="state_defect_en.png", t=660)

# 17 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Lisez le diagramme d'activités de la figure (demande de remboursement de frais).

1) Décrivez en prose son comportement, y compris la boucle et le parallélisme.
2) Combien de fois l'activité « Ordonner le virement » est-elle exécutée pour une demande complète dès le premier passage ? Justifiez avec la sémantique des jetons.
3) Relevez un second risque lié à l'objet « dossier » et au parallélisme.
4) Dites en mots comment corriger le diagramme (ce que vous ajoutez, supprimez ou modifiez).""",
"""1) L'étudiant remplit la demande ; la scolarité vérifie le dossier ; s'il est incomplet, l'étudiant le complète et la demande est revérifiée (boucle) ; s'il est complet, un fork lance en parallèle le passage du dossier à l'état « Validé » (scolarité) et le calcul du montant dû (comptabilité), qui lisent/modifient le même objet dossier ; puis le virement est ordonné par la comptabilité et le processus se termine.
2) Deux fois. Le fork produit deux jetons ; chaque branche dépose son jeton dans le nœud objet « dossier ». La barre qui suit n'a qu'une seule arête entrante : ce n'est donc pas une jointure qui attend plusieurs jetons mais un nœud qui laisse passer chaque jeton ; les deux jetons traversent l'un après l'autre et « Ordonner le virement » s'exécute deux fois : double virement.
3) Les deux activités parallèles accèdent au même objet dossier en même temps : conflit d'accès / mise à jour concurrente (accès concurrent) ; le montant peut être calculé sur un dossier pas encore validé ou une mise à jour peut écraser l'autre ; l'état de l'objet n'est pas précisé.
4) Correction : remplacer la barre par une vraie jointure (join) qui reçoit les arêtes des deux branches, de sorte qu'un seul jeton en sorte ; ou rendre les activités séquentielles ; préciser les états de l'objet dossier ([validé], [calculé]) ou utiliser deux objets distincts, puis une seule arête vers « Ordonner le virement ».""",
"""ESSENTIEL : description correcte de la boucle (incomplet -> compléter -> revérifier) et du fork parallèle.
ESSENTIEL : réponse « deux fois » avec l'explication par les jetons (le fork produit deux jetons, la barre après l'objet n'est pas une jointure, chaque jeton traverse).
ESSENTIEL : identifie l'accès concurrent des deux branches au même objet dossier.
ESSENTIEL : correction par une vraie jointure (un seul jeton) ou par une séquentialisation, avec états ou objets distincts.""",
"Une jointure ne se comporte comme telle que si elle reçoit plusieurs arêtes ; ici une barre à une seule arête entrante laisse passer deux jetons et déclenche deux virements. Les branches parallèles partagent aussi un objet modifié.",
img="act_lanes_fr.png", t=600)

# 18 ------------------------------------------------------------------------------------------------------------------
op("en",
"""Review the deployment diagram of an online shop.

Identify at least five architectural weaknesses (availability, security, scalability, operations), each tied to a visible element of the figure. Then describe a target deployment in UML terms (nodes, stereotypes, artifacts, communication paths) that removes them, and justify one trade-off (cost, complexity or latency).""",
"""Weaknesses: (1) Single points of failure: one web node (web01), one application node (app01) and one database node (db01): the loss of any of them stops the shop. (2) No load balancer and no replication: no horizontal scalability or failover. (3) The browser to web01 link is plain HTTP: no confidentiality or integrity (credentials, sessions, card data exposed). (4) All servers are in one network segment reachable from the Internet, including the database port 5432 and the AJP port 8009: no DMZ / segmentation, a direct attack surface on the database. (5) Tomcat admin console exposed on port 8080 with password-only authentication. (6) Backup: nightly dump to tape with an SCP link: recovery point of 24 hours, no replication, restore never tested, backup in the same environment. (7) No cache/CDN for static content.
Target: nodes in separate zones: a DMZ with two reverse proxy / WAF nodes (stereotype device, executionEnvironment Nginx) behind a load balancer; an application zone with at least two app nodes in a cluster hosting the artifact shop.war; a private data zone with a primary database node and a replica (streaming replication, automatic failover); communication paths stereotyped «HTTPS» from client to proxy, TLS between tiers, database reachable only from the application nodes, the admin console reachable only through a VPN or a bastion; off-site backup with point-in-time recovery and periodic restore tests. Trade-off: high availability and segmentation increase cost and operational complexity (more nodes, replication, monitoring) and may add some latency (extra hop through the proxy, synchronous replication); acceptable against the availability and security requirements of an e-commerce service, or reduce by managed services.""",
"""ESSENTIAL: at least five weaknesses tied to visible elements of the figure (single points of failure, plain HTTP, flat/exposed network or database port, exposed admin console, weak backup/recovery point).
ESSENTIAL: a target deployment in UML terms with redundancy (load balancer, several application nodes, replicated database), segmentation (DMZ/private zones) and encrypted paths (HTTPS/TLS).
ESSENTIAL: one justified trade-off (cost, complexity or latency).""",
"The figure shows a single chain of unreplicated nodes in one exposed network with HTTP: the target architecture adds redundancy, segmentation, TLS, restricted administration and a real backup strategy, at a cost in complexity.",
img="deploy_crit_en.png", t=600)

# 19 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Identifiez le patron de conception de la figure en nommant chaque participant (avec son rôle) et le problème qu'il résout.

Un client doit envoyer une alerte par e-mail, puis par SMS, puis par Slack, dans cet ordre : décrivez en mots comment on compose les objets à l'exécution et ce qui se passe lors d'un appel à send. Comparez avec une solution par sous-classes (combien de classes pour trois canaux combinables ?) et expliquez les différences d'intention entre Décorateur, Composite et Proxy, ainsi qu'un inconvénient réel du Décorateur.""",
"""Patron Décorateur. Rôles : Notifier = composant (interface commune) ; EmailNotifier = composant concret ; NotifierDecorator = décorateur abstrait (implémente Notifier et détient une référence wrapped vers un Notifier) ; SmsDecorator et SlackDecorator = décorateurs concrets. Problème : ajouter des responsabilités (canaux d'envoi) à un objet dynamiquement et de façon combinable, sans modifier sa classe et sans explosion de sous-classes.
Composition : on crée un EmailNotifier, on l'enveloppe dans un SmsDecorator, puis on enveloppe celui-ci dans un SlackDecorator ; le client ne connaît que l'interface Notifier et appelle send sur l'objet le plus externe. Chaque décorateur délègue d'abord à l'objet enveloppé puis ajoute son propre envoi (ou l'inverse selon l'implémentation) : avec la délégation avant le comportement propre, l'ordre des envois est e-mail, SMS puis Slack.
Sous-classes : pour trois canaux combinables, il faudrait une classe par combinaison non vide : 7 (2^3 - 1) classes (davantage si l'ordre compte), qui croît de façon exponentielle ; avec le Décorateur : une interface, une classe concrète, un décorateur abstrait et deux décorateurs : croissance linéaire, et la combinaison est choisie à l'exécution.
Intentions : Décorateur ajoute des responsabilités à un objet en gardant la même interface ; Composite représente une structure arborescente partie-tout (plusieurs enfants) et traite objets simples et composés de façon uniforme ; Proxy contrôle l'accès à un objet (paresse, protection, distance) avec la même interface sans lui ajouter de fonctionnalité métier.
Inconvénients : beaucoup de petits objets, résultats dépendant de l'ordre de composition, identité des objets (le décorateur n'est pas l'objet décoré), configuration et débogage plus difficiles, impossibilité d'enlever facilement un décorateur intérieur.""",
"""ESSENTIEL : identifie le Décorateur et nomme correctement tous les rôles (composant, composant concret, décorateur, décorateurs concrets) avec le problème résolu.
ESSENTIEL : explique l'empilement des objets à l'exécution, l'appel de l'objet externe et la délégation en chaîne qui détermine l'ordre.
ESSENTIEL : compare avec les sous-classes (7 combinaisons, croissance exponentielle contre linéaire).
ESSENTIEL : distingue les intentions de Décorateur, Composite et Proxy et donne un inconvénient réel du Décorateur.""",
"Décorateur : ajout dynamique de comportements par empilement d'objets ayant la même interface. Il évite l'explosion de sous-classes mais multiplie les petits objets et rend l'ordre de composition important.",
img="pattern_mystery_fr.png", t=540)

# 20 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Le listing 1 contient deux fragments de code. Pour chacun : (1) nommez le principe SOLID violé en le justifiant par un élément précis du listing ; (2) décrivez la conséquence concrète à l'usage ou à l'évolution ; (3) proposez la correction (en prose). Pour le second fragment, relevez au moins deux principes violés.""",
"""Fragment 1 (Machine / OldPrinter) : principe de ségrégation des interfaces (ISP) : l'interface Machine est trop large (print, scan, fax) et OldPrinter doit implémenter scan et fax qu'il ne sait pas faire, en levant UnsupportedOperationException ; c'est aussi une violation du principe de substitution de Liskov : un OldPrinter n'est pas utilisable partout où une Machine est attendue sans exceptions inattendues. Conséquence : les clients découvrent l'erreur à l'exécution ; le code appelant doit faire des tests de type ; l'ajout d'une opération dans Machine impose de modifier tous les implémenteurs. Correction : scinder en interfaces spécifiques Printer, Scanner, Fax ; OldPrinter implémente seulement Printer ; une MultiFunctionMachine implémente les trois.
Fragment 2 (ReportService) : principe d'inversion des dépendances (DIP) : le service instancie directement MySqlRepository avec new : il dépend d'une classe concrète de bas niveau, impossible de changer de base ou de tester sans MySQL ; principe ouvert/fermé (OCP) : la chaîne de if sur le format oblige à modifier export à chaque nouveau format ; principe de responsabilité unique (SRP) : la même classe lit les données et gère le formatage. Correction : injecter une interface (Repository) par le constructeur ; définir une interface ReportWriter avec les implémentations PdfWriter et CsvWriter choisies par un registre ou une fabrique, export délègue ; la classe ne change pas pour un nouveau format.""",
"""ESSENTIEL : fragment 1 : ISP (interface trop large, méthodes non supportées qui lèvent une exception) avec mention de la violation de LSP, conséquence et correction par scission des interfaces.
ESSENTIEL : fragment 2 : au moins DIP (new MySqlRepository, dépendance concrète) et OCP (chaîne de if sur le format) identifiés avec l'élément précis du listing ; SRP accepté en complément.
ESSENTIEL : corrections par abstraction injectée (DIP) et stratégie par format (OCP), avec conséquences correctes.""",
"Interface trop large et méthodes non supportées : ISP et LSP. Instanciation d'une classe concrète et chaîne de conditions sur le format : DIP et OCP, et un mélange de responsabilités : SRP.",
img="solid_fr.png", t=600)

# 21 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Le listing 1 compile. Donnez la sortie exacte (quatre lignes) de la méthode main et justifiez chaque ligne avec les règles de Java sur (a) la liaison statique des champs et des méthodes statiques, (b) la résolution de la surcharge à la compilation, (c) la liaison dynamique des méthodes d'instance. Expliquez en particulier pourquoi la quatrième ligne commence par « animal » alors que l'objet est un Dog, et pourquoi elle se termine par « D.obj ».""",
"""Sortie : « animal A », « A.dog », « D.animal », « animal/D.obj ».
Ligne 1 : l'accès à un champ et l'appel d'une méthode statique sont résolus à la compilation sur le type statique (Animal) : a.name vaut « animal » (le champ name de Dog masque celui d'Animal mais n'est pas polymorphe) et a.kind() appelle Animal.kind() : « A ».
Ligne 2 : le type statique de a est Animal ; les méthodes applicables sont sound(Object) et sound(Dog) (l'argument d est un Dog) ; la plus spécifique, sound(Dog), est choisie à la compilation ; Dog ne la redéfinit pas : « A.dog ».
Ligne 3 : le type statique de d est Dog ; l'argument a a le type statique Animal ; les méthodes applicables à un Animal sont sound(Object) et sound(Animal), déclarée dans Dog ; la plus spécifique est sound(Animal) : « D.animal ».
Ligne 4 : who() est définie dans Animal : name y désigne le champ d'Animal (liaison statique) : « animal » ; dans Animal, this a le type statique Animal : seule sound(Object) est applicable, c'est elle qui est choisie à la compilation ; à l'exécution l'objet est un Dog qui redéfinit sound(Object) : liaison dynamique : « D.obj ».""",
"""ESSENTIEL : les quatre lignes exactes : animal A / A.dog / D.animal / animal/D.obj.
ESSENTIEL : explique la liaison statique des champs et des méthodes statiques (type statique), le choix de la surcharge à la compilation selon le type statique des arguments (sound(Dog) pour la ligne 2, sound(Animal) pour la ligne 3), et la liaison dynamique de sound(Object) à la ligne 4.""",
"On distingue ce qui est décidé à la compilation (champs, méthodes statiques, choix de la signature surchargée) de ce qui est décidé à l'exécution (quelle redéfinition d'une signature donnée est appelée).",
img="dispatch_fr.png", t=600)

# 22 ------------------------------------------------------------------------------------------------------------------
op("en",
"""Using the class diagram in the figure, assign each of the following responsibilities to the class that should own it. For each one, name the GRASP principle that justifies the choice (Creator, Information Expert, Low Coupling, High Cohesion, Controller, Pure Fabrication, Polymorphism...) and describe what goes wrong with the most tempting wrong allocation.

(R1) creating an order line when an item is added to an order;
(R2) computing the subtotal of a line;
(R3) computing the order total with the active promotion;
(R4) finding a product from the code typed by the cashier;
(R5) decreasing the stock when the order is confirmed.

Finally, explain why a class named OrderManager that does all of this would not be an acceptable answer.""",
"""R1: Order creates OrderLine (Creator: Order contains/aggregates its lines, has the data to initialise them and uses them closely; composition relation). Wrong: the user interface or a manager creating lines couples them to the Order internals.
R2: OrderLine computes its subtotal (Information Expert: it knows the quantity and has a link to the Product that knows the unit price; it asks Product for the price). Wrong: Order computing line subtotals digs into the lines' data (feature envy).
R3: Order computes the total (Information Expert: it owns the lines) and asks the Promotion to adjust it; with several promotion kinds, Polymorphism (a Promotion abstraction with variants) removes conditionals. Wrong: a controller or UI computing the total duplicates logic and exposes the structure.
R4: Catalog finds the Product by code (Information Expert: it aggregates the products; or a Pure Fabrication repository to keep Product free of persistence); Low Coupling. Wrong: a static find on Product, or the UI scanning products, mixes lookup with the entity.
R5: Product reduces its own stock (Information Expert: it owns the stock attribute), triggered through Order and OrderLine confirming (Low Coupling, respect of the Law of Demeter); invariants (stock >= 0) protected in one place. Wrong: Order changing product.stock directly breaks encapsulation and spreads the stock rule.
OrderManager: a 'god class' with low cohesion (several unrelated responsibilities), high coupling with all other classes, which turns the domain classes into passive data holders (anemic domain model) and goes against Information Expert, High Cohesion and Low Coupling; it is also hard to test and to extend.""",
"""ESSENTIAL: correct allocation: R1 Order (Creator), R2 OrderLine (Information Expert), R3 Order with Promotion (Information Expert, Polymorphism for promotions), R4 Catalog (Information Expert or Pure Fabrication), R5 Product (Information Expert) reached through Order/OrderLine with Low Coupling.
ESSENTIAL: for each, a relevant consequence of the wrong allocation (coupling, duplication, broken encapsulation).
ESSENTIAL: rejects OrderManager as a god class with low cohesion, high coupling and an anemic domain model.""",
"GRASP assigns each responsibility to the class that has the information or the natural relationship: Order creates and totals, OrderLine computes subtotals, Catalog finds products, Product manages stock. A manager class that does everything violates cohesion and coupling principles.",
img="grasp_en.png", t=600)

# 23 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Cahier des charges d'une agence de location de logements à Yaoundé.

Un propriétaire peut posséder plusieurs logements ; un logement appartient à un seul propriétaire. Un logement est loué à un locataire dans le cadre d'un bail, qui a une date de début, une durée en mois et un loyer mensuel. Un logement n'a qu'un bail actif à la fois mais garde l'historique de ses baux ; un locataire peut avoir plusieurs baux, sur des logements différents. Chaque bail a un ou deux garants, qui sont des personnes ; un garant peut garantir plusieurs baux. Les paiements de loyer sont enregistrés par mois et appartiennent à un bail : un paiement ne peut pas exister sans bail. Propriétaires, locataires et garants sont des personnes qui ont un nom et un téléphone, et une même personne peut être à la fois propriétaire et garant.

Décrivez avec précision le diagramme de classes du domaine : classes et attributs principaux, associations avec les multiplicités aux deux extrémités, composition ou agrégation, généralisation ou rôles, classe d'association éventuelle. Justifiez trois choix de modélisation difficiles, notamment le traitement du fait qu'une même personne peut jouer plusieurs rôles.""",
"""Classes : Personne (nom, téléphone) ; Logement (adresse, ...) ; Bail (dateDebut, dureeMois, loyerMensuel) ; Paiement (mois, montant, date).
Associations : Personne (rôle propriétaire) 1 — 0..* Logement (un logement a exactement un propriétaire, une personne peut en posséder plusieurs ou aucun) ; Logement 1 — 0..* Bail (historique des baux d'un logement ; contrainte : au plus un bail actif par logement à un instant donné) ; Personne (rôle locataire) 1 — 0..* Bail ; Bail 0..* — 1..2 Personne (rôle garant) (un bail a un ou deux garants, un garant peut garantir plusieurs baux) ; Bail composé de 0..* Paiement (composition : un paiement n'existe pas sans bail, il disparaît avec lui ; multiplicité 1 côté Bail).
Choix difficiles : 1) Propriétaire, locataire et garant ne sont pas trois sous-classes de Personne : la généralisation est une classification statique et unique, or une personne peut être à la fois propriétaire et garant (classification multiple) et ses rôles changent au cours du temps ; on modélise donc des rôles (noms de rôle sur les extrémités d'association) ou un patron rôle, avec une seule classe Personne. 2) Bail est une classe à part entière et non une classe d'association entre Logement et Personne : une classe d'association n'a qu'une instance par couple de participants, alors que le même locataire peut louer plusieurs fois le même logement (historique) et un bail a ses propres associations (garants, paiements). 3) Le paiement est lié par composition au bail (cycle de vie dépendant) ; la règle « un seul bail actif » et « le locataire n'est pas son propre garant » s'exprime par des contraintes (notes ou OCL).""",
"""ESSENTIEL : une seule classe Personne avec des rôles (propriétaire, locataire, garant) justifiée par la classification multiple/dynamique, et non trois sous-classes exclusives.
ESSENTIEL : Bail modélisé comme classe (pas classe d'association), justifié par l'historique, les baux répétés entre mêmes parties et ses propres associations.
ESSENTIEL : multiplicités correctes : propriétaire 1 — 0..* logements ; logement 1 — 0..* baux ; locataire 1 — 0..* baux ; bail avec 1..2 garants ; composition bail — 0..* paiements.
ESSENTIEL : contrainte d'un seul bail actif par logement mentionnée.""",
"Un rôle n'est pas une sous-classe quand une personne peut cumuler plusieurs rôles ; un bail porte son propre cycle de vie et n'est pas une classe d'association ; un paiement est composé dans son bail.",
t=720)

# 24 ------------------------------------------------------------------------------------------------------------------
op("en",
"""In the figure, ThesisStudent inherits from both Student and Employee, which both inherit from Person.

(1) Describe at least two distinct problems this structure causes.
(2) Explain why this model cannot be implemented as drawn in Java and in C#, and how C++ deals with it.
(3) Propose two alternative designs in UML terms (one based on interfaces and composition, one based on roles), and recommend one for a university in which people change status over time (a student becomes an employee), justifying your choice with the notion of dynamic classification.""",
"""(1) Problems: the diamond problem: ThesisStudent inherits Person twice (through Student and through Employee): without special handling there are two copies of name and id, or an ambiguity about which one is used; ambiguity of getId(): Student and Employee each override it with different meanings (student number or employee number), so a call on ThesisStudent is ambiguous and has no single meaning; semantic problem: a thesis student is one person, but the model creates one object with two identities; static classification: the class of an object is fixed at creation, so a student who becomes an employee cannot change class without creating a new object and losing identity.
(2) Java and C# only allow single inheritance of classes (they allow several interfaces): a class cannot extend two classes; default interface methods with the same signature must be resolved explicitly. C++ allows multiple inheritance of classes and solves the duplication with virtual inheritance (Person as a virtual base shares a single sub-object), the ambiguity of getId() has to be resolved by an override in ThesisStudent or by explicit qualification.
(3) Alternative A: interfaces and composition: IStudent and IEmployee interfaces; ThesisStudent implements both and delegates to composed Student and Employee components (composition over inheritance). Alternative B: roles: one Person class with 0..1 StudentRole and 0..1 EmployeeRole objects (role pattern / role classes), each carrying its own attributes and operations. Recommendation: roles, because they allow dynamic classification: roles can be added or removed at run time, the person keeps a single identity (and getId() lives on the person, with role-specific numbers in the roles), a student who becomes an employee just gains a role, and a ThesisStudent is a person who has both roles at the same time.""",
"""ESSENTIAL: at least two problems among diamond problem/duplicated Person state, ambiguity of getId(), identity of a single person, static classification.
ESSENTIAL: Java and C# forbid multiple class inheritance (interfaces allowed) and C++ allows it with virtual inheritance.
ESSENTIAL: two alternatives (interfaces + composition/delegation, and roles) and a recommendation of roles justified by dynamic classification and a single identity.""",
"Multiple inheritance creates duplication and ambiguity, and fixes the class of an object. Roles (or delegation) keep one identity and allow people to change status.",
img="diamond2_en.png", t=600)

# 25 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Le diagramme de composants montre l'assemblage de AppMobile, Boutique et PasserellePaiement.

1) L'assemblage entre Boutique et PasserellePaiement est-il valide ? Justifiez en comparant précisément les deux interfaces.
2) Que doit vérifier un test de compatibilité d'interface (test de contrat) ?
3) Proposez deux manières de réparer l'assemblage en précisant l'impact sur le diagramme (par exemple un composant adaptateur) et sur le déploiement. Laquelle recommandez-vous si quatre autres boutiques utilisent déjà l'ancienne interface IPaiement ?
4) Différenciez interface fournie et interface requise, et dites ce que change le fait que IPaiementV2 ajoute une opération et modifie la signature de payer.""",
"""1) Non, l'assemblage n'est pas valide. Le composant Boutique requiert IPaiement (payer(montant) retourne un booléen) alors que PasserellePaiement fournit IPaiementV2, un autre type : la signature de payer diffère (paramètre devise en plus) et son type de retour est différent (Reçu au lieu d'un booléen) ; de plus IPaiementV2 ajoute annuler(reçu). IPaiementV2 n'est pas un sous-type de IPaiement (il ne peut pas le remplacer : une opération de même nom mais de signature différente n'est pas une redéfinition), donc la prise ne peut pas se brancher sur la boule.
2) Un test de contrat vérifie : les noms des opérations, les types et l'ordre des paramètres, les types de retour, les préconditions et postconditions, les exceptions, le protocole d'appel (l'ordre des appels autorisés) et les propriétés non fonctionnelles convenues (délais, idempotence).
3) Réparation A : ajouter un composant adaptateur (par exemple AdaptateurPaiementV1) qui fournit IPaiement, requiert IPaiementV2 et convertit les appels (devise par défaut, conversion Reçu vers booléen) ; il est déployé avec la boutique ou avec la passerelle. Réparation B : faire évoluer Boutique pour qu'elle requière IPaiementV2 (ce qui oblige à modifier et redéployer la boutique). Réparation C (variante) : la passerelle fournit les deux interfaces (IPaiement et IPaiementV2) pendant une période de transition (versionnement). Recommandation avec quatre autres boutiques clientes de l'ancienne interface : conserver la rétrocompatibilité : la passerelle continue de fournir IPaiement (ou un adaptateur) et la nouvelle interface est ajoutée à côté ; on ne casse pas les clients existants et on migre progressivement.
4) Interface fournie : ce que le composant offre aux autres (boule) ; requise : ce dont il a besoin d'un autre composant (prise). Ajouter une opération à une interface est compatible pour les clients existants (si elle est une extension) ; modifier la signature d'une opération existante est une rupture de compatibilité : les clients existants ne compilent plus ni ne fonctionnent.""",
"""ESSENTIEL : conclut que l'assemblage est invalide en citant les différences précises (paramètre devise, type de retour Reçu contre booléen, absence de sous-typage entre IPaiementV2 et IPaiement).
ESSENTIEL : contenu d'un test de contrat (signatures, types, pré/postconditions, exceptions, protocole).
ESSENTIEL : au moins deux réparations dont un composant adaptateur ou une double interface, avec impact sur le diagramme et le déploiement, et une recommandation justifiée par les quatre boutiques existantes (rétrocompatibilité).
ESSENTIEL : définitions correctes d'interface fournie et requise et distinction entre extension compatible et modification de signature incompatible.""",
"Une prise et une boule ne s'assemblent que si les interfaces sont compatibles. Ici la signature change : il faut un adaptateur ou une passerelle qui fournit les deux versions pour ne pas casser les clients existants.",
img="comp_req_fr.png", t=540)

# 26 ------------------------------------------------------------------------------------------------------------------
op("en",
"""Explain the Model-Driven Architecture (MDA) viewpoints CIM, PIM and PSM. Then classify each of the following artifacts and justify your choice:
(a) a use case model of a hospital appointment system, written with domain experts;
(b) a UML class diagram of the same system with entities and services, and no mention of any technology;
(c) a UML model that uses a Java EE profile, with stereotypes such as Stateless and Entity and persistence annotations;
(d) the SQL DDL script for PostgreSQL generated from the model.
Describe what a model-to-model transformation and a model-to-text transformation do and give the one that applies between (b) and (c) and between (c) and (d). State two benefits and two real limits of MDA in practice.""",
"""CIM (Computation Independent Model): the business or domain view of the system and its requirements, without computing details (vocabulary, processes, use cases). PIM (Platform Independent Model): the design of the system's structure and behaviour without any platform-specific technology. PSM (Platform Specific Model): the PIM refined with the details of a target platform (Java EE, .NET, a relational DBMS...).
(a) CIM: written with domain experts, describes what the system does for its users without computational details. (b) PIM: a technology-free design model. (c) PSM: it uses a Java EE profile and persistence annotations, so it is tied to a platform. (d) The generated DDL is code/text derived from the PSM, below the PSM level (a code artifact of the platform, not a model).
Model-to-model (M2M): transforms one model into another model, possibly of another metamodel, by rules (for example QVT or ATL): applies from (b) PIM to (c) PSM. Model-to-text (M2T): generates textual artifacts (source code, DDL, configuration) from a model with templates: applies from (c) PSM to (d).
Benefits: portability and platform independence of the design; productivity and consistency of generated code; traceability from requirements to code; documentation always synchronised with the model. Limits: tool maturity and lock-in; the cost of writing and maintaining the transformations; difficulty of round-trip synchronisation between modified code and models; models can become as complex as the code; generated code that is hard to read and debug; need for skills.""",
"""ESSENTIAL: correct definitions of CIM, PIM and PSM.
ESSENTIAL: classification: (a) CIM, (b) PIM, (c) PSM, (d) generated code/text (a code artifact below the PSM), with justification.
ESSENTIAL: model-to-model (PIM to PSM) and model-to-text (PSM to code) correctly described and applied.
ESSENTIAL: two genuine benefits and two genuine limits.""",
"MDA separates requirements (CIM), platform-independent design (PIM) and platform-specific design (PSM), linked by transformations: model-to-model between models, model-to-text to produce code. It brings portability and consistency but costs tooling and transformation maintenance.",
t=480)

# 27 ------------------------------------------------------------------------------------------------------------------
op("fr",
"""Le diagramme de communication de la figure utilise une numérotation décimale.

1) Reconstruisez la séquence complète des messages dans l'ordre d'exécution, avec l'émetteur et le récepteur de chacun.
2) Quel message est émis par :Stock, à qui, et à quel moment par rapport aux autres ?
3) Quelle information un diagramme de séquence met en évidence et que ce diagramme de communication rend moins lisible ? Et inversement ?
4) Que représente le message 2.1 qui ne relie pas deux objets différents ?""",
"""1) Ordre d'exécution (numérotation décimale) : 1 ajouterLivre(isbn) du Client vers le Panier ; 1.1 chercher(isbn) du Panier vers le Catalogue ; 1.2 réserver(isbn) du Panier vers le Stock ; 1.2.1 vérifier(isbn) du Stock vers le Catalogue (imbriqué dans la réservation) ; 2 valider() du Client vers le Panier ; 2.1 calculerTotal() du Panier vers lui-même ; 2.2 émettre(panier) du Panier vers la Facturation.
2) :Stock envoie vérifier(isbn) (message 1.2.1) au Catalogue, pendant l'exécution de réserver (1.2), après 1.1 et avant la fin du message 1 et avant 2 : c'est un appel imbriqué dans 1.2 (le numéro 1.2.1 indique qu'il est déclenché par le traitement du message 1.2).
3) Un diagramme de séquence met en évidence l'ordre chronologique, les durées d'activation (barres), les retours, les fragments (alt, loop, opt) et la durée de vie des objets, que le diagramme de communication ne montre qu'à travers la numérotation. Inversement, le diagramme de communication met en évidence la structure : quels objets sont reliés, la topologie des collaborations et le contexte d'interaction (liens), moins visibles dans la séquence.
4) 2.1 est un message de l'objet à lui-même (auto-message, appel d'une opération de :Panier sur :Panier) : il est dessiné par une boucle sur l'objet, sans lien vers un autre objet.""",
"""ESSENTIEL : la séquence exacte 1, 1.1, 1.2, 1.2.1, 2, 2.1, 2.2 avec l'émetteur et le récepteur de chaque message.
ESSENTIEL : 1.2.1 vérifier(isbn) est émis par Stock vers Catalogue, imbriqué dans 1.2.
ESSENTIEL : comparaison correcte : la séquence montre le temps/l'ordre/les activations/les fragments, la communication montre la structure des liens entre objets.
ESSENTIEL : 2.1 est un auto-message du Panier.""",
"La numérotation décimale exprime l'ordre et l'imbrication des appels. Un diagramme de séquence met l'accent sur le temps, un diagramme de communication sur les liens entre objets.",
img="comm_fr.png", t=480)
