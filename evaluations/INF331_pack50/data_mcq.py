# -*- coding: utf-8 -*-
"""15 multiple-choice questions. opts[0] is ALWAYS the correct option here: build.py moves it to a balanced position and checks lengths."""
M = []
def mcq(lang, text, opts, expl, img="", t=120):
    assert len(opts) == 4
    M.append(dict(lang=lang, kind="mcq", text=text.strip("\n"), opts=opts, expl=expl.strip("\n"), img=img, t=t))

# 1  Liskov
mcq("fr",
"Le listing 1 définit Rectangle, Square (qui en hérite) et une méthode de contrôle Check.test. L'appel de test avec un Rectangle retourne vrai, avec un Square il retourne faux, alors que le compilateur accepte un Square partout où un Rectangle est attendu. Quelle analyse est exacte ?",
["Square impose l'invariant w = h : setWidth ne garantit plus que la hauteur reste inchangée, la postcondition héritée est affaiblie et la substitution est rompue",
 "Square devrait déclarer ses champs private : l'accès protected à w et h expose l'état interne et casse l'encapsulation, ce qui provoque l'échec du test",
 "Le test échoue parce que area() n'est pas redéfinie dans Square : sans redéfinition, la méthode hérite d'un calcul inadapté au cas d'un carré",
 "La redéfinition de setWidth change le comportement de la classe de base, ce que Java interdit à la compilation lorsque la sous-classe est substituée"],
"Le principe de substitution de Liskov exige qu'une sous-classe respecte le contrat de sa super-classe : après setWidth(5) puis setHeight(4), un Rectangle a une aire de 20 car setWidth ne touche pas la hauteur (postcondition). Square modifie les deux dimensions à chaque appel : il renforce l'invariant (w = h) et affaiblit la postcondition de setWidth, donc le code client écrit pour Rectangle se casse. Les autres affirmations sont fausses : l'encapsulation n'est pas la cause, area() est correcte pour un carré et Java ne vérifie pas le comportement à la compilation.",
img="lsp_fr.png", t=120)

# 2  git rebase / force push
mcq("en",
"Alice and Bob both work on the shared branch feature/search, already published on the remote. Bob rebases it onto the latest main and publishes the result with a forced push. Alice has two local commits that are not pushed yet and now runs a plain pull that merges (no rebase option). What is the most likely outcome?",
["Git merges the rewritten commits next to Alice's old copies of them, so each of Bob's changes appears twice in history and conflicts are likely",
 "Git refuses the pull, because a branch whose history was rewritten by a forced push can only be fetched after the local branch is deleted",
 "Alice's two local commits are silently discarded, because after a forced update the remote history is authoritative and replaces hers",
 "The pull fast-forwards cleanly, because Git recognises the rebased commits by their patch content and skips the duplicates automatically"],
"A rebase creates new commits (new parents, hence new hashes) with the same changes. Alice's branch still contains the old versions of Bob's commits, the remote now holds the rewritten ones: a merge pull joins both histories, so the same changes appear twice and often conflict. Git does not refuse, does not discard local commits and does not deduplicate on a merge (only a pull with rebase can skip patch-equivalent commits). Rule: never rewrite shared history, or use a lease-protected force and tell the team.",
t=100)

# 3  object vs class diagram (one violation)
mcq("fr",
"La figure montre un diagramme de classes et un diagramme d'objets censé en être une instance. Une seule affirmation sur ce diagramme d'objets est exacte. Laquelle ?",
["L'objet e2 est lié à deux départements alors que la multiplicité côté Département de l'association « emploie » impose exactement un département",
 "L'objet c2 est enseigné par deux enseignants alors que la multiplicité 1..2 portée par Cours interdit qu'un cours soit assuré par plus d'un enseignant",
 "Les départements d1 et d2 sont rattachés à la même université alors que la composition interdit qu'une université compose plus d'un département",
 "L'objet e1 n'enseigne qu'un seul cours alors que la multiplicité 1..2 portée par Cours exige que chaque enseignant en assure exactement deux"],
"Une multiplicité écrite à côté d'une classe donne le nombre d'instances de cette classe liées à une instance de l'autre extrémité. Département porte « 1 » côté « emploie » : chaque enseignant a exactement un département, or e2 est lié à d1 et à d2. Les autres objets respectent le diagramme : 1..* départements par université, 1..* enseignants par cours (c2 : e2 et e3), et 1..2 cours par enseignant (e1 : un cours, e2 : deux, e3 : un).",
img="objclass_fr.png", t=140)

# 4  sequence diagram trace
mcq("en",
"In the sequence diagram, the scenario written in the note is executed once. Count every message sent by an arrow in the diagram (calls only, not returns) while the scenario runs. How many messages are sent?",
["Five messages: checkout, two reserve, one notifyBackorder and one charge, because charge sits outside the loop and is always sent",
 "Four messages: checkout, two reserve and one notifyBackorder, because charge is skipped when at least one item is on backorder",
 "Six messages: checkout, three reserve, one notifyBackorder and one charge, because every item triggers a reservation attempt first",
 "Seven messages: checkout, two reserve, two notifyBackorder and two charge, because the alternative runs once per item and per branch"],
"The loop runs once for each of the 3 items. In the alt fragment, the first operand runs for the 2 items in stock (reserve) and the else operand runs for the item out of stock (notifyBackorder). charge is after the loop and has no guard, so it is sent once. Total: 1 + 2 + 1 + 1 = 5.",
img="seq_cart_en.png", t=100)

# 5  boundary values
mcq("fr",
"Une fonction accepte un âge entier valide dans l'intervalle [18 ; 60[ (18 inclus, 60 exclu). On applique l'analyse des valeurs limites à trois valeurs par borne : la valeur limite, la valeur juste en dessous et la valeur juste au-dessus. Quel jeu de test est correct ?",
["17, 18, 19, 58, 59, 60",
 "17, 18, 19, 59, 60, 61",
 "18, 19, 20, 57, 58, 59",
 "16, 17, 18, 59, 60, 61"],
"L'intervalle [18 ; 60[ contient les entiers 18 à 59. La borne basse valide est 18 : on teste 17, 18 et 19. La borne haute valide est 59 : on teste 58, 59 et 60 (60 est la première valeur invalide). Le jeu « 17, 18, 19, 59, 60, 61 » correspond à un intervalle fermé [18 ; 60], celui de « 18, 19, 20, 57, 58, 59 » ne teste aucune valeur invalide et le dernier est décalé d'un cran.",
t=100)

# 6  deep history
mcq("fr",
"Le système est éteint. Il reçoit successivement les événements power, wake, pause, alarm puis resume. Selon le diagramme d'états, dans quel état simple (feuille) se trouve-t-il juste après resume ?",
["Pause, car la transition resume entre par l'historique profond H* qui restaure la dernière configuration active, même imbriquée",
 "Lecture, car resume entre dans l'état Actif par son pseudo-état initial et active donc le sous-état par défaut de ce dernier",
 "Veille, car resume réactive l'état composite Allumé depuis son pseudo-état initial, comme après un événement power ordinaire",
 "Maintenance, car resume est ignoré tant que l'événement alarm n'a pas été acquitté explicitement dans l'état courant"],
"power : Éteint → Allumé (Veille). wake : Veille → Actif (Lecture, état initial). pause : Lecture → Pause. alarm : on quitte Allumé vers Maintenance, la configuration active (Allumé / Actif / Pause) est mémorisée. resume entre dans Allumé par H*, l'historique profond, qui rétablit toute la configuration imbriquée : Pause. Un historique superficiel (H) aurait restauré Actif puis son état initial Lecture.",
img="state_hist_fr.png", t=140)

# 7  use case relations
mcq("en",
"Look at the use case diagram of the online shop. Which statement is correct?",
["Place order cannot complete without Authenticate, whereas Apply coupon may be skipped without changing the basic flow of Place order",
 "Apply coupon always runs after Place order, because an extend arrow points from the base use case towards the extension it triggers",
 "Authenticate is optional, since an include relationship is only followed when a guard condition holds on the base use case",
 "Pay by card and Pay by mobile money both run on every Pay, because a specialisation chains the child use cases one after the other"],
"«include» is mandatory: the included behaviour (Authenticate, Pay) is part of Place order and always executed, so Place order cannot complete without it. «extend» is optional: the arrow goes from the extension (Apply coupon) to the base use case (Place order), the extension may be inserted at an extension point under a condition and the base flow stays complete without it. A generalisation means that Pay by card and Pay by mobile money are two alternative ways to Pay, not a sequence.",
img="usecase_shop_en.png", t=110)

# 8  Scrum
mcq("fr",
"À la fin d'un sprint de deux semaines, une user story estimée à 8 points est réalisée à 90 % : le code est écrit mais les tests d'acceptation ne passent pas encore. Selon le guide Scrum, que faut-il faire de cette story ?",
["La laisser hors de l'incrément et la remettre dans le product backlog, où le Product Owner la réordonne et l'équipe la ré-estime",
 "La compter pour 90 % dans la vélocité du sprint, puis la reporter au sprint suivant avec les 10 % restants estimés à un point",
 "Prolonger le sprint de quelques jours jusqu'à satisfaire la Definition of Done, car la durée d'un sprint reste négociable avec le client",
 "La livrer dans l'incrément avec le statut « presque terminé », puisque l'incrément contient le travail déjà intégré au produit"],
"Un élément du backlog ne fait partie de l'incrément que s'il respecte la Definition of Done ; sinon il n'est pas livrable et retourne dans le product backlog pour être réordonné par le Product Owner et ré-estimé par l'équipe. Il n'y a pas de crédit partiel en vélocité, la durée d'un sprint est fixe (on ne la prolonge pas) et l'incrément ne peut pas contenir de travail inachevé.",
t=110)

# 9  association class multiplicities
mcq("en",
"Read the multiplicities in the standard way. In a valid system that contains exactly one Student, what are the minimum and the maximum number of Enrollment objects?",
["Between 3 and 30, because the student must take 3 to 30 courses and each link between this student and a course carries its own Enrollment",
 "Between 1 and 5, because the student must take 1 to 5 courses and each link between this student and a course carries its own Enrollment",
 "Between 3 and 5, because a course holds 3 to 30 students while this student is limited to at most 5 courses of the whole catalogue",
 "Between 1 and 30, because the association class only exists once the student has taken a course and is capped by the course side"],
"A multiplicity written next to a class counts the instances of that class linked to one instance of the other end: 3..30 next to Course means that a student takes between 3 and 30 courses, and 1..5 next to Student means that a course has 1 to 5 students. An association class has exactly one instance per link, so a lone student has between 3 and 30 Enrollment objects. (With one student, each of his courses has 1 student: the 1..5 side is respected.)",
img="assoc_enroll_en.png", t=100)

# 10  initialisation order
mcq("en",
"What does the program in Listing 1 print when main runs? (Each print ends with a space; ignore the trailing space.)",
["Bnull0 Dready7",
 "Bready7 Dready7",
 "Bbase Dready7",
 "Bnull7 Dready7"],
"new Derived() first runs the Base constructor (after super() and before the field initialisers of Derived). Base calls describe(), which is overridden: it runs on the Derived object whose fields are not initialised yet, so tag is null and n is 0 : describe() returns \"null0\". Then the initialisers of Derived run (tag = \"ready\"), the constructor body sets n = 7 and the second print shows \"ready7\". The program prints Bnull0 Dready7.",
img="init_en.png", t=110)

# 11  coupling types
mcq("en",
"Module A calls a function of module B and passes a boolean parameter whose value decides which internal branch B executes. B also reads a global configuration table that A updates before each call. Which pair of coupling types describes this relationship best?",
["Control coupling through the flag parameter, plus common coupling through the shared global configuration table",
 "Stamp coupling through the flag parameter, plus content coupling through the shared global configuration table",
 "Data coupling through the flag parameter, plus external coupling through the shared global configuration table",
 "Control coupling through the flag parameter, plus content coupling through the shared global configuration table"],
"Passing a flag that controls the internal logic of the called module is control coupling (B must know how A wants it to behave). Several modules sharing a global data area is common coupling. Content coupling would mean that A reaches into B's internals (modifies its private data or jumps into its code), stamp coupling means sharing a composite structure of which only a part is used, data coupling passes only the needed elementary data, and external coupling concerns a shared external format or device.",
t=110)

# 12  coverage criteria
mcq("fr",
"Le listing 1 présente une fonction et un jeu de deux tests, T1 (huit ans, non membre) et T2 (trente ans, membre). Quelle affirmation sur la couverture obtenue par ces deux tests est exacte ?",
["Ils satisfont la couverture des décisions (branches), mais pas celle des conditions : age >= 12 n'est jamais évalué à faux dans la ligne 4",
 "Ils satisfont la couverture des conditions et des décisions, car chaque condition atomique de la fonction reçoit les deux valeurs de vérité",
 "Ils ne satisfont que la couverture des instructions, car la décision de la ligne 4 n'est jamais évaluée à faux par l'un des deux tests",
 "Ils satisfont le critère MC/DC, car chaque condition de la ligne 4 est vue modifiant à elle seule le résultat de la décision"],
"Instructions : T1 exécute la ligne 3, T2 la ligne 4 et la décrémentation : tout est couvert. Décisions : la ligne 3 est vraie avec T1 et fausse avec T2 ; la ligne 4 est fausse avec T1 (membre = faux) et vraie avec T2 : 100 % des branches. Conditions : avec T1, membre vaut faux et l'évaluation court-circuite, donc age >= 12 n'est jamais évalué à faux (et n'est évalué que par T2, à vrai) : la couverture des conditions n'est pas atteinte, et a fortiori pas MC/DC.",
img="cover_fr.png", t=120)

# 13  activity diagram join
mcq("en",
"The activity diagram is executed for a standard (non-express) order. What happens?",
["The flow blocks at the join, which waits for a token on each of its three incoming edges, so Ship order is never reached",
 "Both courier and postal are booked, because the fork duplicates the token and the decision node only filters one of the copies",
 "The order ships as soon as the postal booking is done, because a join fires when any one of its incoming edges holds a token",
 "The order ships once after the invoice and the postal booking, because a join only waits for the edges that can still produce a token"],
"The fork creates two tokens. One goes to Prepare invoice. The other reaches the decision node, which sends it to exactly one of the two branches (Book postal here). The join bar has three incoming edges and, in UML, a join fires only when a token is available on every incoming edge: the edge coming from Book courier never receives one, so the join waits forever and Ship order is never executed (a deadlock). The fix is a merge node that brings the two alternative branches back into one edge before the join.",
img="act_fork_en.png", t=120)

# 14  technical debt / refactoring
mcq("fr",
"Quelle affirmation sur la dette technique et le refactoring est exacte ?",
["Le refactoring modifie la structure interne sans changer le comportement observable ; il n'est sûr que s'il s'appuie sur des tests automatisés",
 "Le refactoring consiste à ajouter des fonctionnalités en réorganisant le code, et la dette technique ne désigne que les défauts encore ouverts",
 "La dette technique disparaît dès que le code passe tous ses tests, car elle mesure le nombre de tests manquants plutôt que la conception",
 "Un refactoring est sûr dès que le code recompile, puisque le compilateur garantit la conservation du comportement observable du programme"],
"Un refactoring (Fowler) est une transformation de la structure interne qui préserve le comportement observable ; ce sont les tests automatisés qui permettent de le vérifier, la compilation ne prouve rien sur le comportement. Il n'ajoute pas de fonctionnalités. La dette technique est le coût futur des choix de conception ou de code rapides ou dégradés : elle ne se mesure pas au nombre de défauts ouverts ni de tests, et elle ne disparaît pas parce que les tests passent.",
t=100)

# 15  package cycle
mcq("en",
"In the package diagram, domain.billing and domain.shipping depend on each other. domain.billing changes often, so domain.shipping must stop depending on it. Without merging packages and without creating a third package, which change removes the cycle?",
["Declare an interface in domain.shipping that Invoice implements, so that shipping uses its own abstraction and billing now depends on shipping",
 "Declare an interface in domain.billing that RateTable implements, so that billing uses its own abstraction while shipping keeps depending on billing",
 "Replace the two dependency arrows by a single bidirectional association, so that the modelling tool no longer reports the packages as a cycle",
 "Move Invoice into domain.shipping and RateTable into domain.billing, so that each package keeps one dependency on the other package"],
"The goal is to invert the dependency that goes from shipping to billing (Dependency Inversion + Stable Dependencies): shipping defines the abstraction it needs (for instance an interface that gives the amount to ship), Invoice implements it, so the arrow now goes from billing to shipping and the cycle disappears. The second option inverts the wrong arrow (shipping would still depend on billing). A bidirectional association hides nothing and keeps the cycle. Swapping the classes only moves the cycle.",
img="pkg_cycle_en.png", t=120)

# Options rewritten so that the four options of a question have almost the same length and the right one is never the longest or the shortest
OVERRIDE = {
 1: ["Square impose l'invariant w = h : setWidth ne garantit plus que la hauteur reste inchangée, donc la postcondition héritée s'affaiblit",
     "Square devrait déclarer ses champs private : l'accès protected à w et h expose l'état interne et casse l'encapsulation du comportement",
     "Le test échoue parce que area() n'est pas redéfinie dans Square : sans redéfinition, la méthode hérite d'un calcul inadapté à un carré",
     "La redéfinition de setWidth change le comportement de la classe de base, ce que Java interdit à la compilation pour une sous-classe"],
 2: ["Git merges the rewritten commits next to Alice's old copies of them, so each of Bob's changes shows up twice in history and conflicts are likely",
     "Git refuses the pull, because a branch whose history was rewritten by a forced push can only be fetched after the local branch has been deleted",
     "Alice's two local commits are silently discarded, because after a forced update the remote history is authoritative and replaces her own history",
     "The pull fast-forwards cleanly, because Git recognises the rebased commits by their patch content and skips all the duplicates automatically"],
 3: ["L'objet e2 est lié à deux départements alors que la multiplicité côté Département de l'association « emploie » impose exactement un département",
     "L'objet c2 est enseigné par deux enseignants alors que la multiplicité 1..2 portée par Cours interdit qu'un cours ait plusieurs enseignants",
     "Les départements d1 et d2 sont rattachés à la même université alors que la composition interdit qu'une université compose plusieurs départements",
     "L'objet e1 n'enseigne qu'un seul cours alors que la multiplicité 1..2 portée par Cours exige que chaque enseignant en assure exactement deux"],
 6: ["Pause, car resume entre par l'historique profond H* qui restaure la dernière configuration active, même imbriquée dans un état composite",
     "Lecture, car resume entre dans l'état Actif par son pseudo-état initial et active donc le sous-état par défaut de ce dernier état composite",
     "Veille, car resume réactive l'état composite Allumé depuis son pseudo-état initial, comme après un événement power depuis l'état Éteint",
     "Maintenance, car resume est ignoré tant que l'événement alarm n'a pas été acquitté explicitement dans l'état courant par le système"],
 7: ["Place order cannot complete without Authenticate, whereas Apply coupon may be skipped without changing the basic flow of Place order",
     "Apply coupon always runs after Place order, because an extend arrow points from the base use case towards the extension it triggers",
     "Authenticate is optional, since an include relationship is only followed when a guard condition holds on the base use case concerned",
     "Pay by card and Pay by mobile money both run on every Pay, because a specialisation chains the child use cases one after the other"],
 8: ["La laisser hors de l'incrément et la remettre dans le product backlog, où le Product Owner la réordonne et l'équipe la ré-estime ensuite",
     "La compter pour 90 % dans la vélocité du sprint, puis la reporter au sprint suivant avec les 10 % restants estimés à un seul point",
     "Prolonger le sprint de quelques jours jusqu'à satisfaire la Definition of Done, car la durée d'un sprint reste négociable avec le client",
     "La livrer dans l'incrément avec le statut « presque terminé », puisque l'incrément contient le travail déjà intégré à la version cible"],
 9: ["Between 3 and 30, because the student must take 3 to 30 courses and each link between this student and a course carries one Enrollment",
     "Between 1 and 5, because the student must take 1 to 5 courses and each link between this student and a course carries one Enrollment",
     "Between 3 and 5, because a course holds 3 to 30 students while this student is limited to at most 5 courses of the whole catalogue now",
     "Between 1 and 30, because the association class only exists once the student has taken a course and is capped by the course side too"],
 12: ["Ils satisfont la couverture des décisions (branches), mais pas celle des conditions : age >= 12 n'est jamais évalué à faux à la ligne 4",
      "Ils satisfont la couverture des conditions et des décisions, car chaque condition atomique de la fonction reçoit les deux valeurs de vérité",
      "Ils ne satisfont que la couverture des instructions, car la décision de la ligne 4 n'est jamais évaluée à faux par aucun des deux tests",
      "Ils satisfont le critère MC/DC, car chaque condition de la ligne 4 est vue modifiant à elle seule le résultat de la décision entière"],
 13: ["The flow blocks at the join, which waits for a token on each of its three incoming edges, so Ship order is never reached by the flow",
      "Both courier and postal are booked, because the fork duplicates the token and the decision node only filters one of the two copies",
      "The order ships as soon as the postal booking is done, because a join fires when any one of its incoming edges holds a token at all",
      "The order ships once after the invoice and the postal booking, because a join only waits for the edges that can still produce a token"],
 15: ["Declare an interface in domain.shipping that Invoice implements, so that shipping uses its own abstraction and billing now depends on shipping",
      "Declare an interface in domain.billing that RateTable implements, so that billing uses its own abstraction while shipping keeps depending on billing",
      "Replace the two dependency arrows by one bidirectional association, so that the modelling tool no longer reports the packages as a cycle at all",
      "Move Invoice into domain.shipping and RateTable into domain.billing, so that each package keeps one dependency on the other package as before"],
}
for _i, _o in OVERRIDE.items():
    M[_i - 1]['opts'] = _o
