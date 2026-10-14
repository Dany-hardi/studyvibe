# -*- coding: utf-8 -*-
"""INF331 (Approche OO pour la modélisation des SI): evaluation bank, 65 hard questions, French and English.
Each entry: lang, kind (mcq|tf|written), question, options (4 for mcq, 2 for tf, none for written), answer, explanation, image, seconds.
Every Java snippet used here was compiled and run (see java_checks/): the outputs quoted below are real."""

Q = []
def add(lang, kind, text, opts, ans, expl, img="", t=90):
    Q.append(dict(lang=lang, kind=kind, text=text.strip("\n"), opts=opts, ans=ans, expl=expl.strip("\n"), img=img, t=t))

SEE_FR, SEE_EN = " (voir la figure)", " (see the figure)"

# 1
add("fr", "mcq",
"Une équipe projet teste son logiciel complet, en conditions quasi réelles, mais uniquement entre ses propres membres. Le client n'a encore rien manipulé. Quel niveau de test, d'après le cours, reste à réaliser avant la livraison pour s'assurer que l'application répond réellement au besoin ?",
["Le test unitaire, composant par composant",
 "Le test d'intégration, par assemblage progressif des modules",
 "Le test de validation (test bêta), chez le client, par des utilisateurs sélectionnés",
 "Le test système (test alpha), par l'équipe elle-même"],
"C",
"Le test système (alpha) est un test en grandeur nature du système complet par l'équipe : il est déjà fait ici. Ce qui manque est le test de validation (bêta), réalisé chez le client par des utilisateurs sélectionnés. Les tests unitaires et d'intégration sont plus bas dans le V (composants isolés, puis assemblage).", t=60)

# 2
add("en", "mcq",
"""
class P {
    String name = "P";
    static String s() { return "Ps"; }
    String m(Object o) { return "P.obj"; }
    String m(String s) { return "P.str"; }
}
class C extends P {
    String name = "C";
    static String s() { return "Cs"; }
    String m(Object o) { return "C.obj"; }
}
P p = new C();
Object o = "x";
System.out.println(p.name + "|" + p.s() + "|" + p.m(o) + "|" + p.m("x"));

What is printed?""",
["C|Cs|C.obj|P.str", "P|Ps|C.obj|P.str", "P|Ps|P.obj|P.str", "C|Cs|C.obj|C.obj"],
"B",
"Fields and static methods are bound to the static type (P): p.name is \"P\" and p.s() is \"Ps\". For overloaded methods the overload is chosen at compile time from the static argument types: p.m(o) selects m(Object), then dynamic binding runs the override C.m(Object) = \"C.obj\"; p.m(\"x\") selects m(String), which C does not override, so \"P.str\". Real output: P|Ps|C.obj|P.str", t=150)

# 3
add("fr", "mcq",
"Ce diagramme de classes est censé montrer que Livre, Opéra et Film sont des spécialisations d'Œuvre. Quelle est l'erreur de notation ?" + SEE_FR,
["Œuvre devrait être soulignée car c'est une classe de base",
 "La flèche de généralisation de Film est inversée : le triangle creux doit être du côté de la super-classe Œuvre",
 "Les attributs d'Œuvre ne devraient pas être privés",
 "Les trois sous-classes devraient être reliées par une composition"],
"B",
"En UML la généralisation se note par un trait plein terminé par un triangle creux placé du côté de la super-classe (la classe mère). Pour Livre et Opéra le triangle est bien sur Œuvre ; pour Film il a été dessiné du côté de Film, ce qui inverserait le sens de l'héritage.", img="oeuvre_fr.png", t=60)

# 4
add("fr", "mcq",
"D'après la classification donnée dans le cours, quel ensemble ne contient QUE des vues statiques (structurelles) d'UML ?",
["Cas d'utilisation, classes, objets, composants, déploiement",
 "Classes, objets, états-transitions, composants",
 "Classes, objets, composants, déploiement, séquence",
 "Cas d'utilisation, classes, activités, déploiement"],
"A",
"Le cours range parmi les vues statiques ou structurelles : cas d'utilisation, classes, objets, composants, déploiement. Les vues dynamiques sont : états-transitions, activités et interactions (communication, séquence). Les trois autres propositions mélangent des diagrammes statiques et dynamiques.", t=60)

# 5
add("en", "written",
"""
class A {
    A() { System.out.print("A"); init(); }
    void init() { System.out.print("a"); }
}
class B extends A {
    int x = 5;
    B() { System.out.print("B"); }
    void init() { System.out.print(x); }
}
new B();

What exactly is printed? (type the characters with no spaces)""",
None, "A0B",
"The B constructor first calls the A constructor, which prints A and then calls init(). Because of dynamic binding this runs B.init(), but B's field initialiser (x = 5) has not run yet, so x still has its default value 0: it prints 0. Then the body of the B constructor prints B. Real output: A0B", t=120)

# 6
add("en", "mcq",
"The diagram models a composition between Hotel and Room. Which UML rule does it violate?" + SEE_EN,
["The diamond must be white for a composition",
 "The multiplicity on the composite (Hotel) side must not be greater than 1: here a room would belong to 2..* hotels",
 "The part side of a composition cannot have a lower bound of 1",
 "A class involved in a composition cannot have any other association (Guest)"],
"B",
"A composition means the part belongs to at most one composite and is destroyed with it, so the multiplicity on the composite side must be 1 or 0..1. Reading the figure, the end next to Hotel is 2..*, i.e. each Room would be part of at least two Hotels: impossible. The black diamond is correct, and Guest has nothing to do with the rule.", img="hotel_en.png", t=75)

# 7 (true/false)
add("fr", "tf",
"Vrai ou faux : lors du recueil de besoins, le MOE s'intéresse au seul comportement externe du logiciel (boîte noire), tandis que le MOA essaie de comprendre ce que fera cette boîte noire.",
["Vrai", "Faux"], "B",
"Faux : c'est l'inverse. Le MOA (maîtrise d'ouvrage, le client) s'intéresse au seul comportement externe, la boîte noire ; le MOE (maîtrise d'œuvre, l'équipe qui réalise) essaie de comprendre ce que fera cette boîte noire.", t=45)

# 8
add("en", "mcq",
"A Java class declares, in this order: int a; private int b; protected int c; public int d; static int e;  Which UML attribute list matches (UML underlines static members and uses ~ for package visibility)?",
["~ a : int, - b : int, # c : int, + d : int, ~ e : int (underlined)",
 "+ a : int, - b : int, # c : int, + d : int, + e : int (underlined)",
 "- a : int, - b : int, # c : int, + d : int, - e : int (underlined)",
 "~ a : int, - b : int, ~ c : int, + d : int, ~ e : int (not underlined)"],
"A",
"With no modifier Java gives package visibility, written ~ in UML (not + and not -). private is -, protected is #, public is +. A static member is a class attribute and is always underlined, so e is ~ e : int, underlined.", t=75)

# 9
add("fr", "written",
"Dans ce cycle en V, chaque phase de la branche descendante doit être confrontée au niveau de test de la branche montante qui la vérifie. Combien de couples (phase en face de son test) sont incorrects ?" + SEE_FR,
None, "2",
"Dans le cours : Analyse des besoins et faisabilité en face de Recette ; Spécifications en face de Tests de validation ; Conception architecturale en face de Tests d'intégration ; Conception détaillée en face de Tests unitaires. Sur la figure, les tests de validation et d'intégration ont été permutés : Spécifications en face d'Intégration et Architecturale en face de Validation sont faux. Les deux autres couples sont corrects : 2 erreurs.", img="vcycle_fr.png", t=90)

# 10
add("fr", "mcq",
"""
class Etudiant {
    private final int[] notes;
    Etudiant(int[] n) { this.notes = n; }
    int[] getNotes() { return notes; }
    double moyenne() { /* moyenne arithmétique de notes */ }
}
int[] n = {10, 12};
Etudiant e = new Etudiant(n);
System.out.print(e.moyenne() + " ");
n[0] = 0;
e.getNotes()[1] = 20;
System.out.println(e.moyenne());

Que s'affiche-t-il, et que révèle ce résultat ?""",
["11.0 11.0 : private final protège le contenu du tableau, l'encapsulation est respectée",
 "11.0 10.0 : l'encapsulation est violée, car le constructeur et l'accesseur exposent la référence du tableau interne (il faudrait des copies défensives)",
 "11.0 10.0 : comportement normal de final, qui rend le tableau immuable",
 "11.0 puis ArrayIndexOutOfBoundsException : un tableau final ne peut pas être modifié"],
"B",
"final empêche seulement de réaffecter la référence, pas de modifier le contenu du tableau. Le constructeur garde l'alias de n et getNotes() renvoie la référence interne : le code extérieur modifie l'état de l'objet sans passer par ses méthodes. (11.0 puis 10.0, résultat réel.) Encapsuler, c'est ne laisser l'état modifiable que par l'interface : copie défensive à l'entrée et à la sortie.", t=120)

# 11
add("en", "written",
"In this ATM use case diagram, 'Withdraw cash' is linked to 'Authenticate' and to 'Print receipt'. Authentication is mandatory at every withdrawal; the receipt is only printed if the customer asks for one. How many of the two dashed relationships between use cases are wrong (wrong stereotype or wrong direction)? Answer with a number." + SEE_EN,
None, "2",
"Authentication is part of every withdrawal: «include», drawn from Withdraw cash to Authenticate (A includes B). The optional receipt is an extension: «extend», drawn from Print receipt towards Withdraw cash (the extending use case points to the extended one), usually with a condition note. The diagram has «extend» on the mandatory one and «include» on the optional one, with the optional one in the wrong direction: both relationships are wrong.", img="dab_en.png", t=90)

# 12
add("fr", "mcq",
"Dans un diagramme d'états-transitions, une transition porte l'étiquette  [T > 25] / allumer_voyant()  sans événement déclencheur explicite. Que se passe-t-il ?",
["Elle ne sera jamais franchie : une transition doit toujours avoir un événement déclencheur",
 "C'est une transition d'achèvement : la garde est évaluée quand l'activité de l'état source s'achève ; si T > 25 est vrai, le voyant est allumé et l'objet change d'état",
 "C'est une transition interne : elle ne change jamais l'état actif",
 "La garde est évaluée en permanence : dès que T dépasse 25 la transition part, comme pour un événement de changement"],
"B",
"Une transition sans événement déclencheur explicite est une transition d'achèvement : elle se déclenche à la fin de l'activité de l'état source, et sa garde est évaluée à ce moment-là. Si la garde est vraie, l'effet (/ allumer_voyant()) s'exécute et l'état cible devient actif. Une transition interne n'a pas d'état cible ; un événement de changement doit être écrit explicitement.", t=90)

# 13
add("en", "written",
"""
class X {
    static { System.out.print("S"); }
    { System.out.print("I"); }
    X() { System.out.print("C"); }
}
new X();
new X();

What is printed? (no spaces)""",
None, "SICIC",
"The static initialiser runs once, when the class is first used: S. For each new X(), the instance initialiser block runs before the constructor body: I then C. Two objects: S, IC, IC. Real output: SICIC", t=90)

# 14
add("fr", "written",
"Le diagramme de classes (haut) dit : une entreprise emploie au moins deux personnes ; une personne travaille dans au plus deux entreprises. Le diagramme d'objets (bas) en est un instantané. Quels objets violent le modèle ? Donnez leurs noms séparés par une virgule, dans l'ordre alphabétique (exemple de format : a1,b2)." + SEE_FR,
None, "e3,p6|p6,e3",
"Il faut vérifier chaque objet. e3 n'est lié qu'à p3 : une seule personne alors qu'il en faut au moins 2. p6 est lié à e1, e2 et e4 : trois entreprises alors qu'une personne en a au plus 2. Les autres respectent les bornes : e1 {p1,p2,p6}, e2 {p2,p3,p6}, e4 {p4,p5,p6} ; p1, p2, p3, p4, p5 ont 1 ou 2 entreprises.", img="objets_fr.png", t=120)

# 15
add("en", "mcq",
"""
class Rect {
    protected int w, h;
    void setW(int w) { this.w = w; }
    void setH(int h) { this.h = h; }
    int area() { return w * h; }
}
class Square extends Rect {
    void setW(int w) { this.w = w; this.h = w; }
    void setH(int h) { this.w = h; this.h = h; }
}
Rect r = new Square();
r.setW(5); r.setH(4);
System.out.println(r.area());

What is printed, and which design principle is violated?""",
["20: no violation, Square is a correct specialisation of Rect",
 "16: the Liskov Substitution Principle, because a Square cannot replace a Rect without breaking the contract of setW/setH",
 "16: the Open/Closed Principle, because Rect should be abstract",
 "20: the Single Responsibility Principle"],
"B",
"A Square forces w = h, so after setW(5) and setH(4) both sides are 4 and area() returns 16 (real output), whereas client code written for a Rect expects 5 x 4 = 20. Code that works with Rect breaks when given a Square: this is the classic violation of the Liskov Substitution Principle (an \"is-a\" in geometry is not an \"is-a\" in behaviour).", t=90)

# 16 (true/false)
add("en", "tf",
"True or false: according to the course, the acceptance test belongs to the development phase (it is its last activity), not to post-development.",
["True", "False"], "A",
"True. The life cycle presented says that development \"covers all the activities from the analysis of needs to the acceptance test\". Post-development is installation, operation, maintenance and support, and retirement; pre-development is about why the software is needed, resources, deadlines and planning.", t=45)

# 17
add("fr", "mcq",
"Facture et Abonnement sont censées réaliser l'interface Payable. Que pensez-vous de la notation de ce diagramme ?" + SEE_FR,
["Les deux relations sont correctes",
 "Celle de Facture est incorrecte : un trait plein avec triangle creux note une généralisation ; la réalisation d'une interface se note par un trait discontinu terminé par un triangle creux («realize»)",
 "Celle d'Abonnement est incorrecte : une réalisation doit être dessinée en trait plein",
 "Les deux sont incorrectes : une interface se relie à ses classes par une association"],
"B",
"La réalisation d'une interface (stéréotype «realize») est un trait discontinu terminé par une flèche triangulaire creuse. Un trait plein avec triangle creux est la généralisation (héritage entre classes). Abonnement est donc correct, Facture est dessinée comme si elle héritait de Payable.", img="pay_fr.png", t=75)

# 18
add("en", "mcq",
"""
interface A { default String hi() { return "A"; } }
interface B { default String hi() { return "B"; } }

Which statement about a class C that implements both A and B is true?""",
["class C implements A, B { } compiles and C.hi() returns \"A\" (the first interface wins)",
 "class C implements A, B { } does not compile: C must override hi(), for instance with { return A.super.hi() + B.super.hi(); }, which returns \"AB\"",
 "class C implements A, B { } compiles and C.hi() returns \"AB\" automatically",
 "Java forbids a class from implementing two interfaces that declare a method with the same name"],
"B",
"Two unrelated default methods with the same signature create a conflict that the compiler does not resolve for you: class C must override hi(). Inside the override, A.super.hi() and B.super.hi() select each default; the combination returns \"AB\" (checked with javac and java). Implementing several interfaces is allowed.", t=90)

# 19
add("fr", "written",
"Dans un modèle, chaque entreprise emploie au moins 2 personnes et chaque personne travaille dans au plus 2 entreprises. Quel est le nombre minimal de personnes nécessaires pour que 3 entreprises existent en respectant ces contraintes ?",
None, "3",
"Il faut au moins 3 x 2 = 6 liens (emploi). Chaque personne en fournit au plus 2, donc au moins 6 / 2 = 3 personnes. Trois suffisent : p1 dans E1 et E2, p2 dans E2 et E3, p3 dans E3 et E1 : chaque entreprise a bien 2 personnes et chaque personne 2 entreprises.", t=90)

# 20
add("en", "mcq",
"Look at the transitions leaving the state Borrowed on the event tick. What is wrong with this state machine?" + SEE_EN,
["Nothing: a state may have several outgoing transitions with the same trigger",
 "When days = 15 both guards are true; transitions triggered by the same event from the same state need mutually exclusive guards, otherwise the behaviour is non-deterministic",
 "The transitions to Reminded and Overdue should be internal transitions",
 "The guards must be written after the slash, as effects"],
"B",
"The same event can trigger several transitions leaving a state, but then each one must have a condition and the conditions must exclude each other. With [days >= 10] and [days > 14], a tick at 15 days enables both Reminded and Overdue: nothing says which one fires. Fix: [days >= 10 and days <= 14] and [days > 14].", img="dvd_en.png", t=90)

# 21
add("fr", "mcq",
"Œuvre (titre, auteur) est spécialisée en Livre, Opéra et Film. Un développeur ajoute l'attribut nbPages directement dans Œuvre « pour factoriser ». Quelle critique est correcte ?",
["Aucune : plus un attribut est haut dans la hiérarchie, mieux c'est pour la réutilisation",
 "La généralisation ne doit regrouper que les caractéristiques communes à toutes les sous-classes ; nbPages ne concerne ni Opéra ni Film, il appartient à Livre (spécialisation)",
 "nbPages doit être déclaré static, puisqu'il est partagé par toutes les œuvres",
 "Il faut remplacer l'héritage par une composition, car Film n'a pas de pages"],
"B",
"Généraliser, c'est factoriser les caractéristiques communes à un ensemble de classes ; spécialiser, c'est ajouter des caractéristiques propres à une sous-classe. Mettre nbPages dans Œuvre l'imposerait à Opéra et Film, où il n'a pas de sens. Il doit être déclaré dans Livre. static et composition n'ont rien à voir avec le problème.", t=75)

# 22
add("en", "mcq",
"In a UML activity diagram a fork creates three concurrent flows. Flow 1 reaches an activity final node, flow 2 reaches a flow final node, and flow 3 is still executing an action. Which statement is correct?",
["Reaching the flow final node ends the whole activity; the activity final node only ends flow 1",
 "Flow 1 reaching the activity final node terminates the whole activity, flow 3 included; the flow final node of flow 2 only ends flow 2",
 "Activity final and flow final are synonyms: the activity ends when all three flows are finished",
 "An activity final node can only be reached after a join"],
"B",
"The activity final node ends the entire activity as soon as a token reaches it, whatever the other flows are doing. The flow final node only consumes the tokens of its own flow and leaves the other flows running. The course lists both kinds of node (final node and flow final node).", t=75)

# 23
add("fr", "mcq",
"On exécute ce diagramme d'activités avec le mode « carte ». Que se passe-t-il ?" + SEE_FR,
["Le reçu est imprimé, puis l'activité se termine",
 "L'exécution se bloque : la barre de synchronisation (join) attend un jeton sur chacune de ses deux branches entrantes, mais le nœud de décision n'en produit qu'un",
 "Les deux paiements sont exécutés en parallèle",
 "L'activité reboucle sur le choix du mode de paiement"],
"B",
"Le nœud de décision aiguille le jeton vers une seule branche. Le join est une synchronisation : il n'émet que lorsque toutes ses branches entrantes ont reçu un jeton. Il attendra donc indéfiniment : interblocage. Pour réunir deux branches exclusives il faut un nœud de fusion (merge, un losange), pas une barre de synchronisation.", img="paiement_fr.png", t=75)

# 24
add("fr", "written",
"""
class A { String f() { return "A"; }  String g() { return f() + "g"; } }
class B extends A { String f() { return "B" + super.f(); } }
class C extends B { String g() { return super.g() + "C"; } }

Quelle chaîne est affichée par System.out.println(new C().g()); ?""",
None, "BAgC",
"new C().g() : C.g() appelle super.g(), c'est-à-dire B.g(), qui n'existe pas, donc A.g(). Dans A.g(), l'appel f() est lié dynamiquement à l'objet réel (un C) : C ne redéfinit pas f(), c'est donc B.f() qui s'exécute et rend \"B\" + super.f() = \"B\" + \"A\" = \"BA\". A.g() rend \"BA\" + \"g\" = \"BAg\", puis C.g() ajoute \"C\" : BAgC (résultat réel).", t=120)

# 25
add("en", "written",
"The ATM sequence diagram uses a combined fragment with two operands separated by a dashed line: [valid] and [else]. The fragment is currently labelled opt. Which interaction operator should it carry instead? (three letters)" + SEE_EN,
None, "alt",
"opt has a single operand with a guard: it runs or it does not. When there are two (or more) mutually exclusive branches with an else, the operator is alt (alternatives): exactly one operand whose guard is true is executed, like a switch. loop repeats, ref reuses another interaction.", img="atm_seq_en.png", t=60)

# 26
add("en", "mcq",
"Three tickets reach the maintenance team: (1) the application crashes when the phone number field is empty; (2) the mobile-money provider changed the format of its responses, so the parser must be rewritten; (3) teachers ask for a faster course search. Which classification is correct?",
["(1) corrective, (2) adaptive, (3) perfective",
 "(1) corrective, (2) perfective, (3) adaptive",
 "(1) adaptive, (2) corrective, (3) perfective",
 "(1) perfective, (2) adaptive, (3) corrective"],
"A",
"Corrective maintenance fixes detected errors (the crash). Adaptive maintenance adapts the application to a change of its environment, such as execution environment or data formats (the provider's new format). Perfective maintenance improves performance or adds features (the faster search).", t=60)

# 27
add("fr", "mcq",
"Dans le cas d'utilisation « Retirer de l'argent » d'un distributeur, le porteur de carte déclenche le cas, et le DAB sollicite un système d'autorisation bancaire externe pour valider l'opération. Quelle affirmation est correcte ?",
["Le porteur de carte est l'acteur principal ; le système d'autorisation est un acteur secondaire, sollicité par le système",
 "Les deux sont des acteurs principaux, puisque chacun participe au cas",
 "Le système d'autorisation externe est un cas d'utilisation interne",
 "Un cas d'utilisation peut avoir plusieurs acteurs principaux qui l'initient en même temps"],
"A",
"L'acteur principal initie le cas et en obtient un résultat observable : il n'y en a qu'un par cas d'utilisation. Les autres acteurs sont secondaires : ils sont sollicités par le système pour des informations complémentaires, comme ici le système d'autorisation. Un acteur est hors du système (ce n'est pas un cas d'utilisation).", t=60)

# 28
add("fr", "written",
"Dans ce diagramme d'états-transitions d'un lecteur, quel état n'est jamais atteignable depuis l'état initial ? (un seul mot)" + SEE_FR,
None, "Erreur",
"Depuis l'état initial on atteint Attente, puis Lecture (play), puis Pause (pause), l'état final (éteindre). Erreur n'a aucune transition entrante : personne ne peut y arriver, ses transitions (reset) ne seront jamais franchies. C'est un défaut de modélisation : il manque la transition qui mène à Erreur (par exemple depuis Lecture).", img="lecteur_fr.png", t=60)

# 29
add("en", "mcq",
"Which of these Java declarations compiles?",
["abstract class S { abstract void f() { } }",
 "interface I { int X; }",
 "class K { abstract void f(); }",
 "abstract class S { abstract void f(); void g() { } }  followed by  class T extends S { void f() { } }"],
"D",
"An abstract method cannot have a body. An interface field is a constant and must be initialised (int X = 1;). A class that declares an abstract method must itself be abstract. The valid one is the abstract class mixing an abstract and a concrete method, with the concrete subclass T implementing f(). (All four checked with javac.)", t=90)

# 30
add("fr", "mcq",
"Quelle incohérence contient ce diagramme de séquence ?" + SEE_FR,
["Le message « total » est un retour : il devrait être asynchrone",
 "Le message emettre() est envoyé à :Facture avant la création de cet objet : la création (flèche vers le sommet de la ligne de vie) doit précéder tout message qui lui est adressé",
 "Il manque une croix à la fin de la ligne de vie de :Facture",
 "Les messages synchrones devraient être dessinés avec une flèche ouverte"],
"B",
"Une ligne de vie n'existe qu'à partir de la création de l'instance : elle commence sous le rectangle d'en-tête, atteint par le message de création. Ici emettre() part vers :Facture à un moment où l'objet n'existe pas encore (il n'est créé qu'après). Les autres propositions sont fausses : un retour est en pointillé, la croix de destruction est facultative, les messages synchrones ont une flèche pleine.", img="seq_creation_fr.png", t=75)

# 31
add("fr", "written",
"""
class Facture {
    double calculerTotal() {...}
    double appliquerRemise(double t) {...}
    byte[] genererPDF() {...}
    String formaterDate(Date d) {...}      // utilisée par le PDF
    void envoyerEmail(String adresse) {...}
}

D'après le principe de responsabilité unique (SRP), combien de responsabilités distinctes cette classe porte-t-elle (donc de raisons de changer) ? Répondez par un nombre.""",
None, "3",
"calculerTotal et appliquerRemise relèvent des règles métier (calcul de la facture). genererPDF et formaterDate relèvent de la présentation (le format du document). envoyerEmail relève de la notification. Trois raisons de changer : règles de calcul, mise en page, canal d'envoi. Il faudrait au moins trois classes.", t=75)

# 32
add("en", "mcq",
"State Heating declares: entry / startPump(), exit / stopPump(), do / monitor(). Compare (i) an external self-transition triggered by the event tick, and (ii) an internal transition  on tick / log()  declared inside the state. When tick occurs, how many times are startPump() and stopPump() executed in each case?",
["(i) once each; (ii) once each",
 "(i) once each (exit, then entry again); (ii) never (the state is neither left nor re-entered)",
 "(i) never; (ii) once each",
 "(i) once each; (ii) only stopPump()"],
"B",
"An external transition, even a self-transition, leaves the state (exit / stopPump()) and enters it again (entry / startPump(); the do activity restarts). An internal transition has no target state: it only executes its own effect (log()) without exit and entry, and the active state does not change.", t=90)

# 33
add("en", "written",
"Using the diagram, where the bodies of move() are given in the notes, what is the value of s after:  Vehicle[] v = { new Car(), new Boat(), new Train() };  int s = 0;  for (Vehicle x : v) s += x.move();  ? (a number)" + SEE_EN,
None, "19",
"Car.move() = super.move() + 10 = 1 + 10 = 11. Boat.move() = 5. Train.move() = super.move() * 3 = 1 * 3 = 3. Sum: 11 + 5 + 3 = 19. Each call is resolved on the real class of the object (polymorphism), not on the declared type Vehicle. (Checked by running the code.)", img="vehicules_en.png", t=90)

# 34
add("fr", "mcq",
"""
class Compte {
    static int nb = 0;
    int id;
    Compte() { id = ++nb; }
}
Compte a = new Compte();
Compte b = new Compte();
a = new Compte();
System.out.println(a.id + "," + b.id + "," + Compte.nb + "," + b.nb);

Que s'affiche-t-il ?""",
["1,2,3,3", "3,2,3,3", "3,2,2,2", "3,2,3,2"],
"B",
"nb est un attribut de classe (static) : une seule valeur, partagée. Après trois créations nb vaut 3, quelle que soit la référence utilisée (Compte.nb ou b.nb). id est un attribut d'instance : le troisième objet (référencé par a) a l'id 3, le deuxième (b) l'id 2 ; le premier objet n'est plus référencé. Résultat réel : 3,2,3,3", t=90)

# 35
add("en", "written",
"In this qualified association (qualifier accountNo on the Bank end), at most how many Person objects can be linked to ONE given pair (Bank, accountNo)? (a number)" + SEE_EN,
None, "2",
"With a qualifier, a qualified object (a Bank) plus one value of the qualifier (an accountNo) selects the targets at the other end. The multiplicity written next to Person (0..2) is the number of Person per (Bank, accountNo) pair: at most 2 (and possibly none).", img="qualif_en.png", t=60)

# 36
add("en", "mcq",
"Students may retake a course. Every attempt has its own date and grade, and the same pair (Student, Course) can therefore have several attempts. Which design is correct?",
["An association class Enrolment (date, grade) attached to the Student-Course association",
 "The attributes date and grade placed in Student",
 "A regular class Attempt (date, grade) associated with both Student and Course, because an association class allows at most one instance for a given pair of linked objects",
 "The attributes date and grade placed in Course"],
"C",
"An association class has the characteristics of an association: for one pair of objects there is at most one link, hence at most one instance of the association class. As several attempts exist for the same (Student, Course) pair, the attempt must be reified as an ordinary class related to both. Putting the attributes in Student or Course loses the pairing.", t=90)

# 37
add("fr", "written",
"Un module « Utils » regroupe le calcul de la TVA, l'envoi de SMS, le formatage des dates et l'ouverture de la connexion à la base de données. Quel critère de qualité de conception (un seul mot) ce module viole-t-il le plus ?",
None, "cohésion|cohesion",
"La cohésion exprime le degré avec lequel les éléments d'un module sont fonctionnellement liés. Ici des fonctions sans rapport entre elles (fiscalité, messagerie, formats, persistance) sont réunies : très faible cohésion. Le couplage concerne les liens entre modules différents ; la compréhensibilité et l'adaptabilité en souffrent, mais comme conséquences.", t=60)

# 38
add("fr", "mcq",
"""
Voici le diagramme de classes de Compte (un attribut dérivé « / interets » est calculé et n'a pas de champ propre), puis son code Java :
1  public class Compte {
2      private double solde;
3      private int numero;
4      private static int nbComptes;
5      public void deposer(double m) { solde += m; }
6      public double getSolde() { return solde; }
7      public static int total() { return nbComptes; }
8  }

Quelle ligne du code ne respecte pas le diagramme ?""" + SEE_FR,
["La ligne 2", "La ligne 3", "La ligne 4", "La ligne 7"],
"B",
"Le diagramme indique « # numero : int » : # signifie protected, alors que la ligne 3 déclare le champ private. Les lignes 2 (- solde), 4 (attribut de classe souligné, static) et 7 (opération de classe soulignée, static) sont conformes ; l'attribut dérivé /interets n'a pas de champ, ce qui est normal.", img="compte_fr.png", t=100)

# 39
add("fr", "mcq",
"À propos des points de choix d'un diagramme d'états-transitions, quelle affirmation est vraie ?",
["Un point de décision (losange) possède une entrée et au moins deux sorties ; ses gardes sont évaluées au moment où il est atteint",
 "Un point de jonction (petit cercle plein) n'accepte qu'une seule transition entrante",
 "Les transitions sortant d'un point de jonction doivent porter des événements déclencheurs",
 "Les gardes d'un point de décision sont évaluées dès l'entrée dans l'état source de la transition entrante"],
"A",
"Point de décision : une entrée, au moins deux sorties, gardes évaluées quand le point est atteint (donc avec les valeurs de ce moment). Point de jonction : plusieurs segments entrants et sortants possibles, mais ses transitions sortantes ne peuvent pas porter de déclencheur d'événement.", t=75)

# 40
add("en", "written",
"In C++ (non-virtual multiple inheritance), how many copies of the attribute speed would an Amphibious object contain, given this class diagram? (a number)" + SEE_EN,
None, "2",
"Amphibious inherits from LandVehicle and WaterVehicle, each of which inherits from Vehicle: there are two inheritance paths up to Vehicle (the diamond problem). With plain multiple inheritance in C++, an Amphibious object contains two Vehicle sub-objects, hence two copies of speed (virtual inheritance is needed to share one). Java forbids multiple class inheritance altogether: Amphibious could not be written with classes only.", img="amphibie_en.png", t=75)

# 41
add("en", "written",
"""
Dog a = new Dog();
Dog b = a;
Dog c = new Dog();
Dog d = b;
c = d;

At the end, how many Dog objects are still reachable through at least one variable? (a number)""",
None, "1",
"Two objects are created: the first (a, b and later d) and the second (c at first). The last line makes c refer to the first object. The second object is no longer referenced by any variable: it is eligible for garbage collection. Variables are references: four of them designate one single reachable object.", t=75)

# 42
add("fr", "written",
"Dans la description textuelle du cas « Retirer de l'argent » (DAB), le DAB détecte pour la première ou la deuxième fois que le code saisi est erroné (alternative 5a). Il l'indique au porteur de carte et enregistre l'échec sur la carte. À quelle étape du scénario nominal le cas d'utilisation reprend-il ? Répondez par le numéro de l'étape.",
None, "5",
"Dans l'exemple du cours, l'étape 5 du scénario nominal est la validation du code d'identification par le DAB. Après une première ou une deuxième erreur (5a), le cas reprend à l'étape 5 pour redemander et revalider le code ; à la troisième erreur (5b) la carte est confisquée et le cas se termine en échec.", t=60)

# 43
add("fr", "mcq",
"La classe Confrontation a une opération  Resultat confronter(Strategie s1, Strategie s2). Strategie n'est ni un champ de Confrontation, ni créée par elle : elle n'apparaît que dans la signature. Quelle relation traduit fidèlement ce code ?" + SEE_FR,
["La composition 1 — 2 telle que dessinée",
 "Une agrégation 1 — 2",
 "Une dépendance : trait discontinu orienté de Confrontation vers Strategie",
 "Une association navigable dans les deux sens"],
"C",
"Une classe qui n'utilise une autre que comme paramètre d'une opération est liée à elle par une dépendance (relation unidirectionnelle, trait discontinu orienté, la modification de la cible peut impliquer celle de la source). Il n'y a ni contenance (composition, agrégation) ni lien structurel durable (association).", img="strategie_fr.png", t=75)

# 44
add("en", "mcq",
"Class Planner calls the operations of an interface Booking, and class Agency provides them. Which pair of UML relationships is correct?",
["Planner -> Booking: «realize» (dashed, hollow triangle); Agency -> Booking: «use» (dashed, open arrow)",
 "Planner -> Booking: «use» (dependency, dashed with open arrow); Agency -> Booking: «realize» (dashed with hollow triangle)",
 "Planner -> Booking: generalisation; Agency -> Booking: association",
 "Planner -> Booking: composition; Agency -> Booking: «realize»"],
"B",
"The class that implements the interface realises it: dashed line ending with a hollow triangle and the stereotype «realize». A client class that needs the interface depends on it (required interface): dependency stereotyped «use». An interface must be realised by at least one class, and a class may realise several interfaces.", t=75)

# 45
add("fr", "written",
"""
abstract class Forme { abstract int aire(); }
class Rectangle extends Forme { int l, h; Rectangle(int l, int h) { this.l = l; this.h = h; } int aire() { return l * h; } }
class Triangle extends Forme { int b, h; Triangle(int b, int h) { this.b = b; this.h = h; } int aire() { return b * h / 2; } }
class Carre extends Rectangle { Carre(int c) { super(c, c); } }

Forme[] f = { new Rectangle(2, 3), new Triangle(5, 3), new Carre(3), new Rectangle(4, 1) };
int s = 0;  for (Forme x : f) s += x.aire();
System.out.println(s);

Quelle valeur est affichée ?""",
None, "26",
"Rectangle(2,3) : 6. Triangle(5,3) : 5 * 3 / 2 en arithmétique entière = 15 / 2 = 7 (et non 7,5). Carre(3) hérite de Rectangle : 3 * 3 = 9. Rectangle(4,1) : 4. Somme : 6 + 7 + 9 + 4 = 26 (résultat réel). Le polymorphisme choisit chaque aire() selon la classe réelle.", t=120)

# 46
add("en", "mcq",
"OrderService creates a MySqlOrderRepository itself (see the note) and depends directly on it. Which principle is violated, and what is the fix?" + SEE_EN,
["Single Responsibility: split OrderService into two classes",
 "Dependency Inversion: make OrderService depend on an abstraction (an interface OrderRepository) that MySqlOrderRepository implements, and give it the implementation from outside",
 "Liskov Substitution: make MySqlOrderRepository extend OrderService",
 "Interface Segregation: split the methods save and find of MySqlOrderRepository into two classes"],
"B",
"A high-level class (business rules) depends on a low-level concrete class (MySQL access): changing the database forces changing OrderService, and it cannot be tested alone. Dependency Inversion says both should depend on an abstraction: define the interface OrderRepository, let MySqlOrderRepository implement it, and inject it into OrderService.", img="dip_en.png", t=75)

# 47
add("en", "mcq",
"Which statement about design approaches is FALSE?",
["The functional approach separates the problem of representing the data from the problem of processing it",
 "The object-oriented approach is driven by data types and sees the software as a collection of objects",
 "The object-oriented approach is a top-down hierarchical decomposition by successive refinements",
 "The systemic approach considers the system as a whole of interconnected sub-systems, without yet looking at internal details"],
"C",
"Top-down decomposition by successive refinements is the functional approach. The object-oriented approach is bottom-up (ascending): it starts from autonomous objects and builds more complex ones; it is driven by data types and the structure of the problem, not by the functions.", t=60)

# 48 (true/false)
add("en", "tf",
"True or false: a UML component may not contain classes; its internal behaviour is realised by artifacts only, and only its interfaces are visible.",
["True", "False"], "B",
"False. A component is an autonomous software unit with provided and required interfaces; its internal behaviour is generally realised by a set of classes, and components can contain classes. Its behaviour is hidden: only its interfaces are visible.", t=45)

# 49
add("fr", "mcq",
"Quel problème de notation présente ce diagramme de séquence ?" + SEE_FR,
["Le message valider() devrait être asynchrone",
 "journaliser(evt) est un message asynchrone (flèche ouverte) : l'émetteur n'attend pas de réponse, donc la réponse « acquittement » en pointillé contredit l'asynchronisme",
 "Les réponses en pointillé ne sont jamais autorisées",
 "terminer() devrait être un message de création"],
"B",
"Un message asynchrone n'attend pas de réponse et ne bloque pas l'émetteur ; il se dessine avec une flèche à extrémité ouverte. Une réponse (flèche en pointillé) n'a de sens qu'après un message synchrone, où l'émetteur reste bloqué pendant le traitement. Ici la réponse à journaliser() est incohérente.", img="seq_async_fr.png", t=75)

# 50
add("en", "mcq",
"""
double discount(Customer c) {
    switch (c.type) {
        case REGULAR: return 0.0;
        case STUDENT: return 0.1;
        case VIP:     return 0.2;
    }
    return 0.0;
}

Adding a new customer type STAFF forces a change to discount(). Which principle is violated, and which refactoring fixes it?""",
["Open/Closed: replace the switch by polymorphism (one discount strategy per customer type) so that new types are added without modifying existing code",
 "Liskov Substitution: make the class Customer final",
 "Interface Segregation: split Customer into smaller interfaces",
 "Dependency Inversion: replace the enumeration by a static class"],
"A",
"Each new type means editing existing code that already works: the code is not closed for modification. With polymorphism (an interface DiscountPolicy with one implementation per customer type, or a discount() method redefined in subclasses) a new type is a new class, and discount() never changes.", t=75)

# 51
add("fr", "mcq",
"""
class Chien { private String nom; Chien(String n) { nom = n; } void setNom(String n) { nom = n; } String getNom() { return nom; } }

Chien a = new Chien("Charlie");
Chien b = new Chien("Charlie");
Chien c = a;
c.setNom("Max");
System.out.println((a == b) + " " + a.getNom() + " " + b.getNom() + " " + (a == c));

Que s'affiche-t-il ?""",
["true Max Max true", "false Max Charlie true", "false Charlie Charlie true", "true Max Charlie false"],
"B",
"Un objet = identité + état + comportement. a et b ont le même état au départ (« Charlie ») mais des identités différentes : a == b est false. c est une autre référence vers le même objet que a : modifier c modifie a (Max) et a == c est true. b n'a pas changé : Charlie. Résultat réel : false Max Charlie true", t=90)

# 52
add("en", "written",
"In this communication diagram the sequence numbers are hierarchical: 1.2 is nested in 1, and 1.10 is the tenth message nested in 1 (so it comes after 1.2.1). Which message is executed fifth? Give its operation name only, without parentheses." + SEE_EN,
None, "notify|notify()",
"Execution order follows the hierarchical numbers in numeric order, depth first: 1 addBook, 1.1 find, 1.2 reserve, 1.2.1 check (nested in 1.2), 1.10 notify, then 2 checkout. The fifth message is 1.10: notify(). A text sort would wrongly put 1.10 before 1.2.", img="comm_en.png", t=90)

# 53
add("fr", "mcq",
"Quelle étiquette de message respecte la syntaxe d'un diagramme de communication, sachant que cette syntaxe est :  [ '[' <cond> ']' [<séq>] : ] [<var> :=] <msg>( [<par>] ) ?",
["[stock > 0] 1.1 : dispo := verifier(isbn)",
 "1.1 [stock > 0] : dispo := verifier(isbn)",
 "[stock > 0] : 1.1 dispo := verifier(isbn)",
 "dispo := 1.1 : [stock > 0] verifier(isbn)"],
"A",
"Dans la syntaxe, la partie facultative avant les deux-points est la condition entre crochets, suivie du numéro de séquence ; viennent ensuite deux-points, la variable de retour (var :=) et le message avec ses paramètres. [stock > 0] 1.1 : dispo := verifier(isbn) respecte cet ordre.", t=75)

# 54
add("fr", "mcq",
"Dans un diagramme de déploiement, comment représente-t-on correctement l'affectation d'un composant et celle d'un artefact à un nœud ?",
["Composant : dépendance «support» orientée du composant vers le nœud ; artefact : dépendance «deploy» orientée de l'artefact vers le nœud",
 "Composant : «deploy» du nœud vers le composant ; artefact : «support» du nœud vers l'artefact",
 "Composant et artefact : simple association sans stéréotype",
 "Composant : «deploy» de l'artefact vers le composant ; artefact : généralisation vers le nœud"],
"A",
"Un composant est affecté à un nœud en le plaçant dans le nœud ou par une dépendance stéréotypée «support» orientée du composant vers le nœud. Un artefact se déploie sur un nœud par une dépendance «deploy» pointant vers le nœud (ou en étant dessiné à l'intérieur du cube).", t=75)

# 55
add("fr", "mcq",
"Quelle règle UML ce diagramme enfreint-il ?" + SEE_FR,
["Une classe d'association ne peut pas avoir d'attributs",
 "Une classe d'association ne peut être reliée qu'à une seule association ; ici Note est reliée à deux associations",
 "Une association ne peut pas relier trois classes",
 "Une classe d'association doit être abstraite"],
"B",
"Une classe d'association possède les caractéristiques d'une association et celles d'une classe (elle peut avoir des attributs). Mais elle ne peut être reliée qu'à une seule association : ici le trait discontinu de Note part vers les deux associations (Etudiant–Cours et Cours–Enseignant).", img="noteassoc_fr.png", t=60)

# 56
add("en", "mcq",
"""
interface Machine { void print(); void scan(); void fax(); }
class BasicPrinter implements Machine {
    public void print() { /* ... */ }
    public void scan() { throw new UnsupportedOperationException(); }
    public void fax()  { throw new UnsupportedOperationException(); }
}

Which principle is mainly violated, and what is the fix?""",
["Interface Segregation: clients are forced to depend on methods they do not use; split Machine into small role interfaces (Printer, Scanner, Fax)",
 "Dependency Inversion: make BasicPrinter depend on a concrete Machine class",
 "Open/Closed: add a new Machine subclass for every method",
 "Single Responsibility: merge print, scan and fax into a single method"],
"A",
"BasicPrinter must implement scan() and fax() although it cannot: the interface is too fat. Interface Segregation says no client should depend on methods it does not use: split the interface into role interfaces (Printer, Scanner, Fax) and let each class implement only what it supports.", t=60)

# 57
add("fr", "written",
"Un Cours contient des Chapitres qui n'existent que dans leur Cours ; détruire le Cours détruit ses Chapitres. Chaque Chapitre appartient donc à au plus combien de Cours ? Autrement dit, quelle est la valeur maximale autorisée de la multiplicité du côté composite d'une composition ? (un nombre)",
None, "1",
"Une composition (agrégation composite) est une contenance structurelle : une instance de la partie appartient toujours à au plus une instance de l'élément composite, la destruction du composite entraîne celle des parties. La multiplicité du côté composite est donc 1 ou 0..1, jamais supérieure à 1.", t=45)

# 58
add("fr", "mcq",
"Quelle erreur contient ce diagramme de composants ?" + SEE_FR,
["Planificateur ne devrait pas avoir d'interface requise",
 "FaireReservation est requise des deux côtés (deux « sockets » face à face) : une interface requise doit être reliée à une interface fournie (« lollipop ») d'un autre composant",
 "Agence devrait fournir ActualiserPlan",
 "Les composants doivent être dessinés comme des classes"],
"B",
"Une interface requise (demi-cercle) se satisfait par une interface fournie (cercle) d'un autre composant : ici Agence (requise) et Planificateur (fournie) sont bien assemblés pour ActualiserPlan. Entre Planificateur et Reservation, deux interfaces requises se font face, sans interface fournie : personne ne réalise FaireReservation.", img="composants_fr.png", t=75)

# 59
add("en", "mcq",
"In a sequence diagram, the ATM must send retryPIN() to the customer again and again while the PIN is wrong, at most 3 times. Which combined fragment fits best?",
["opt [PIN wrong]",
 "alt [PIN wrong] / [else]",
 "loop(0,3) [PIN wrong]",
 "ref retryPIN"],
"C",
"loop is the operator for repetition: it carries a minimum and a maximum count and a guard. Here 0 to 3 repetitions while the PIN is wrong: loop(0,3) [PIN wrong]. opt runs its operand at most once, alt chooses between branches once, ref only reuses another interaction.", t=75)

# 60
add("en", "mcq",
"In this model a University is deleted. Which statement is guaranteed by the diagram?" + SEE_EN,
["Its Departments and the Courses of those departments are destroyed",
 "Its Departments are destroyed, but their Courses survive",
 "Its Departments necessarily survive (aggregation means sharing), so their Courses survive too",
 "The model alone forces no destruction: the aggregation leaves each Department independent, and Courses are only destroyed if their own Department is"],
"D",
"University and Department are linked by an aggregation (white diamond): a Department is a part, but it is not owned exclusively and is not destroyed with the University, nor does it necessarily survive: nothing is said. Department and Course are linked by a composition (black diamond): Courses die with their Department. So the only guaranteed rule concerns a deleted Department, not a deleted University.", img="univ_en.png", t=90)

# 61
add("fr", "mcq",
"Une Maison est composée de Pieces : elles n'existent que dans la maison et sont détruites avec elle. Quelle implémentation Java traduit le mieux cette composition ?",
["class Maison { private final List<Piece> pieces; Maison(List<Piece> p) { this.pieces = p; } }",
 "class Maison { private final List<Piece> pieces = new ArrayList<>(); Maison(int nb) { for (int i = 0; i < nb; i++) pieces.add(new Piece()); } }",
 "class Maison { private static List<Piece> pieces = new ArrayList<>(); }",
 "class Piece { private Maison maison; }  class Maison { }"],
"B",
"Dans une composition le composite crée et possède ses parties : elles naissent dans la maison et ne sont pas partagées. Si la liste est reçue de l'extérieur, les pièces existent ailleurs (agrégation) et un alias subsiste. Une liste static serait commune à toutes les maisons. Un champ Maison dans Piece inverse le sens de la contenance.", t=75)

# 62
add("en", "mcq",
"What is wrong with the 'Web Server' part of this deployment diagram?" + SEE_EN,
["Nothing: the direction of a dependency arrow is free",
 "The «deploy» dependency must point from the artifact (app.war) to the node (Web Server), not from the node to the artifact",
 "An artifact can only be drawn inside a node, never outside",
 "A «device» cannot host an artifact"],
"B",
"An artifact is deployed on a node: the «deploy» dependency points to the node. The artifact may also be drawn inside the node's cube (as schema.sql is in DB Server), but this is an alternative, not an obligation. A device (a physical node) is precisely what hosts artifacts.", img="deploy_en.png", t=60)

# 63
add("en", "mcq",
"In a class diagram, Order -> Product is navigable only from Order to Product: an order knows its products, but a product does not know the orders that contain it. Which Java skeleton matches?",
["class Order { List<Product> products; }  class Product { }",
 "class Order { }  class Product { List<Order> orders; }",
 "class Order { List<Product> products; }  class Product { List<Order> orders; }",
 "class Order { Product product; }  class Product { Order order; }"],
"A",
"Navigability towards Product means that Order holds a reference to its products (here a list, since an order has several). Product holds nothing about Order. The version where Product holds the list of Orders reverses the direction, and the two versions where each class refers to the other are navigable in both directions.", t=60)

# 64
add("fr", "mcq",
"Un document « plan de test global et par module » est livré à l'issue de quelle activité du processus de développement présenté en cours ?",
["L'analyse des besoins (cahier de charge fonctionnelle, plan de recette)",
 "La conception détaillée (modèles de conception, dossier de conception technique + plan de test global et par module)",
 "L'implémentation (code source documenté, modules testables, scripts d'installation)",
 "Le déploiement (version finale, guide d'installation et d'utilisation, procès-verbal de recette)"],
"B",
"D'après le cours, la conception (détaillée) produit les modèles de conception, le dossier de conception (technique) et le plan de test global et par module. L'analyse des besoins produit le cahier de charge fonctionnelle et le plan de recette ; l'implémentation le code documenté ; le déploiement la version finale et le PV de recette.", t=75)

# 65
add("en", "written",
"Once 'Prepare shipment' is moved to the swimlane where it belongs (warehouse staff pack and ship; sales staff only check the order), how many transitions of this activity diagram cross a partition boundary? Count every arrow between two nodes, including the arrows leaving the initial node and entering the final node. (a number)" + SEE_EN,
None, "3",
"Place order (Customer) -> Validate order (Sales): crossing 1. Validate order (Sales) -> Prepare shipment (now Warehouse): crossing 2. Prepare shipment -> Ship parcel (both Warehouse): none. Ship parcel (Warehouse) -> Receive parcel (Customer): crossing 3. The initial node and the final node are both in Customer, with their neighbours Place order and Receive parcel: no crossing. Total: 3.", img="lanes_en.png", t=75)
