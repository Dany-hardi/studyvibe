# -*- coding: utf-8 -*-
"""8 questions where the student writes code (marked by the AI: it reads the logic, it never runs the code). Reference solutions are real files that were compiled and run."""
C = []
def code(lang, code_lang, text, ref_files, notes, expl, img="", t=900):
    C.append(dict(lang=lang, kind="code", code_lang=code_lang, text=text.strip("\n"), ref_files=ref_files, notes=notes.strip("\n"), expl=expl.strip("\n"), img=img, t=t))

# 1 Strategy ----------------------------------------------------------------------------------------------------------
code("fr", "java",
"""Implémentez en Java le patron représenté par la figure (diagramme de classes d'un panier d'achat et de ses modes de livraison).

Règles de calcul :
- LivraisonStandard : 1 500 FCFA plus 200 FCFA par kilogramme entamé (le poids est arrondi au kilogramme supérieur).
- LivraisonExpress : 3 500 FCFA plus 450 FCFA par kilogramme entamé ; elle refuse (IllegalArgumentException) tout colis de plus de 20 kg, 20 kg exactement étant accepté.
- Retrait : gratuit si le total du panier est au moins égal à 50 000 FCFA, sinon 500 FCFA.

Panier.fraisLivraison délègue à la stratégie courante ; si aucune stratégie n'a été définie, elle lève IllegalStateException ; definirStrategie refuse null avec IllegalArgumentException. Écrivez l'interface, les trois classes et Panier (constructeur compris), de façon qu'on puisse ajouter une quatrième stratégie sans modifier Panier.""",
["C1.java"],
"""ESSENTIEL : une interface StrategieLivraison et trois classes qui l'implémentent, Panier qui détient la stratégie par composition et lui délègue le calcul (aucun if ou switch sur le type de livraison).
ESSENTIEL : arithmétique exacte : arrondi au kg supérieur (par exemple 2,3 kg compte pour 3 kg : 1 500 + 600 = 2 100 en standard), express limité à 20 kg inclus (exception au-delà), retrait gratuit à partir de 50 000 inclus, 500 sinon.
ESSENTIEL : definirStrategie refuse null (IllegalArgumentException) ; fraisLivraison sans stratégie lève IllegalStateException.
Accepter des variantes équivalentes (classe abstraite, lambdas) si la logique et les exceptions sont correctes. Le code doit compiler dans l'esprit (syntaxe Java correcte).""",
"Patron Stratégie : une interface, des algorithmes interchangeables et un contexte (Panier) qui délègue. Les règles à respecter : arrondi au kilo supérieur, plafond de 20 kg pour l'express, seuil de 50 000 pour le retrait.",
img="strategie_livraison_fr.png", t=900)

# 2 Observer ----------------------------------------------------------------------------------------------------------
code("en", "java",
"""Implement in Java the structure shown in the figure.

Sensor.setValue(v) stores the value and notifies all attached observers by calling update(v), but only when v is strictly greater than the threshold given to the Sensor constructor. Dashboard keeps the last value it received. AlarmLogger counts the alarms it received.

Requirements: attach ignores null and duplicates (an observer attached twice is notified once); detach of an unknown observer does nothing; an observer may detach itself from inside update without breaking the iteration over the observers; an exception thrown by one observer must not prevent the others from being notified. Write the interface and all the classes.""",
["C2.java"],
"""ESSENTIAL: interface Observer with update(double); Sensor with a collection of observers; Dashboard and AlarmLogger implement update correctly (last value, alarm count).
ESSENTIAL: notification only when v is strictly greater than the threshold (equal does not notify).
ESSENTIAL: attach ignores null and duplicates; detach tolerates unknown observers.
ESSENTIAL: safe iteration: iterating over a copy or using a copy-on-write list so that an observer can detach itself during update without ConcurrentModificationException or skipping observers.
ESSENTIAL: an exception from one observer is caught so the others are still notified.""",
"The Observer pattern with the usual traps: the strict threshold, duplicates, self-removal during notification, and isolation of failing observers.",
img="observer_class_en.png", t=900)

# 3 State -------------------------------------------------------------------------------------------------------------
code("fr", "java",
"""Implémentez en Java le cycle de vie d'un document avec le patron État, d'après le diagramme d'états de la figure.

Document a un titre, un ensemble de relecteurs ayant approuvé (vide au départ) et un état courant (Brouillon au départ). Ses méthodes soumettre(), approuver(String relecteur), rejeter(), corriger(), retirer() et abandonner() délèguent à l'état courant ; etat() retourne le nom de l'état courant.

Règles : soumettre exige un titre non vide (sinon IllegalStateException). approuver ajoute un relecteur ; un même relecteur ne compte qu'une fois ; le document est publié seulement lorsqu'il y a au moins deux relecteurs distincts. retirer ramène un document publié à Brouillon et remet les relecteurs à zéro. abandonner est possible depuis Rejeté et rend le document inutilisable : toute opération ensuite lève IllegalStateException. Tout événement non prévu dans un état est ignoré sans erreur.""",
["C3.java"],
"""ESSENTIEL : une abstraction d'état (interface ou classe abstraite) et une classe par état (Brouillon, En relecture, Publié, Rejeté, et un état final Abandonné), Document qui délègue à l'état courant et change d'état ; pas de grand if/switch sur un code d'état dans Document.
ESSENTIEL : transitions correctes : soumettre (titre non vide sinon IllegalStateException) vers En relecture ; approuver avec relecteurs distincts (un ensemble), publication à partir de deux ; rejeter vers Rejeté ; corriger vers Brouillon ; retirer remet à Brouillon et vide les relecteurs ; abandonner vers l'état final.
ESSENTIEL : les événements non prévus sont ignorés dans les états normaux, mais toutes les opérations lèvent IllegalStateException dans l'état abandonné.""",
"Chaque état est une classe qui sait quelles transitions lui sont permises ; Document délègue. Les points délicats sont les relecteurs distincts, la remise à zéro au retrait et l'état final qui rejette tout.",
img="state_doc_fr.png", t=900)

# 4 JUnit --------------------------------------------------------------------------------------------------------------
code("en", "java",
"""Write the JUnit 5 test class for InvoiceReminder.remindOverdue, using hand-written test doubles (no mocking library): a fake Clock fixed on a chosen date, and a recording Mailer (a spy) that stores the messages sent and can be told to fail for some recipients.

Behaviour to verify (see the figure): an invoice is overdue when it is not paid and its due date is strictly before today; remindOverdue sends one mail per overdue invoice with the subject "Invoice <number> is overdue" and returns the number of mails sent; if send throws, the exception is caught, the invoice is skipped and it is not counted; a null list makes the method throw IllegalArgumentException.

Your tests must cover at least: no invoices; an invoice due exactly today; an invoice due yesterday and unpaid; a paid invoice that is overdue; several invoices of which only some are overdue; a mailer that fails for one invoice while another still succeeds; and the null list. Give the tests names that explain a failure, keep the arrange-act-assert structure visible, and make each test independent of the others.""",
["InvoiceReminderTest.java"],
"""ESSENTIAL: hand-written test doubles: a Clock fixed on a date and a spy Mailer that records the messages (to, subject) and can throw for chosen recipients; fresh doubles per test (independent tests).
ESSENTIAL: at least seven distinct tests covering the listed cases with correct assertions: nothing sent and 0 for no invoices; due today is NOT overdue (boundary); unpaid due yesterday gives one mail with the exact subject "Invoice <number> is overdue" and count 1; paid overdue gives no mail; mixed list gives mails only for overdue unpaid ones with the right count; a failing mail is skipped, not counted, and the other mail is still sent; null list throws IllegalArgumentException (assertThrows).
ESSENTIAL: assertions check both the returned count and what the spy recorded (recipient and subject), not only one of them.
A correct suite must detect these faulty implementations: treating 'due today' as overdue (<= instead of <), ignoring the paid flag, letting the exception propagate, no null check.""",
"Good unit tests use test doubles for time and mail, probe the boundary (due today), verify both the result and the interaction, and name each case clearly.",
img="spy_class_en.png", t=900)

# 5 Composite ----------------------------------------------------------------------------------------------------------
code("fr", "java",
"""Implémentez en Java le patron Composite de la figure (système de fichiers).

Fichier.taille() retourne son nombre d'octets ; Dossier.taille() est la somme récursive de la taille de ses enfants ; rechercher(ext) retourne tous les Fichier dont l'extension, comparée sans tenir compte de la casse et sans le point éventuel, vaut ext, en parcourant récursivement les dossiers.

Dossier.ajouter(e) refuse null, un doublon (même instance) et tout ajout qui créerait un cycle (ajouter un dossier à l'un de ses descendants ou à lui-même) en levant IllegalArgumentException. Un élément n'a qu'un seul parent : ajouter un élément déjà rattaché à un autre dossier le déplace (il est retiré de l'ancien parent). retirer(e) ignore un élément absent. Le nom d'un élément ne peut pas être vide (IllegalArgumentException).""",
["C5.java"],
"""ESSENTIEL : classe abstraite Élément (nom, taille(), rechercher), Fichier (feuille) et Dossier (composite) ; récursion correcte pour taille() et rechercher() sans cas particulier pour le type.
ESSENTIEL : ajouter refuse null, doublon et tout cycle (élément égal au dossier ou dossier contenant le dossier cible dans son sous-arbre) avec IllegalArgumentException.
ESSENTIEL : un seul parent : l'élément déjà rattaché est retiré de son ancien parent ; retirer ignore l'absent.
ESSENTIEL : recherche d'extension insensible à la casse et acceptant un point initial ; nom vide refusé.""",
"Composite : une abstraction commune pour les feuilles et les dossiers. La difficulté est dans les invariants : pas de cycle, un seul parent, déplacement automatique.",
img="composite_fr.png", t=900)

# 6 Refactor OCP -------------------------------------------------------------------------------------------------------
code("en", "java",
"""Listing 1 computes the monthly pay. Refactor it so that new contract types can be added without modifying existing classes (Open/Closed Principle), while preserving the exact behaviour of the three existing types, including the exception for an unknown contract.

Rules (as in the listing): PERMANENT: baseSalary plus overtimeHours times (baseSalary / 160) times 1.25. CONTRACTOR: baseSalary plus overtimeHours times (baseSalary / 160). INTERN: the pay is the base salary capped at 50 000, overtime is not paid. An unknown contract throws IllegalArgumentException with the message "Unknown contract " followed by the contract name.

Write the Java code: the abstraction, the three implementations, a component that selects the implementation from the contract name using a registry (not an if/else chain or a switch), and PayrollCalculator.monthlyPay keeping its public signature. Comments are welcome, long explanations are not.""",
["C6.java"],
"""ESSENTIAL: an abstraction (interface or abstract class) with three implementations whose formulas are exact: permanent = base + overtime * (base / 160) * 1.25; contractor = base + overtime * (base / 160); intern = min(base, 50000) with no overtime pay.
ESSENTIAL: PayrollCalculator.monthlyPay keeps its signature and selects the implementation through a registry (Map or equivalent) filled at construction or by registration, with no if/else or switch on the contract name.
ESSENTIAL: unknown contract throws IllegalArgumentException with the message "Unknown contract " + name.
ESSENTIAL: adding a new contract type must only need a new class and its registration (a way to register is provided or evident).""",
"Replace the conditional by polymorphism and a registry: new types are added by new classes, not by editing existing code.",
img="payroll_en.png", t=900)

# 7 Invariants ---------------------------------------------------------------------------------------------------------
code("fr", "java",
"""Implémentez en Java les classes Periode, Reservation et Chambre de la figure, en respectant les invariants.

Periode : immuable (champs final, pas de setters) ; le constructeur rejette null et debut >= fin avec IllegalArgumentException ; chevauche(autre) est vrai si les deux périodes partagent au moins une nuit, la date de fin étant exclue (une période qui finit le 10 ne chevauche pas celle qui commence le 10) ; equals, hashCode et toString sont cohérents.
Reservation : immuable ; le client ne peut pas être vide et la période ne peut pas être nulle (IllegalArgumentException).
Chambre : reserver(client, periode) enregistre et retourne la réservation si la chambre est libre, sinon lève IllegalStateException ; estLibre(periode) ; reservations() expose une vue non modifiable (ou une copie) ; une Chambre ne mémorise jamais deux réservations qui se chevauchent, même si plusieurs threads appellent reserver en même temps.""",
["C7.java"],
"""ESSENTIEL : Periode immuable (final, pas de setter) avec validation (null, debut >= fin), chevauche correct avec fin exclue (debut < autre.fin et autre.debut < fin), equals/hashCode cohérents.
ESSENTIEL : Reservation immuable avec validations.
ESSENTIEL : Chambre.reserver vérifie la disponibilité et enregistre de façon atomique (méthode synchronisée, verrou ou structure concurrente équivalente), lève IllegalStateException en cas de chevauchement ; estLibre cohérent avec reserver.
ESSENTIEL : reservations() ne laisse pas modifier la liste interne (vue non modifiable ou copie défensive).""",
"Les invariants sont protégés par l'immuabilité, la validation à la construction, la copie défensive et l'atomicité de la vérification suivie de l'enregistrement.",
img="invariants_fr.png", t=900)

# 8 SQL ---------------------------------------------------------------------------------------------------------------
code("en", "sql",
"""Write the PostgreSQL DDL that persists the class diagram with the class table inheritance strategy: one table for Vehicle (the abstract superclass) holding the common columns, and one table per concrete subclass that shares the primary key of Vehicle.

Requirements: surrogate primary keys; plate is unique and must match two letters, three digits, two letters (a CHECK with a regular expression); year is between 1950 and a plausible upper bound (explain in a comment how you treat the "current year + 1" limit, since a CHECK cannot call a non-immutable function); email of an owner is unique; every vehicle has exactly one owner and an owner may have no vehicle; deleting a vehicle deletes its subtype row; doors between 2 and 5, payload positive, at least two axles, engine capacity positive; and a vehicle must belong to at most one subtype table: enforce it with a discriminator column and composite foreign keys. Write only the DDL (comments allowed).""",
["C8.sql"],
"""ESSENTIAL: tables owner, vehicle, car, truck and motorbike with primary keys; subtype tables use the vehicle id as primary key and as foreign key to vehicle with ON DELETE CASCADE.
ESSENTIAL: vehicle.owner_id NOT NULL with a foreign key to owner; owner.email UNIQUE; vehicle.plate UNIQUE with a CHECK on the regular expression ^[A-Z]{2}[0-9]{3}[A-Z]{2}$; year CHECK (fixed bounds, with the current-year limit handled by a trigger or the application, explained).
ESSENTIAL: exclusive subtypes: a discriminator column in vehicle, a UNIQUE (id, discriminator) key, and in each subtype table a discriminator column fixed by a CHECK to its value and a composite foreign key (id, discriminator) to vehicle.
ESSENTIAL: attribute CHECK constraints: doors BETWEEN 2 AND 5, payload > 0, axles >= 2, engine capacity > 0.
Valid PostgreSQL syntax is expected; a small syntax slip that does not change the meaning is acceptable.""",
"Class table inheritance gives one table per class sharing the key; the discriminator and composite foreign keys make the subtypes exclusive.",
img="orm_inherit_en.png", t=900)
