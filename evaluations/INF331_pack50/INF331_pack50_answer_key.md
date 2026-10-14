# INF331 pack of 50: answer key (teacher only)

## 1. [open, fr] Une équipe développe une application de gestion des bourses pour une université, selon un cycle en V. Quatre a

Reference:

(a) Vérification (activité statique de revue) au niveau de la spécification des exigences : on contrôle la cohérence, la complétude et la non-ambiguïté du cahier des charges (par rapport aux besoins exprimés / aux normes de rédaction).
(b) Validation : recette utilisateur au sommet du V, par rapport à l'expression des besoins des utilisateurs (cahier des charges) : on vérifie que le produit répond au besoin réel.
(c) Vérification statique (inspection) au niveau du codage / de la conception détaillée, par rapport aux standards de codage et à la conception détaillée.
(d) Vérification dynamique : test d'intégration, par rapport à la conception architecturale (spécification de conception).
Conclusion : vérifier = « construit-on le produit correctement ? » (conformité aux spécifications) ; valider = « construit-on le bon produit ? » (adéquation au besoin). Si la spécification est erronée, incomplète ou ne reflète pas le besoin, un produit parfaitement conforme à ses spécifications reste inadapté : seule la validation avec les utilisateurs (recette) le révèle.

Essential points:

ESSENTIEL : (a) vérification, niveau exigences ; (b) validation, recette, par rapport aux besoins utilisateurs ; (c) vérification statique, niveau codage/conception détaillée ; (d) vérification dynamique, intégration, par rapport à la conception architecturale.
ESSENTIEL : définition correcte de vérification (produit construit correctement / conformité aux spécifications) et de validation (bon produit / adéquation au besoin).
ESSENTIEL : la conclusion doit dire que des spécifications fausses ou incomplètes passent la vérification mais pas la validation.
Accepter (a) comme « validation des exigences » seulement si l'étudiant précise que cette relecture est faite par l'équipe et non par les utilisateurs ; dans ce cas le raisonnement doit rester cohérent.

## 2. [mcq, fr] Le listing 1 définit Rectangle, Square (qui en hérite) et une méthode de contrôle Check.test. L'appel de test 

Correct: **A**

- A. Square impose l'invariant w = h : setWidth ne garantit plus que la hauteur reste inchangée, donc la postcondition héritée s'affaiblit
- B. Square devrait déclarer ses champs private : l'accès protected à w et h expose l'état interne et casse l'encapsulation du comportement
- C. Le test échoue parce que area() n'est pas redéfinie dans Square : sans redéfinition, la méthode hérite d'un calcul inadapté à un carré
- D. La redéfinition de setWidth change le comportement de la classe de base, ce que Java interdit à la compilation pour une sous-classe

Le principe de substitution de Liskov exige qu'une sous-classe respecte le contrat de sa super-classe : après setWidth(5) puis setHeight(4), un Rectangle a une aire de 20 car setWidth ne touche pas la hauteur (postcondition). Square modifie les deux dimensions à chaque appel : il renforce l'invariant (w = h) et affaiblit la postcondition de setWidth, donc le code client écrit pour Rectangle se casse. Les autres affirmations sont fausses : l'encapsulation n'est pas la cause, area() est correcte pour un carré et Java ne vérifie pas le comportement à la compilation.

## 3. [open, en] A tax authority must deliver an electronic filing platform by a date fixed by a new law, 14 months from now. T

Reference:

A pure V-model fits the stable, well-specified part (filing and calculation rules): fixed scope, early detailed specification, traceability, formal milestones and documents required by procurement. Its weaknesses: late feedback, high cost of change, nothing usable in production before the end, which contradicts the 6-month objective and the uncertainty on the bank APIs.
Scrum (or another iterative/incremental approach) fits the uncertain part (bank integrations): short time-boxed iterations, early integration and feedback, early spikes to retire the API risk, ability to absorb interface changes (adapters / anti-corruption layer isolating the banks behind one internal payment interface). Its weaknesses: weak natural fit with fixed milestones and heavy documentation, and with a fixed scope contract.
Hybrid: a plan-driven backbone (architecture, requirements baseline, compliance documentation, milestones aligned with the end of increments) with agile iterations inside the increments; the first increment (month 6) delivers a usable subset (for example filing and calculation for simple cases with one bank); remaining increments add banks and features. The deadline is fixed, so time is the constraint and the scope is the variable: prioritise (for example MoSCoW), keep a buffer, cut or defer lower-priority features rather than the date. Documentation is produced incrementally and baselined at each milestone to satisfy procurement. Risks are managed explicitly (risk-driven ordering: bank integration spikes first).

Essential points:

ESSENTIAL: recommends a hybrid and explicitly assigns the stable core (filing/calculation) to a plan-driven V approach and the uncertain bank integrations to iterative/agile work.
ESSENTIAL: addresses the uncertainty and changes of the bank APIs (early spikes, adapters/isolation, short feedback loops).
ESSENTIAL: addresses the 6-month usable subset through incremental delivery.
ESSENTIAL: treats the fixed deadline as a time-box with variable scope (prioritisation, deferral) and addresses documentation/milestones required by procurement.
Fair weaknesses of both pure approaches must be given.

## 4. [code, fr] Implémentez en Java le patron représenté par la figure (diagramme de classes d'un panier d'achat et de ses mod

Reference:

interface StrategieLivraison { int cout(double poidsKg, int total); }
class LivraisonStandard implements StrategieLivraison {
    public int cout(double poidsKg, int total) { return 1500 + 200 * (int) Math.ceil(poidsKg); }
}
class LivraisonExpress implements StrategieLivraison {
    public int cout(double poidsKg, int total) {
        if (poidsKg > 20) throw new IllegalArgumentException("Colis trop lourd");
        return 3500 + 450 * (int) Math.ceil(poidsKg);
    }
}
class Retrait implements StrategieLivraison {
    public int cout(double poidsKg, int total) { return total >= 50000 ? 0 : 500; }
}
class Panier {
    private final int total; private final double poidsKg; private StrategieLivraison strategie;
    Panier(int total, double poidsKg) { this.total = total; this.poidsKg = poidsKg; }
    void definirStrategie(StrategieLivraison s) {
        if (s == null) throw new IllegalArgumentException("strategie null");
        this.strategie = s;
    }
    int fraisLivraison() {
        if (strategie == null) throw new IllegalStateException("aucune strategie");
        return strategie.cout(poidsKg, total);
    }
}
public class C1 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        Panier p = new Panier(30000, 2.3);
        try { p.fraisLivraison(); throw new AssertionError(); } catch (IllegalStateException e) {}
        p.definirStrategie(new LivraisonStandard()); eq(p.fraisLivraison(), 2100);
        p.definirStrategie(new LivraisonExpress()); eq(p.fraisLivraison(), 3500 + 450 * 3);
        p.definirStrategie(new Retrait()); eq(p.fraisLivraison(), 500);
        Panier q = new Panier(50000, 1); q.definirStrategie(new Retrait()); eq(q.fraisLivraison(), 0); eq(new LivraisonExpress().cout(20, 0), 3500 + 450 * 20); try { new LivraisonExpress().cout(20.5, 0); throw new AssertionError(); } catch (IllegalArgumentException e) {}
    }
}

Essential points:

ESSENTIEL : une interface StrategieLivraison et trois classes qui l'implémentent, Panier qui détient la stratégie par composition et lui délègue le calcul (aucun if ou switch sur le type de livraison).
ESSENTIEL : arithmétique exacte : arrondi au kg supérieur (par exemple 2,3 kg compte pour 3 kg : 1 500 + 600 = 2 100 en standard), express limité à 20 kg inclus (exception au-delà), retrait gratuit à partir de 50 000 inclus, 500 sinon.
ESSENTIEL : definirStrategie refuse null (IllegalArgumentException) ; fraisLivraison sans stratégie lève IllegalStateException.
Accepter des variantes équivalentes (classe abstraite, lambdas) si la logique et les exceptions sont correctes. Le code doit compiler dans l'esprit (syntaxe Java correcte).

## 5. [open, fr] La figure représente la classe GestionnaireScolarité d'une application d'université et les classes dont elle d

Reference:

1) Cohésion très faible : la classe regroupe des responsabilités sans lien (inscription, calcul de moyennes, génération de bulletin PDF, envoi d'e-mails, persistance SQL, import CSV, planification d'examens) : cohésion coïncidente ou logique (« tout ce qui touche à la scolarité »), loin de la cohésion fonctionnelle ; elle viole le principe de responsabilité unique (SRP). Couplage : vers Etudiant, Cours, Note : couplage par données/estampille (objets du domaine passés en paramètres), normal. Vers PdfLib, SmtpClient, MySqlConnection, CsvParser : dépendances directes vers des classes concrètes de technologie, couplage fort (à l'implémentation) qui viole l'inversion de dépendances (DIP).
2) Conséquences : changer de bibliothèque PDF, de serveur SMTP ou de base impose de modifier cette même classe énorme (plusieurs raisons de changer, risque de régression, conflits de fusion dans l'équipe) ; impossible de tester le calcul de moyenne ou l'inscription sans base de données, serveur de mail ni bibliothèque PDF (tests lents, fragiles, pas unitaires).
3) Découpage : InscriptionService (inscrire, règles d'inscription), CalculNotesService (moyenne, seuil d'admission), BulletinService (génération du bulletin) avec une interface BulletinRenderer (implémentation PDF), NotificationService avec une interface Notifier (implémentation SMTP), EtudiantRepository (interface de persistance, implémentation MySQL), ImportNotesService (CSV), PlanificationExamens. Principes : SRP, haute cohésion, séparation des préoccupations, DIP : les services dépendent d'interfaces injectées (injection par constructeur) ; SMTP et MySQL derrière des interfaces (Notifier, Repository) permettent de les remplacer par des faux dans les tests.

Essential points:

ESSENTIEL : identifie la cohésion faible (responsabilités multiples sans lien, SRP) avec des exemples de méthodes de la figure.
ESSENTIEL : distingue le couplage vers les classes du domaine (acceptable) du couplage fort vers les classes techniques concrètes (PdfLib, SmtpClient, MySqlConnection, CsvParser).
ESSENTIEL : au moins deux conséquences pertinentes (modifications multiples, testabilité).
ESSENTIEL : au moins quatre classes ou services avec une responsabilité unique chacune et usage d'interfaces/abstractions injectées pour l'e-mail et la base (DIP).

## 6. [mcq, en] Alice and Bob both work on the shared branch feature/search, already published on the remote. Bob rebases it o

Correct: **C**

- A. Git refuses the pull, because a branch whose history was rewritten by a forced push can only be fetched after the local branch has been deleted
- B. Alice's two local commits are silently discarded, because after a forced update the remote history is authoritative and replaces her own history
- C. Git merges the rewritten commits next to Alice's old copies of them, so each of Bob's changes shows up twice in history and conflicts are likely
- D. The pull fast-forwards cleanly, because Git recognises the rebased commits by their patch content and skips all the duplicates automatically

A rebase creates new commits (new parents, hence new hashes) with the same changes. Alice's branch still contains the old versions of Bob's commits, the remote now holds the rewritten ones: a merge pull joins both histories, so the same changes appear twice and often conflict. Git does not refuse, does not discard local commits and does not deduplicate on a merge (only a pull with rebase can skip patch-equivalent commits). Rule: never rewrite shared history, or use a lease-protected force and tell the team.

## 7. [open, en] Listing 1 is a method that runs in production in an online shop.

Reference:

(a) Smells: long method doing several jobs (pricing, tax, discount, payment, logging, mailing, persistence): SRP violation, lines 2-21. If/else chains on a String code: country (7-9) and payment type (11-16): switch on type, OCP violation, to be replaced by polymorphism/Strategy. Magic numbers (1.1925, 1.20, 1.19, 0.9) lines 7-10. Feature envy / Law of Demeter: line 5 digs into o.items.get(i).price and qty to compute what Order or OrderLine should compute. Primitive obsession: country and payType are Strings. Boolean flag parameter vip (line 2, 10). Public fields accessed directly (o.items, o.cardNo, o.email): data class without behaviour. Hard-wired static collaborators (Bank, PayPal, Mailer, Database) lines 13, 16, 18, 19: DIP violation. Mixing I/O (System.out lines 12, 15) with domain calculation. Duplicated structure between the two payment branches.
(b) Extract Method / Move Method (total computation moves to Order.total() and OrderLine.subtotal()); Replace Conditional with Polymorphism or Strategy (a TaxPolicy per country, a PaymentMethod per type, chosen through a map or factory); Replace Magic Number with Symbolic Constant / Introduce Parameter Object (a Customer or Discount object instead of a boolean); Dependency Injection (constructor injection of interfaces).
(c) Calculation, payment, mail and database are tangled and the collaborators are static or hard-coded, so a test would charge a card, send an email and write to the database. To test the calculation: separate the pure computation (total with tax and discount) into a method or class with no I/O, and inject the collaborators as interfaces (PaymentGateway, Mailer, OrderRepository) so tests use fakes.

Essential points:

ESSENTIAL: at least six valid, distinct smells with correct line references (for example long method/SRP, conditional chains/OCP, magic numbers, feature envy, primitive obsession, boolean flag, hard-wired static dependencies/DIP, I/O mixed with logic).
ESSENTIAL: three refactorings or patterns correctly named (Extract/Move Method, Replace Conditional with Polymorphism or Strategy, Symbolic Constant, Parameter Object, Dependency Injection...) with a correct description of the change.
ESSENTIAL: testability explanation naming the static/hard-coded collaborators and the remedy: dependency injection of abstractions and separation of the pure calculation.

## 8. [mcq, fr] La figure montre un diagramme de classes et un diagramme d'objets censé en être une instance. Une seule affirm

Correct: **A**

- A. L'objet e2 est lié à deux départements alors que la multiplicité côté Département de l'association « emploie » impose exactement un département
- B. L'objet c2 est enseigné par deux enseignants alors que la multiplicité 1..2 portée par Cours interdit qu'un cours ait plusieurs enseignants
- C. Les départements d1 et d2 sont rattachés à la même université alors que la composition interdit qu'une université compose plusieurs départements
- D. L'objet e1 n'enseigne qu'un seul cours alors que la multiplicité 1..2 portée par Cours exige que chaque enseignant en assure exactement deux

Une multiplicité écrite à côté d'une classe donne le nombre d'instances de cette classe liées à une instance de l'autre extrémité. Département porte « 1 » côté « emploie » : chaque enseignant a exactement un département, or e2 est lié à d1 et à d2. Les autres objets respectent le diagramme : 1..* départements par université, 1..* enseignants par cours (c2 : e2 et e3), et 1..2 cours par enseignant (e1 : un cours, e2 : deux, e3 : un).

## 9. [open, fr] La figure montre l'état des dépôts après un incident. Bob a rebasé la branche feature sur la nouvelle pointe d

Reference:

1) L'identité d'un commit est son empreinte (SHA) calculée sur son contenu complet : arbre de fichiers, commit(s) parent(s), auteur, dates et message. Le rebase rejoue les modifications sur un autre parent (M4 au lieu de M2) : le parent change, donc l'arbre et l'empreinte aussi : ce sont de nouveaux commits (B1', B2'), même si le diff est le même.
2) Pull avec fusion : Git récupère B1', B2' ; la base commune des deux lignes est M2 ; la fusion rassemble l'ancienne ligne (B1, B2, A1, A2) et la nouvelle (M3, M4, B1', B2') : les modifications de Bob apparaissent deux fois dans l'historique, des conflits sont probables, et on obtient un commit de fusion inutile. Pull avec rebase : les commits locaux d'Alice sont rejoués sur origin/feature ; Git détecte que B1 et B2 ont le même patch que B1' et B2' et les écarte ; il reste A1' et A2' posés sur B2' (des conflits restent possibles).
3) Règle : ne jamais réécrire l'historique d'une branche déjà publiée et partagée ; réserver le rebase aux branches locales non publiées ; si un forçage est indispensable, utiliser un forçage protégé (force-with-lease), prévenir l'équipe et protéger les branches partagées. Pour récupérer : récupérer (fetch) l'état distant puis rejouer uniquement les commits A1 et A2 sur la nouvelle pointe, par exemple rebase --onto origin/feature B2 (ancienne base) ou cherry-pick de A1 et A2 sur origin/feature, puis pousser normalement.
4) Stratégie : développement sur tronc (trunk-based) ou GitHub flow : main toujours livrable et protégé ; branches de fonctionnalité courtes (un jour ou deux) ; intégration quotidienne ; revue de code par demande de fusion ; intégration continue obligatoire avant la fusion ; drapeaux de fonctionnalité pour livrer du travail inachevé ; pas de branche longue durée.

Essential points:

ESSENTIEL : explique que l'empreinte dépend notamment du parent, que le rebase change le parent et crée donc de nouveaux commits.
ESSENTIEL : décrit correctement le comportement du pull avec fusion (doublons, conflits probables, commit de fusion) et du pull avec rebase (rejeu des commits d'Alice, détection des commits équivalents).
ESSENTIEL : énonce la règle de ne pas réécrire l'historique partagé (et mentionne un forçage protégé) et donne une récupération correcte (rebase --onto ou cherry-pick des seuls commits A1 et A2).
ESSENTIEL : stratégie de branches cohérente avec l'intégration quotidienne (branches courtes, tronc protégé, intégration continue).

## 10. [code, en] Implement in Java the structure shown in the figure.

Reference:

import java.util.*;
import java.util.concurrent.CopyOnWriteArrayList;
interface Observer { void update(double v); }
class Sensor {
    private double value; private final double threshold;
    private final List<Observer> observers = new CopyOnWriteArrayList<>();
    Sensor(double threshold) { this.threshold = threshold; }
    void attach(Observer o) { if (o != null && !observers.contains(o)) observers.add(o); }
    void detach(Observer o) { observers.remove(o); }
    void setValue(double v) {
        this.value = v;
        if (v > threshold) {
            for (Observer o : observers) {
                try { o.update(v); } catch (RuntimeException e) { /* the others still get notified */ }
            }
        }
    }
}
class Dashboard implements Observer { double lastValue; public void update(double v) { lastValue = v; } }
class AlarmLogger implements Observer { int alarms; public void update(double v) { alarms++; } }
public class C2 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        Sensor s = new Sensor(50); Dashboard d = new Dashboard(); AlarmLogger l = new AlarmLogger();
        s.attach(d); s.attach(d); s.attach(null); s.attach(l);
        s.setValue(50); eq(l.alarms, 0);
        s.setValue(60); eq(l.alarms, 1); eq(d.lastValue, 60.0);
        Observer selfRemoving = new Observer() { public void update(double v) { s.detach(this); } };
        Observer bad = v -> { throw new RuntimeException("boom"); };
        s.attach(selfRemoving); s.attach(bad); s.attach(new AlarmLogger());
        s.setValue(70); eq(l.alarms, 2);
        s.setValue(80); eq(l.alarms, 3);
        s.detach(new Dashboard());
    }
}

Essential points:

ESSENTIAL: interface Observer with update(double); Sensor with a collection of observers; Dashboard and AlarmLogger implement update correctly (last value, alarm count).
ESSENTIAL: notification only when v is strictly greater than the threshold (equal does not notify).
ESSENTIAL: attach ignores null and duplicates; detach tolerates unknown observers.
ESSENTIAL: safe iteration: iterating over a copy or using a copy-on-write list so that an observer can detach itself during update without ConcurrentModificationException or skipping observers.
ESSENTIAL: an exception from one observer is caught so the others are still notified.

## 11. [open, fr] La figure donne la spécification de la fonction net(m, fidèle).

Reference:

1) Classes : invalide : m <= 0 (et éventuellement valeur non numérique/absente) ; valides : 0 < m < 50 000 (remise de base 0 %), 50 000 <= m < 200 000 (5 %), m >= 200 000 (10 %), chacune croisée avec fidèle = oui / non. R5 dépend de la remise de base : la remise totale = min(base + 2, 10) pour un fidèle, donc son effet (surtout le plafond à 10 %) n'existe qu'en combinaison avec R2-R4 ; il faut croiser les classes.
2) Valeurs limites et résultats (arrondi à l'entier le plus proche, 0,5 vers le haut) : m = 0 -> erreur « montant invalide » ; m = -1 -> erreur ; m = 0,01 non fidèle -> 0 ; m = 49 999,99 non fidèle -> 50 000 ; m = 49 999,99 fidèle (2 %) -> 49 000 ; m = 50 000 non fidèle (5 %) -> 47 500 ; m = 50 000 fidèle (7 %) -> 46 500 ; m = 199 999,99 non fidèle (5 %) -> 190 000 ; m = 199 999,99 fidèle (7 %) -> 186 000 ; m = 200 000 non fidèle (10 %) -> 180 000 ; m = 200 000 fidèle (12 % plafonné à 10 %) -> 180 000 ; m = 200 001 fidèle -> 180 001 (200 001 x 0,9 = 180 000,9 -> 180 001).
3) Pour m = 200 000 et fidèle : correct : base 10 % + 2 = 12 %, plafonné à 10 % -> 180 000. Si le développeur plafonne la base à 10 % puis ajoute 2 points sans replafonner : 12 % -> 176 000 (erreur). Le cas m = 200 000, fidèle = oui (et tout m >= 200 000 fidèle) révèle l'erreur ; de même m = 190 000 fidèle : 5 % + 2 = 7 % -> 176 700 (pas de plafonnement en jeu).

Essential points:

ESSENTIEL : classes invalides (m <= 0) et trois classes valides croisées avec fidèle ; explication de l'interdépendance R5 / R2-R4 (le plafond dépend de la remise de base).
ESSENTIEL : valeurs limites autour de 0, 50 000 et 200 000 (juste en dessous, sur la limite, juste au-dessus), avec au moins dix cas et des résultats exacts : par exemple 50 000 non fidèle -> 47 500 ; 50 000 fidèle -> 46 500 ; 200 000 non fidèle -> 180 000 ; 200 000 fidèle -> 180 000.
ESSENTIEL : l'exemple d'ordre incorrect doit être chiffré (200 000 fidèle : 180 000 attendu contre 176 000 si le plafond n'est pas appliqué après l'ajout) et désigner le cas qui le détecte.
Vérifier les calculs ; une erreur arithmétique sur un cas non essentiel ne suffit pas à échouer si les autres points sont corrects.

## 12. [mcq, en] In the sequence diagram, the scenario written in the note is executed once. Count every message sent by an arr

Correct: **B**

- A. Four messages: checkout, two reserve and one notifyBackorder, because charge is skipped when at least one item is on backorder
- B. Five messages: checkout, two reserve, one notifyBackorder and one charge, because charge sits outside the loop and is always sent
- C. Six messages: checkout, three reserve, one notifyBackorder and one charge, because every item triggers a reservation attempt first
- D. Seven messages: checkout, two reserve, two notifyBackorder and two charge, because the alternative runs once per item and per branch

The loop runs once for each of the 3 items. In the alt fragment, the first operand runs for the 2 items in stock (reserve) and the else operand runs for the item out of stock (notifyBackorder). charge is after the loop and has no guard, so it is sent once. Total: 1 + 2 + 1 + 1 = 5.

## 13. [open, en] Use the activity network in the figure (durations in days).

Reference:

(1) Forward pass (ES-EF): A 0-3; B 0-4; C 3-5; D 3-8; E 4-7; F 8-12 (waits for D); G 7-13; H 13-15 (waits for G); I 15-18. Backward pass (LS-LF): I 15-18; H 13-15; G 7-13; F 9-13; E 4-7; D 4-9; C 7-9; B 0-4; A 1-4 (min of LS of C and D, which is 4). Critical path B-E-G-H-I (zero float), duration 4+3+6+2+3 = 18 days. The path A-D-F-H-I lasts 17 days.
(2) Total float = LS - ES (or LF - EF): C = 7 - 3 = 4 days; D = 4 - 3 = 1 day. Free float = the delay of an activity that does not delay the earliest start of any successor: C: ES of F (8) - EF of C (5) = 3 days. Total float can be shared with the following activities and delays them; free float only concerns the immediate successors and does not move them.
(3) G shortened by 2 days (4 days): path B-E-G-H-I = 16, path A-D-F-H-I = 17: new duration 17 days, the critical path becomes A-D-F-H-I only. G shortened by 1 day (5 days): both paths last 17 days: duration 17 days with two critical paths (B-E-G-H-I and A-D-F-H-I); no further gain is possible by shortening only G.

Essential points:

ESSENTIAL: critical path B-E-G-H-I and duration 18 days, backed by the forward and backward passes (ES/EF/LS/LF mostly correct).
ESSENTIAL: total floats C = 4 and D = 1, free float of C = 3, with a correct definition of both floats.
ESSENTIAL: G reduced by 2 gives 17 days with critical path A-D-F-H-I; G reduced by 1 gives 17 days with two critical paths.

## 14. [open, fr] Selon la norme de maintenance du logiciel (ISO/IEC 14764), classez chacun des six événements suivants dans la 

Reference:

(1) Corrective : on corrige un défaut constaté. (2) Adaptative : le logiciel est adapté à un changement de son environnement technique (le SGBD). (3) Préventive : on améliore la maintenabilité pour éviter des problèmes futurs, sans changement fonctionnel ni demande. (4) Perfective : amélioration/évolution fonctionnelle demandée par les utilisateurs. (5) Adaptative : adaptation à un changement de l'environnement réglementaire/externe. (6) Préventive : action anticipant une dégradation prévisible avant qu'elle n'apparaisse (une réponse perfective, amélioration de performance, est acceptable si elle est justifiée par l'amélioration de la performance).
La maintenance perfective (évolutive) représente en général la plus grande part du coût (de l'ordre de la moitié) : les utilisateurs demandent sans cesse de nouvelles fonctionnalités et améliorations pendant toute la vie du logiciel, plus que de corrections.

Essential points:

ESSENTIEL : (1) corrective, (2) adaptative, (3) préventive, (4) perfective, (5) adaptative, chacune avec une justification correcte ; (6) préventive acceptée (ou perfective si justifiée par la performance).
ESSENTIEL : la maintenance perfective est la plus coûteuse, avec une explication fondée sur les demandes d'évolution des utilisateurs.
Au plus une erreur de classement parmi (1) à (5) tolérée si la justification est cohérente ; deux erreurs ou plus : incorrect.

## 15. [mcq, fr] Une fonction accepte un âge entier valide dans l'intervalle [18 ; 60[ (18 inclus, 60 exclu). On applique l'ana

Correct: **A**

- A. 17, 18, 19, 58, 59, 60
- B. 17, 18, 19, 59, 60, 61
- C. 18, 19, 20, 57, 58, 59
- D. 16, 17, 18, 59, 60, 61

L'intervalle [18 ; 60[ contient les entiers 18 à 59. La borne basse valide est 18 : on teste 17, 18 et 19. La borne haute valide est 59 : on teste 58, 59 et 60 (60 est la première valeur invalide). Le jeu « 17, 18, 19, 59, 60, 61 » correspond à un intervalle fermé [18 ; 60], celui de « 18, 19, 20, 57, 58, 59 » ne teste aucune valeur invalide et le dernier est décalé d'un cran.

## 16. [code, fr] Implémentez en Java le cycle de vie d'un document avec le patron État, d'après le diagramme d'états de la figu

Reference:

import java.util.*;
interface Etat {
    default Etat soumettre(Document d) { return this; }
    default Etat approuver(Document d, String r) { return this; }
    default Etat rejeter(Document d) { return this; }
    default Etat corriger(Document d) { return this; }
    default Etat retirer(Document d) { return this; }
    default Etat abandonner(Document d) { return this; }
    String nom();
}
class Brouillon implements Etat {
    public Etat soumettre(Document d) {
        if (d.titre() == null || d.titre().trim().isEmpty()) throw new IllegalStateException("titre vide");
        return new EnRelecture();
    }
    public String nom() { return "Brouillon"; }
}
class EnRelecture implements Etat {
    public Etat approuver(Document d, String r) {
        d.relecteurs().add(r);
        return d.relecteurs().size() >= 2 ? new Publie() : this;
    }
    public Etat rejeter(Document d) { return new Rejete(); }
    public String nom() { return "En relecture"; }
}
class Publie implements Etat {
    public Etat retirer(Document d) { d.relecteurs().clear(); return new Brouillon(); }
    public String nom() { return "Publié"; }
}
class Rejete implements Etat {
    public Etat corriger(Document d) { return new Brouillon(); }
    public Etat abandonner(Document d) { return new Abandonne(); }
    public String nom() { return "Rejeté"; }
}
class Abandonne implements Etat {
    private IllegalStateException e() { return new IllegalStateException("document abandonné"); }
    public Etat soumettre(Document d) { throw e(); }
    public Etat approuver(Document d, String r) { throw e(); }
    public Etat rejeter(Document d) { throw e(); }
    public Etat corriger(Document d) { throw e(); }
    public Etat retirer(Document d) { throw e(); }
    public Etat abandonner(Document d) { throw e(); }
    public String nom() { return "Abandonné"; }
}
class Document {
    private final String titre; private final Set<String> relecteurs = new LinkedHashSet<>(); private Etat etat = new Brouillon();
    Document(String titre) { this.titre = titre; }
    String titre() { return titre; }
    Set<String> relecteurs() { return relecteurs; }
    void soumettre() { etat = etat.soumettre(this); }
    void approuver(String r) { etat = etat.approuver(this, r); }
    void rejeter() { etat = etat.rejeter(this); }
    void corriger() { etat = etat.corriger(this); }
    void retirer() { etat = etat.retirer(this); }
    void abandonner() { etat = etat.abandonner(this); }
    String etat() { return etat.nom(); }
}
public class C3 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        Document d = new Document("Cours"); eq(d.etat(), "Brouillon");
        d.approuver("a"); eq(d.etat(), "Brouillon");
        d.soumettre(); eq(d.etat(), "En relecture");
        d.approuver("a"); d.approuver("a"); eq(d.etat(), "En relecture");
        d.approuver("b"); eq(d.etat(), "Publié");
        d.retirer(); eq(d.etat(), "Brouillon");
        d.soumettre(); d.approuver("a"); eq(d.etat(), "En relecture");
        d.rejeter(); eq(d.etat(), "Rejeté"); d.corriger(); eq(d.etat(), "Brouillon");
        d.soumettre(); d.rejeter(); d.abandonner(); eq(d.etat(), "Abandonné");
        try { d.soumettre(); throw new AssertionError(); } catch (IllegalStateException e) {}
        try { new Document(" ").soumettre(); throw new AssertionError(); } catch (IllegalStateException e) {}
    }
}

Essential points:

ESSENTIEL : une abstraction d'état (interface ou classe abstraite) et une classe par état (Brouillon, En relecture, Publié, Rejeté, et un état final Abandonné), Document qui délègue à l'état courant et change d'état ; pas de grand if/switch sur un code d'état dans Document.
ESSENTIEL : transitions correctes : soumettre (titre non vide sinon IllegalStateException) vers En relecture ; approuver avec relecteurs distincts (un ensemble), publication à partir de deux ; rejeter vers Rejeté ; corriger vers Brouillon ; retirer remet à Brouillon et vide les relecteurs ; abandonner vers l'état final.
ESSENTIEL : les événements non prévus sont ignorés dans les états normaux, mais toutes les opérations lèvent IllegalStateException dans l'état abandonné.

## 17. [open, en] The figure is a traceability matrix: the modules each test exercises and the requirements each test verifies. 

Reference:

(1) Tests exercising M3: T2, T3, T4; tests exercising M5: T7, T8. Affected tests: T2, T3, T4, T7, T8. Requirements verified by these tests: R1 (T2), R2 (T2, T3), R3 (T4), R5 (T7, T8): impacted requirements R1, R2, R3, R5.
(2) Minimal suite of three tests: T2 (M3; verifies R1 and R2), T4 (M3, M4; verifies R3) and T7 or T8 (M5; verifies R5). It is minimal because among the tests that touch a changed module R1 is covered only by T2, R3 only by T4, and R5 needs one of T7/T8: three distinct tests are needed (T2 already covers R1 and R2 together), so no suite of two tests can work.
(3) R4 is not verified by any test: a requirements coverage gap (write the missing test). Redundant tests: T6 verifies the same module and requirement as T1 (M1/R1 is already covered by T1), and T5 (M4, R3) is covered by T4: candidates to merge or remove to reduce maintenance and run time, after checking that they do not detect different faults.
(4) The matrix only records direct coverage. A change can break modules that depend on the changed ones (M4 is used by tests T4, T5, T8 and might depend on M3 or M5): you need a dependency or impact analysis (call graph, data flow, changed lines), coverage data by line or function, and risk/defect history to select and prioritise tests; also unchanged modules reached indirectly must be considered.

Essential points:

ESSENTIAL: affected tests T2, T3, T4, T7, T8 and impacted requirements R1, R2, R3, R5.
ESSENTIAL: a minimal suite of three tests (T2, T4, and T7 or T8) with a valid minimality argument.
ESSENTIAL: names the uncovered requirement R4 and a redundancy (T5 or T6) with an appropriate action.
ESSENTIAL: explains that direct coverage in the matrix ignores indirect impact and asks for dependency/impact analysis (call graph, data flow) or similar information.

## 18. [open, fr] Une startup lance une application de transfert d'argent mobile pour des zones rurales : réseaux 2G/3G instable

Reference:

Réponse attendue (exemple, d'autres choix argumentés sont acceptés) : 1) Fiabilité (disponibilité, tolérance aux pannes, récupérabilité) : réseaux instables et argent en jeu : une transaction ne doit jamais être perdue ni exécutée deux fois. 2) Sécurité (confidentialité, intégrité, non-répudiation, authenticité) : argent réel, fraude, réglementation. 3) Utilisabilité (accessibilité, facilité d'apprentissage) : utilisateurs peu alphabétisés : icônes, langues locales, parcours courts. 4) Efficacité de performance (consommation de bande passante, temps de réponse) ou portabilité/compatibilité (téléphones d'entrée de gamme).
Tactiques : fiabilité : opérations idempotentes avec identifiant de transaction, file d'attente locale avec renvoi (store-and-forward), repli USSD/SMS quand les données ne passent pas, journalisation transactionnelle côté serveur. Sécurité : authentification par code PIN lié à l'appareil, chiffrement TLS, journal d'audit infalsifiable, limitation du nombre d'essais, signature des transactions. Performance : messages compacts, compression, cache local, client léger.
Conflit : sécurité contre utilisabilité (authentification forte contre utilisateurs peu alphabétisés) ou sécurité contre performance (chiffrement coûteux sur téléphone modeste) ou fiabilité (renvois) contre exactitude (doublons). Compromis argumenté : authentification adaptée au risque (PIN simple pour les petits montants, confirmation renforcée au-delà d'un seuil) pour rester conforme à la réglementation sans bloquer les utilisateurs.

Essential points:

ESSENTIEL : quatre caractéristiques ISO/IEC 25010 correctement nommées (fiabilité, sécurité, utilisabilité, efficacité de performance, adéquation fonctionnelle, compatibilité, portabilité, maintenabilité) hiérarchisées avec une justification liée au contexte donné ; pour une application financière, l'absence de la sécurité ou de la fiabilité doit être justifiée explicitement.
ESSENTIEL : deux tactiques concrètes et précises (pas des généralités).
ESSENTIEL : un vrai conflit entre deux caractéristiques et une décision de compromis argumentée.

## 19. [mcq, fr] Le système est éteint. Il reçoit successivement les événements power, wake, pause, alarm puis resume. Selon le

Correct: **B**

- A. Lecture, car resume entre dans l'état Actif par son pseudo-état initial et active donc le sous-état par défaut de ce dernier état composite
- B. Pause, car resume entre par l'historique profond H* qui restaure la dernière configuration active, même imbriquée dans un état composite
- C. Veille, car resume réactive l'état composite Allumé depuis son pseudo-état initial, comme après un événement power depuis l'état Éteint
- D. Maintenance, car resume est ignoré tant que l'événement alarm n'a pas été acquitté explicitement dans l'état courant par le système

power : Éteint → Allumé (Veille). wake : Veille → Actif (Lecture, état initial). pause : Lecture → Pause. alarm : on quitte Allumé vers Maintenance, la configuration active (Allumé / Actif / Pause) est mémorisée. resume entre dans Allumé par H*, l'historique profond, qui rétablit toute la configuration imbriquée : Pause. Un historique superficiel (H) aurait restauré Actif puis son état initial Lecture.

## 20. [open, fr] La figure est un diagramme de cas d'utilisation d'une bibliothèque universitaire. Il contient au moins sept er

Reference:

1) La relation « include » est dessinée dans le mauvais sens : la flèche doit aller du cas de base (Emprunter un livre) vers le cas inclus (Vérifier l'adhésion).
2) « extend » : la flèche va du cas de base vers l'extension, alors qu'elle doit aller de l'extension vers le cas de base ; de plus S'authentifier est une étape obligatoire (il faut être authentifié pour emprunter) : c'est une inclusion, pas une extension (comportement optionnel et conditionnel).
3) Une association relie directement deux cas d'utilisation (Emprunter un livre — Rendre un livre) : on ne relie pas deux cas par une association ; les liens entre cas sont include, extend ou la généralisation ; les acteurs participent aux cas par des associations.
4) Une association relie deux acteurs (Membre — Bibliothécaire, « valide ») : le diagramme de cas d'utilisation ne modélise pas les échanges entre acteurs ; on utilise la généralisation entre acteurs s'il y a une hiérarchie de rôles, ou on supprime le lien.
5) Le cas « Payer l'amende » est dessiné hors de la frontière du système : un cas d'utilisation fait partie du système ; seuls les acteurs sont à l'extérieur.
6) « Mettre à jour la table EXEMPLAIRE » est un détail d'implémentation, pas un objectif d'acteur : le cas doit exprimer un but métier (par exemple « Enregistrer un nouvel exemplaire »).
7) « Catalogue des livres » est un nom (un objet), pas une action : un cas d'utilisation se nomme par un verbe d'action vue de l'acteur (« Consulter le catalogue »).

Essential points:

ESSENTIEL : au moins six des sept erreurs, chacune avec une justification et une correction correctes : (1) sens de include ; (2) sens et nature de extend (obligatoire donc include) ; (3) association entre deux cas d'utilisation ; (4) association entre deux acteurs ; (5) cas hors de la frontière du système ; (6) cas trop technique, niveau d'implémentation ; (7) nom de cas qui n'est pas une action.
Une explication fausse pour une erreur ne compte pas pour cette erreur.

## 21. [open, en] Use the use case 'Book a ticket' of the figure. Write its textual specification: actors, preconditions, trigge

Reference:

Actors: Passenger (primary); Payment provider (secondary, used by Pay). Preconditions: the passenger has selected an itinerary and a date; train services exist. Trigger: the passenger asks to book a ticket.
Main scenario (example): 1 Passenger chooses the itinerary and the date. 2 System shows the available trains and fares. 3 Passenger selects a train and the number of passengers. 4 System checks availability and holds the places for a limited time. 5 Passenger confirms the choice. [Extension point: Choose a seat, at step 5; Apply a rail pass, at step 6.] 6 System shows the price. 7 Passenger asks to pay. 8 System runs the included use case Pay (the payment provider authorises the amount). 9 System confirms the booking and issues the ticket. 10 System runs the included use case Send the e-ticket.
Alternative flows: A1 at step 3: the passenger changes the date: back to step 2. A2 at step 5 (extension): the passenger chooses a seat (Choose a seat extends the base use case): the system shows the seat map and records the seat, then step 6 resumes. A3 at step 6 (extension): the passenger applies a rail pass: the system recalculates the price. Exception flows: E1 at step 4: no more places: the system proposes other trains and the use case resumes at step 2 or ends. E2 at step 8: payment refused or times out: the system releases the held places and offers another means of payment or cancels (no ticket issued). E3 at step 10: e-ticket sending fails: the ticket remains available in the account and the system retries.
Postconditions: success: the booking is recorded, places allocated, payment captured, ticket issued and sent. Minimal guarantee: whatever happens, no payment is taken without a ticket and held places are released on abandon.
Change for the 24-hour reservation: Pay is currently a mandatory inclusion, so payment cannot be skipped. It must become optional: Pay becomes an extension (with a condition such as 'pay now') or a separate use case 'Reserve for 24 hours' is added as an alternative flow; Send the e-ticket must then depend on payment (included by Pay or an extension of it), not on Book a ticket, because no ticket is issued without payment; the specification gains an expiry rule and a follow-up use case to pay before expiry.

Essential points:

ESSENTIAL: complete structure: actors, preconditions, trigger, a numbered main scenario of at least eight steps alternating actor and system, at least two alternative and two exception flows tied to step numbers, success postcondition and minimal guarantee.
ESSENTIAL: the included use cases (Pay, Send the e-ticket) are invoked at appropriate steps; the extension points (Choose a seat, Apply a rail pass) are located at steps and handled as optional.
ESSENTIAL: for the 24-hour reservation, recognises that the mandatory inclusion of Pay prevents it and proposes a correct change (Pay becomes an extension or an alternative use case is added) and re-attaches Send the e-ticket to payment.

## 22. [mcq, en] Look at the use case diagram of the online shop. Which statement is correct?

Correct: **D**

- A. Apply coupon always runs after Place order, because an extend arrow points from the base use case towards the extension it triggers
- B. Authenticate is optional, since an include relationship is only followed when a guard condition holds on the base use case concerned
- C. Pay by card and Pay by mobile money both run on every Pay, because a specialisation chains the child use cases one after the other
- D. Place order cannot complete without Authenticate, whereas Apply coupon may be skipped without changing the basic flow of Place order

«include» is mandatory: the included behaviour (Authenticate, Pay) is part of Place order and always executed, so Place order cannot complete without it. «extend» is optional: the arrow goes from the extension (Apply coupon) to the base use case (Place order), the extension may be inserted at an extension point under a condition and the base flow stays complete without it. A generalisation means that Pay by card and Pay by mobile money are two alternative ways to Pay, not a sequence.

## 23. [code, en] Write the JUnit 5 test class for InvoiceReminder.remindOverdue, using hand-written test doubles (no mocking li

Reference:

import org.junit.jupiter.api.*;
import static org.junit.jupiter.api.Assertions.*;
import java.time.LocalDate;
import java.util.*;

public class InvoiceReminderTest {
    private static final LocalDate TODAY = LocalDate.of(2026, 10, 14);

    /** Test double: a clock that always says the same day. */
    static class FixedClock implements Clock {
        public LocalDate today() { return TODAY; }
    }

    /** Spy: records every mail and can be told to fail for some recipients. */
    static class SpyMailer implements Mailer {
        final List<String[]> sent = new ArrayList<>();
        final Set<String> failFor = new HashSet<>();
        public void send(String to, String subject) {
            if (failFor.contains(to)) throw new IllegalStateException("smtp down for " + to);
            sent.add(new String[] { to, subject });
        }
    }

    private SpyMailer mailer;
    private InvoiceReminder reminder;

    private void setUp() {
        mailer = new SpyMailer();
        reminder = new InvoiceReminder(new FixedClock(), mailer);
    }
    private static Invoice invoice(String n, int daysFromToday, boolean paid, String email) {
        return new Invoice(n, TODAY.plusDays(daysFromToday), paid, email);
    }

    @Test @DisplayName("no invoices: nothing is sent and zero is returned")
    public void noInvoicesSendsNothing() {
        setUp();
        int count = reminder.remindOverdue(Collections.emptyList());
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "no mail expected");
    }

    @Test @DisplayName("an invoice due today is not overdue")
    public void dueTodayIsNotOverdue() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F1", 0, false, "a@x.org")));
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "due today must not be reminded");
    }

    @Test @DisplayName("an unpaid invoice due yesterday is reminded once with the expected subject")
    public void unpaidDueYesterdayIsReminded() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F2", -1, false, "b@x.org")));
        assertEquals(1, count);
        assertEquals(1, mailer.sent.size(), "one mail");
        assertEquals("b@x.org", mailer.sent.get(0)[0]);
        assertEquals("Invoice F2 is overdue", mailer.sent.get(0)[1]);
    }

    @Test @DisplayName("a paid invoice is never reminded, even if long overdue")
    public void paidInvoiceIsIgnored() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F3", -90, true, "c@x.org")));
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "paid invoice must not be reminded");
    }

    @Test @DisplayName("several overdue invoices: one mail each, others untouched")
    public void severalOverdueInvoices() {
        setUp();
        List<Invoice> all = Arrays.asList(invoice("F4", -3, false, "d@x.org"), invoice("F5", 2, false, "e@x.org"), invoice("F6", -10, false, "f@x.org"));
        int count = reminder.remindOverdue(all);
        assertEquals(2, count);
        assertEquals("Invoice F4 is overdue", mailer.sent.get(0)[1]);
        assertEquals("Invoice F6 is overdue", mailer.sent.get(1)[1]);
    }

    @Test @DisplayName("a failing mail does not stop the others and is not counted")
    public void failingMailDoesNotStopTheOthers() {
        setUp();
        mailer.failFor.add("g@x.org");
        List<Invoice> all = Arrays.asList(invoice("F7", -1, false, "g@x.org"), invoice("F8", -1, false, "h@x.org"));
        int count = reminder.remindOverdue(all);
        assertEquals(1, count);
        assertEquals(1, mailer.sent.size(), "only the successful mail is recorded");
        assertEquals("h@x.org", mailer.sent.get(0)[0]);
    }

    @Test @DisplayName("a null list is rejected with IllegalArgumentException")
    public void nullListIsRejected() {
        setUp();
        assertThrows(IllegalArgumentException.class, () -> reminder.remindOverdue(null));
    }
}

Essential points:

ESSENTIAL: hand-written test doubles: a Clock fixed on a date and a spy Mailer that records the messages (to, subject) and can throw for chosen recipients; fresh doubles per test (independent tests).
ESSENTIAL: at least seven distinct tests covering the listed cases with correct assertions: nothing sent and 0 for no invoices; due today is NOT overdue (boundary); unpaid due yesterday gives one mail with the exact subject "Invoice <number> is overdue" and count 1; paid overdue gives no mail; mixed list gives mails only for overdue unpaid ones with the right count; a failing mail is skipped, not counted, and the other mail is still sent; null list throws IllegalArgumentException (assertThrows).
ESSENTIAL: assertions check both the returned count and what the spy recorded (recipient and subject), not only one of them.
A correct suite must detect these faulty implementations: treating 'due today' as overdue (<= instead of <), ignoring the paid flag, letting the exception propagate, no null check.

## 24. [open, fr] Comparez le diagramme d'objets au diagramme de classes de la figure. Listez toutes les violations (il y en a a

Reference:

1) t3 est lié aux deux projets p1 et p2 : une tâche est une partie composée d'un seul projet à la fois (composition exclusive ; multiplicité 1 côté Projet).
2) t4 n'appartient à aucun projet : la multiplicité 1 côté Projet impose exactement un projet.
3) t2 est assignée à deux développeurs d1 et d2 : multiplicité 1 côté Développeur (une tâche a exactement un développeur).
4) d1 a quatre tâches (t1, t2, t3, t4) : la multiplicité 0..3 côté Tâche limite un développeur à trois tâches.
5) m1 est une instance de Membre, classe abstraite : une classe abstraite ne peut pas être instanciée.
6) Les équipes e1 et e2 n'ont chacune qu'un seul développeur : multiplicité 2..5 côté Développeur de l'association avec Équipe.
7) Un lien existe entre t1 et e2 (Tâche — Équipe) alors qu'aucune association n'existe entre ces classes : un lien doit être l'instance d'une association.
Contrôle de multiplicités : 2, 3, 4, 6 (et 1 en partie : le nombre de projets d'une tâche). Autres règles : 5 (classe abstraite non instanciable), 7 (lien sans association) et l'exclusivité de la composition pour 1 (une partie ne peut appartenir à deux composites).

Essential points:

ESSENTIEL : au moins sept violations distinctes, chacune avec l'objet ou le lien fautif et la règle enfreinte : t3 dans deux projets (composition exclusive), t4 sans projet, t2 avec deux développeurs, d1 avec quatre tâches, m1 instance d'une classe abstraite, équipes avec un seul développeur (e1, e2), lien t1-e2 sans association.
ESSENTIEL : distingue les violations de multiplicité de celles qui relèvent d'autres règles (classe abstraite, lien sans association, exclusivité de la composition).

## 25. [mcq, fr] À la fin d'un sprint de deux semaines, une user story estimée à 8 points est réalisée à 90 % : le code est écr

Correct: **C**

- A. La compter pour 90 % dans la vélocité du sprint, puis la reporter au sprint suivant avec les 10 % restants estimés à un seul point
- B. Prolonger le sprint de quelques jours jusqu'à satisfaire la Definition of Done, car la durée d'un sprint reste négociable avec le client
- C. La laisser hors de l'incrément et la remettre dans le product backlog, où le Product Owner la réordonne et l'équipe la ré-estime ensuite
- D. La livrer dans l'incrément avec le statut « presque terminé », puisque l'incrément contient le travail déjà intégré à la version cible

Un élément du backlog ne fait partie de l'incrément que s'il respecte la Definition of Done ; sinon il n'est pas livrable et retourne dans le product backlog pour être réordonné par le Product Owner et ré-estimé par l'équipe. Il n'y a pas de crédit partiel en vélocité, la durée d'un sprint est fixe (on ne la prolonge pas) et l'incrément ne peut pas contenir de travail inachevé.

## 26. [open, en] The class diagram in the figure contains at least six design or notation flaws. Identify at least five of them

Reference:

1) OrderLine is a composed part of both Order and Cart: a part in a composition belongs to at most one whole. Fix: keep the composition with Order and give the cart its own part class (CartLine), or make Cart an aggregation/association if lines must be shared (usually convert a cart into an order by creating order lines).
2) Invoice inherits from Order: an invoice is not a kind of order (it fails the 'is-a' test and the Liskov substitution principle): inheritance used for reuse. Fix: association or dependency between Invoice and Order (an invoice is issued for an order), or a common abstraction such as Document.
3) The multiplicity 1..* on the Customer side of 'places' means an order can be placed by several customers: it should be exactly 1 (an order has one customer).
4) Redundant attribute: Order.customer duplicates the association to Customer (association ends already express it) and Customer.customerName duplicates name. Fix: remove the attributes and name the association end (role).
5) Order.totalPrice is derivable from the order lines: it should be a derived attribute (/totalPrice) computed by an operation, not stored redundantly.
6) The attribute email in Customer is public (+): breaks encapsulation. Fix: private attribute with accessor operations.

Essential points:

ESSENTIAL: at least five of the six flaws with a correct explanation and correction: (1) OrderLine part of two composites; (2) Invoice inheriting from Order (is-a / LSP); (3) wrong multiplicity of customers per order; (4) redundant attributes duplicating association or another attribute; (5) stored derived attribute totalPrice; (6) public attribute breaking encapsulation.

## 27. [open, fr] La figure montre un diagramme de séquence du paiement de frais de scolarité, avec le contrat (les opérations) 

Reference:

Défauts : (1) Le message débiter(montant) ne respecte pas la signature Paiement.débiter(montant, compte) : le paramètre compte manque. (2) Les appels synchrones débiter et autoriser retournent un booléen utilisé ensuite (garde [accepté]) mais aucun message de retour n'est dessiné : il manque les retours (booléen) de Banque vers Paiement puis de Paiement vers Portail. (3) Le message archiver() est envoyé à :Banque après la destruction de sa ligne de vie (croix), et Banque n'a pas d'opération archiver dans son contrat : un message ne peut pas être envoyé à un objet détruit ; il faut l'adresser à la bonne classe (par exemple Paiement ou un journal) et avant la destruction, ou supprimer la destruction. (4) Le fragment alt n'a qu'un opérande [accepté] : le cas de refus n'est pas modélisé (il manque un opérande [else] avec un message d'échec à l'étudiant, absent aussi du contrat de Portail). (5) La garde [accepté] ne correspond à aucune valeur retournée visible dans le diagramme.
Scénario corrigé : l'étudiant appelle payer(montant) sur Portail ; Portail appelle débiter(montant, compte) sur Paiement ; Paiement appelle autoriser(compte, montant) sur Banque ; Banque retourne un booléen à Paiement qui le retourne à Portail ; fragment alt : [accepté = vrai] Portail envoie confirmer() à l'étudiant ; [sinon] Portail notifie le refus à l'étudiant (nouvelle opération, par exemple refuser(), à ajouter au contrat) ; l'archivage éventuel est un message à l'objet qui a cette opération, envoyé tant que la ligne de vie existe.

Essential points:

ESSENTIEL : au moins quatre défauts distincts parmi : paramètre compte manquant dans débiter, retours manquants des appels synchrones, message envoyé après destruction / opération absente du contrat de Banque, alt sans branche [else], garde sans valeur retournée.
ESSENTIEL : chaque défaut est accompagné d'une correction correcte.
ESSENTIEL : le scénario corrigé inclut les retours et la branche de refus.

## 28. [code, fr] Implémentez en Java le patron Composite de la figure (système de fichiers).

Reference:

import java.util.*;
abstract class Element {
    protected final String nom; protected Dossier parent;
    Element(String nom) {
        if (nom == null || nom.trim().isEmpty()) throw new IllegalArgumentException("nom vide");
        this.nom = nom;
    }
    abstract long taille();
    abstract List<Fichier> rechercher(String ext);
}
class Fichier extends Element {
    private final long octets; private final String extension;
    Fichier(String nom, long octets, String extension) {
        super(nom);
        if (octets < 0) throw new IllegalArgumentException("octets");
        this.octets = octets; this.extension = extension == null ? "" : extension;
    }
    long taille() { return octets; }
    List<Fichier> rechercher(String ext) {
        String e = ext == null ? "" : ext.startsWith(".") ? ext.substring(1) : ext;
        return extension.equalsIgnoreCase(e) ? Collections.singletonList(this) : Collections.<Fichier>emptyList();
    }
}
class Dossier extends Element {
    private final List<Element> enfants = new ArrayList<>();
    Dossier(String nom) { super(nom); }
    void ajouter(Element e) {
        if (e == null) throw new IllegalArgumentException("null");
        if (enfants.contains(e)) throw new IllegalArgumentException("doublon");
        if (e == this || (e instanceof Dossier && ((Dossier) e).contientDansSousArbre(this))) throw new IllegalArgumentException("cycle");
        if (e.parent != null) e.parent.retirer(e);
        enfants.add(e); e.parent = this;
    }
    void retirer(Element e) { if (enfants.remove(e)) e.parent = null; }
    private boolean contientDansSousArbre(Element cible) {
        for (Element c : enfants) {
            if (c == cible || (c instanceof Dossier && ((Dossier) c).contientDansSousArbre(cible))) return true;
        }
        return false;
    }
    long taille() { long t = 0; for (Element e : enfants) t += e.taille(); return t; }
    List<Fichier> rechercher(String ext) {
        List<Fichier> r = new ArrayList<>();
        for (Element e : enfants) r.addAll(e.rechercher(ext));
        return r;
    }
}
public class C5 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        Dossier r = new Dossier("racine"), a = new Dossier("a"), b = new Dossier("b");
        Fichier f1 = new Fichier("f1", 100, "TXT"), f2 = new Fichier("f2", 50, "pdf"), f3 = new Fichier("f3", 7, "txt");
        r.ajouter(a); a.ajouter(b); a.ajouter(f1); b.ajouter(f2); b.ajouter(f3);
        eq(r.taille(), 157L); eq(r.rechercher(".txt").size(), 2); eq(r.rechercher("PDF").size(), 1);
        try { b.ajouter(r); throw new AssertionError(); } catch (IllegalArgumentException e) {}
        try { a.ajouter(a); throw new AssertionError(); } catch (IllegalArgumentException e) {}
        try { r.ajouter(null); throw new AssertionError(); } catch (IllegalArgumentException e) {}
        try { a.ajouter(f1); throw new AssertionError(); } catch (IllegalArgumentException e) {}
        r.ajouter(f2); eq(b.taille(), 7L); eq(r.taille(), 157L);
        r.retirer(new Fichier("zz", 1, "x")); eq(r.taille(), 157L);
        try { new Fichier(" ", 1, "x"); throw new AssertionError(); } catch (IllegalArgumentException e) {}
    }
}

Essential points:

ESSENTIEL : classe abstraite Élément (nom, taille(), rechercher), Fichier (feuille) et Dossier (composite) ; récursion correcte pour taille() et rechercher() sans cas particulier pour le type.
ESSENTIEL : ajouter refuse null, doublon et tout cycle (élément égal au dossier ou dossier contenant le dossier cible dans son sous-arbre) avec IllegalArgumentException.
ESSENTIEL : un seul parent : l'élément déjà rattaché est retiré de son ancien parent ; retirer ignore l'absent.
ESSENTIEL : recherche d'extension insensible à la casse et acceptant un point initial ; nom vide refusé.

## 29. [mcq, en] Read the multiplicities in the standard way. In a valid system that contains exactly one Student, what are the

Correct: **A**

- A. Between 3 and 30, because the student must take 3 to 30 courses and each link between this student and a course carries one Enrollment
- B. Between 1 and 5, because the student must take 1 to 5 courses and each link between this student and a course carries one Enrollment
- C. Between 3 and 5, because a course holds 3 to 30 students while this student is limited to at most 5 courses of the whole catalogue now
- D. Between 1 and 30, because the association class only exists once the student has taken a course and is capped by the course side too

A multiplicity written next to a class counts the instances of that class linked to one instance of the other end: 3..30 next to Course means that a student takes between 3 and 30 courses, and 1..5 next to Student means that a course has 1 to 5 students. An association class has exactly one instance per link, so a lone student has between 3 and 30 Enrollment objects. (With one student, each of his courses has 1 student: the 1..5 side is respected.)

## 30. [open, en] The figure shows the state machine of a print job.

Reference:

(1) Defects: a) Non-determinism: from Idle two transitions on submit have overlapping guards [pages > 0] and [pages >= 1] (the same condition): both are enabled. Fix: disjoint guards, for example [pages > 0 and printer free] to Printing and [pages > 0 and printer busy] to Queued. b) Incompleteness: no behaviour for submit with pages = 0 (rejection or error transition). c) Archived is unreachable (no incoming transition): add a transition (for example after completion) or remove it. d) Error is a trap state with no outgoing transition and it is not a final state: add reset/clear back to Idle (or a final state). e) Printing has no completion transition: a job can never finish normally: add a 'done' transition to Idle (or Archived). f) Queued has no cancel transition.
(2) Initial state Idle (entry showReady already done). submit(3): leave Idle (no exit action), transition has no effect, entering Printing runs entry / startMotor. cancel: leave Printing: exit / stopMotor, then the transition effect logCancel, then enter Idle: entry / showReady. Order: startMotor, stopMotor, logCancel, showReady.
UML rule: when a transition fires, exit actions of the states left run first (innermost to outermost), then the effect of the transition, then the entry actions of the states entered (outermost to innermost).

Essential points:

ESSENTIAL: at least four defects among: overlapping guards on submit (non-determinism), missing case pages = 0, unreachable Archived, trap state Error, Printing with no completion transition, Queued without cancel; each with a correct fix.
ESSENTIAL: the action order startMotor, stopMotor, logCancel, showReady.
ESSENTIAL: the UML rule exit actions, then transition effect, then entry actions.

## 31. [open, fr] Lisez le diagramme d'activités de la figure (demande de remboursement de frais).

Reference:

1) L'étudiant remplit la demande ; la scolarité vérifie le dossier ; s'il est incomplet, l'étudiant le complète et la demande est revérifiée (boucle) ; s'il est complet, un fork lance en parallèle le passage du dossier à l'état « Validé » (scolarité) et le calcul du montant dû (comptabilité), qui lisent/modifient le même objet dossier ; puis le virement est ordonné par la comptabilité et le processus se termine.
2) Deux fois. Le fork produit deux jetons ; chaque branche dépose son jeton dans le nœud objet « dossier ». La barre qui suit n'a qu'une seule arête entrante : ce n'est donc pas une jointure qui attend plusieurs jetons mais un nœud qui laisse passer chaque jeton ; les deux jetons traversent l'un après l'autre et « Ordonner le virement » s'exécute deux fois : double virement.
3) Les deux activités parallèles accèdent au même objet dossier en même temps : conflit d'accès / mise à jour concurrente (accès concurrent) ; le montant peut être calculé sur un dossier pas encore validé ou une mise à jour peut écraser l'autre ; l'état de l'objet n'est pas précisé.
4) Correction : remplacer la barre par une vraie jointure (join) qui reçoit les arêtes des deux branches, de sorte qu'un seul jeton en sorte ; ou rendre les activités séquentielles ; préciser les états de l'objet dossier ([validé], [calculé]) ou utiliser deux objets distincts, puis une seule arête vers « Ordonner le virement ».

Essential points:

ESSENTIEL : description correcte de la boucle (incomplet -> compléter -> revérifier) et du fork parallèle.
ESSENTIEL : réponse « deux fois » avec l'explication par les jetons (le fork produit deux jetons, la barre après l'objet n'est pas une jointure, chaque jeton traverse).
ESSENTIEL : identifie l'accès concurrent des deux branches au même objet dossier.
ESSENTIEL : correction par une vraie jointure (un seul jeton) ou par une séquentialisation, avec états ou objets distincts.

## 32. [mcq, en] What does the program in Listing 1 print when main runs? (Each print ends with a space; ignore the trailing sp

Correct: **D**

- A. Bready7 Dready7
- B. Bbase Dready7
- C. Bnull7 Dready7
- D. Bnull0 Dready7

new Derived() first runs the Base constructor (after super() and before the field initialisers of Derived). Base calls describe(), which is overridden: it runs on the Derived object whose fields are not initialised yet, so tag is null and n is 0 : describe() returns "null0". Then the initialisers of Derived run (tag = "ready"), the constructor body sets n = 7 and the second print shows "ready7". The program prints Bnull0 Dready7.

## 33. [open, en] Review the deployment diagram of an online shop.

Reference:

Weaknesses: (1) Single points of failure: one web node (web01), one application node (app01) and one database node (db01): the loss of any of them stops the shop. (2) No load balancer and no replication: no horizontal scalability or failover. (3) The browser to web01 link is plain HTTP: no confidentiality or integrity (credentials, sessions, card data exposed). (4) All servers are in one network segment reachable from the Internet, including the database port 5432 and the AJP port 8009: no DMZ / segmentation, a direct attack surface on the database. (5) Tomcat admin console exposed on port 8080 with password-only authentication. (6) Backup: nightly dump to tape with an SCP link: recovery point of 24 hours, no replication, restore never tested, backup in the same environment. (7) No cache/CDN for static content.
Target: nodes in separate zones: a DMZ with two reverse proxy / WAF nodes (stereotype device, executionEnvironment Nginx) behind a load balancer; an application zone with at least two app nodes in a cluster hosting the artifact shop.war; a private data zone with a primary database node and a replica (streaming replication, automatic failover); communication paths stereotyped «HTTPS» from client to proxy, TLS between tiers, database reachable only from the application nodes, the admin console reachable only through a VPN or a bastion; off-site backup with point-in-time recovery and periodic restore tests. Trade-off: high availability and segmentation increase cost and operational complexity (more nodes, replication, monitoring) and may add some latency (extra hop through the proxy, synchronous replication); acceptable against the availability and security requirements of an e-commerce service, or reduce by managed services.

Essential points:

ESSENTIAL: at least five weaknesses tied to visible elements of the figure (single points of failure, plain HTTP, flat/exposed network or database port, exposed admin console, weak backup/recovery point).
ESSENTIAL: a target deployment in UML terms with redundancy (load balancer, several application nodes, replicated database), segmentation (DMZ/private zones) and encrypted paths (HTTPS/TLS).
ESSENTIAL: one justified trade-off (cost, complexity or latency).

## 34. [open, fr] Identifiez le patron de conception de la figure en nommant chaque participant (avec son rôle) et le problème q

Reference:

Patron Décorateur. Rôles : Notifier = composant (interface commune) ; EmailNotifier = composant concret ; NotifierDecorator = décorateur abstrait (implémente Notifier et détient une référence wrapped vers un Notifier) ; SmsDecorator et SlackDecorator = décorateurs concrets. Problème : ajouter des responsabilités (canaux d'envoi) à un objet dynamiquement et de façon combinable, sans modifier sa classe et sans explosion de sous-classes.
Composition : on crée un EmailNotifier, on l'enveloppe dans un SmsDecorator, puis on enveloppe celui-ci dans un SlackDecorator ; le client ne connaît que l'interface Notifier et appelle send sur l'objet le plus externe. Chaque décorateur délègue d'abord à l'objet enveloppé puis ajoute son propre envoi (ou l'inverse selon l'implémentation) : avec la délégation avant le comportement propre, l'ordre des envois est e-mail, SMS puis Slack.
Sous-classes : pour trois canaux combinables, il faudrait une classe par combinaison non vide : 7 (2^3 - 1) classes (davantage si l'ordre compte), qui croît de façon exponentielle ; avec le Décorateur : une interface, une classe concrète, un décorateur abstrait et deux décorateurs : croissance linéaire, et la combinaison est choisie à l'exécution.
Intentions : Décorateur ajoute des responsabilités à un objet en gardant la même interface ; Composite représente une structure arborescente partie-tout (plusieurs enfants) et traite objets simples et composés de façon uniforme ; Proxy contrôle l'accès à un objet (paresse, protection, distance) avec la même interface sans lui ajouter de fonctionnalité métier.
Inconvénients : beaucoup de petits objets, résultats dépendant de l'ordre de composition, identité des objets (le décorateur n'est pas l'objet décoré), configuration et débogage plus difficiles, impossibilité d'enlever facilement un décorateur intérieur.

Essential points:

ESSENTIEL : identifie le Décorateur et nomme correctement tous les rôles (composant, composant concret, décorateur, décorateurs concrets) avec le problème résolu.
ESSENTIEL : explique l'empilement des objets à l'exécution, l'appel de l'objet externe et la délégation en chaîne qui détermine l'ordre.
ESSENTIEL : compare avec les sous-classes (7 combinaisons, croissance exponentielle contre linéaire).
ESSENTIEL : distingue les intentions de Décorateur, Composite et Proxy et donne un inconvénient réel du Décorateur.

## 35. [code, en] Listing 1 computes the monthly pay. Refactor it so that new contract types can be added without modifying exis

Reference:

import java.util.*;
interface ContractPay { double pay(double baseSalary, int overtimeHours); }
class PermanentPay implements ContractPay {
    public double pay(double base, int ot) { return base + ot * (base / 160) * 1.25; }
}
class ContractorPay implements ContractPay {
    public double pay(double base, int ot) { return base + ot * (base / 160); }
}
class InternPay implements ContractPay {
    public double pay(double base, int ot) { return Math.min(base, 50000); }
}
class PayrollCalculator {
    private final Map<String, ContractPay> registry = new HashMap<>();
    PayrollCalculator() {
        register("PERMANENT", new PermanentPay());
        register("CONTRACTOR", new ContractorPay());
        register("INTERN", new InternPay());
    }
    void register(String contract, ContractPay p) { registry.put(contract, p); }
    public double monthlyPay(String contract, double baseSalary, int overtimeHours) {
        ContractPay p = registry.get(contract);
        if (p == null) throw new IllegalArgumentException("Unknown contract " + contract);
        return p.pay(baseSalary, overtimeHours);
    }
}
public class C6 {
    static void near(double a, double b) { if (Math.abs(a - b) > 1e-9) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        PayrollCalculator c = new PayrollCalculator();
        near(c.monthlyPay("PERMANENT", 160000, 10), 160000 + 10 * 1000 * 1.25);
        near(c.monthlyPay("CONTRACTOR", 160000, 10), 170000);
        near(c.monthlyPay("INTERN", 80000, 10), 50000); near(c.monthlyPay("INTERN", 30000, 10), 30000);
        try { c.monthlyPay("X", 1, 1); throw new AssertionError(); } catch (IllegalArgumentException e) { if (!e.getMessage().equals("Unknown contract X")) throw e; }
        c.register("TRAINEE", (b, o) -> 10); near(c.monthlyPay("TRAINEE", 1, 1), 10);
    }
}

Essential points:

ESSENTIAL: an abstraction (interface or abstract class) with three implementations whose formulas are exact: permanent = base + overtime * (base / 160) * 1.25; contractor = base + overtime * (base / 160); intern = min(base, 50000) with no overtime pay.
ESSENTIAL: PayrollCalculator.monthlyPay keeps its signature and selects the implementation through a registry (Map or equivalent) filled at construction or by registration, with no if/else or switch on the contract name.
ESSENTIAL: unknown contract throws IllegalArgumentException with the message "Unknown contract " + name.
ESSENTIAL: adding a new contract type must only need a new class and its registration (a way to register is provided or evident).

## 36. [mcq, en] Module A calls a function of module B and passes a boolean parameter whose value decides which internal branch

Correct: **C**

- A. Stamp coupling through the flag parameter, plus content coupling through the shared global configuration table
- B. Data coupling through the flag parameter, plus external coupling through the shared global configuration table
- C. Control coupling through the flag parameter, plus common coupling through the shared global configuration table
- D. Control coupling through the flag parameter, plus content coupling through the shared global configuration table

Passing a flag that controls the internal logic of the called module is control coupling (B must know how A wants it to behave). Several modules sharing a global data area is common coupling. Content coupling would mean that A reaches into B's internals (modifies its private data or jumps into its code), stamp coupling means sharing a composite structure of which only a part is used, data coupling passes only the needed elementary data, and external coupling concerns a shared external format or device.

## 37. [open, fr] Le listing 1 contient deux fragments de code. Pour chacun : (1) nommez le principe SOLID violé en le justifian

Reference:

Fragment 1 (Machine / OldPrinter) : principe de ségrégation des interfaces (ISP) : l'interface Machine est trop large (print, scan, fax) et OldPrinter doit implémenter scan et fax qu'il ne sait pas faire, en levant UnsupportedOperationException ; c'est aussi une violation du principe de substitution de Liskov : un OldPrinter n'est pas utilisable partout où une Machine est attendue sans exceptions inattendues. Conséquence : les clients découvrent l'erreur à l'exécution ; le code appelant doit faire des tests de type ; l'ajout d'une opération dans Machine impose de modifier tous les implémenteurs. Correction : scinder en interfaces spécifiques Printer, Scanner, Fax ; OldPrinter implémente seulement Printer ; une MultiFunctionMachine implémente les trois.
Fragment 2 (ReportService) : principe d'inversion des dépendances (DIP) : le service instancie directement MySqlRepository avec new : il dépend d'une classe concrète de bas niveau, impossible de changer de base ou de tester sans MySQL ; principe ouvert/fermé (OCP) : la chaîne de if sur le format oblige à modifier export à chaque nouveau format ; principe de responsabilité unique (SRP) : la même classe lit les données et gère le formatage. Correction : injecter une interface (Repository) par le constructeur ; définir une interface ReportWriter avec les implémentations PdfWriter et CsvWriter choisies par un registre ou une fabrique, export délègue ; la classe ne change pas pour un nouveau format.

Essential points:

ESSENTIEL : fragment 1 : ISP (interface trop large, méthodes non supportées qui lèvent une exception) avec mention de la violation de LSP, conséquence et correction par scission des interfaces.
ESSENTIEL : fragment 2 : au moins DIP (new MySqlRepository, dépendance concrète) et OCP (chaîne de if sur le format) identifiés avec l'élément précis du listing ; SRP accepté en complément.
ESSENTIEL : corrections par abstraction injectée (DIP) et stratégie par format (OCP), avec conséquences correctes.

## 38. [open, fr] Le listing 1 compile. Donnez la sortie exacte (quatre lignes) de la méthode main et justifiez chaque ligne ave

Reference:

Sortie : « animal A », « A.dog », « D.animal », « animal/D.obj ».
Ligne 1 : l'accès à un champ et l'appel d'une méthode statique sont résolus à la compilation sur le type statique (Animal) : a.name vaut « animal » (le champ name de Dog masque celui d'Animal mais n'est pas polymorphe) et a.kind() appelle Animal.kind() : « A ».
Ligne 2 : le type statique de a est Animal ; les méthodes applicables sont sound(Object) et sound(Dog) (l'argument d est un Dog) ; la plus spécifique, sound(Dog), est choisie à la compilation ; Dog ne la redéfinit pas : « A.dog ».
Ligne 3 : le type statique de d est Dog ; l'argument a a le type statique Animal ; les méthodes applicables à un Animal sont sound(Object) et sound(Animal), déclarée dans Dog ; la plus spécifique est sound(Animal) : « D.animal ».
Ligne 4 : who() est définie dans Animal : name y désigne le champ d'Animal (liaison statique) : « animal » ; dans Animal, this a le type statique Animal : seule sound(Object) est applicable, c'est elle qui est choisie à la compilation ; à l'exécution l'objet est un Dog qui redéfinit sound(Object) : liaison dynamique : « D.obj ».

Essential points:

ESSENTIEL : les quatre lignes exactes : animal A / A.dog / D.animal / animal/D.obj.
ESSENTIEL : explique la liaison statique des champs et des méthodes statiques (type statique), le choix de la surcharge à la compilation selon le type statique des arguments (sound(Dog) pour la ligne 2, sound(Animal) pour la ligne 3), et la liaison dynamique de sound(Object) à la ligne 4.

## 39. [mcq, fr] Le listing 1 présente une fonction et un jeu de deux tests, T1 (huit ans, non membre) et T2 (trente ans, membr

Correct: **B**

- A. Ils satisfont la couverture des conditions et des décisions, car chaque condition atomique de la fonction reçoit les deux valeurs de vérité
- B. Ils satisfont la couverture des décisions (branches), mais pas celle des conditions : age >= 12 n'est jamais évalué à faux à la ligne 4
- C. Ils ne satisfont que la couverture des instructions, car la décision de la ligne 4 n'est jamais évaluée à faux par aucun des deux tests
- D. Ils satisfont le critère MC/DC, car chaque condition de la ligne 4 est vue modifiant à elle seule le résultat de la décision entière

Instructions : T1 exécute la ligne 3, T2 la ligne 4 et la décrémentation : tout est couvert. Décisions : la ligne 3 est vraie avec T1 et fausse avec T2 ; la ligne 4 est fausse avec T1 (membre = faux) et vraie avec T2 : 100 % des branches. Conditions : avec T1, membre vaut faux et l'évaluation court-circuite, donc age >= 12 n'est jamais évalué à faux (et n'est évalué que par T2, à vrai) : la couverture des conditions n'est pas atteinte, et a fortiori pas MC/DC.

## 40. [open, en] Using the class diagram in the figure, assign each of the following responsibilities to the class that should 

Reference:

R1: Order creates OrderLine (Creator: Order contains/aggregates its lines, has the data to initialise them and uses them closely; composition relation). Wrong: the user interface or a manager creating lines couples them to the Order internals.
R2: OrderLine computes its subtotal (Information Expert: it knows the quantity and has a link to the Product that knows the unit price; it asks Product for the price). Wrong: Order computing line subtotals digs into the lines' data (feature envy).
R3: Order computes the total (Information Expert: it owns the lines) and asks the Promotion to adjust it; with several promotion kinds, Polymorphism (a Promotion abstraction with variants) removes conditionals. Wrong: a controller or UI computing the total duplicates logic and exposes the structure.
R4: Catalog finds the Product by code (Information Expert: it aggregates the products; or a Pure Fabrication repository to keep Product free of persistence); Low Coupling. Wrong: a static find on Product, or the UI scanning products, mixes lookup with the entity.
R5: Product reduces its own stock (Information Expert: it owns the stock attribute), triggered through Order and OrderLine confirming (Low Coupling, respect of the Law of Demeter); invariants (stock >= 0) protected in one place. Wrong: Order changing product.stock directly breaks encapsulation and spreads the stock rule.
OrderManager: a 'god class' with low cohesion (several unrelated responsibilities), high coupling with all other classes, which turns the domain classes into passive data holders (anemic domain model) and goes against Information Expert, High Cohesion and Low Coupling; it is also hard to test and to extend.

Essential points:

ESSENTIAL: correct allocation: R1 Order (Creator), R2 OrderLine (Information Expert), R3 Order with Promotion (Information Expert, Polymorphism for promotions), R4 Catalog (Information Expert or Pure Fabrication), R5 Product (Information Expert) reached through Order/OrderLine with Low Coupling.
ESSENTIAL: for each, a relevant consequence of the wrong allocation (coupling, duplication, broken encapsulation).
ESSENTIAL: rejects OrderManager as a god class with low cohesion, high coupling and an anemic domain model.

## 41. [code, fr] Implémentez en Java les classes Periode, Reservation et Chambre de la figure, en respectant les invariants.

Reference:

import java.time.LocalDate;
import java.util.*;
final class Periode {
    private final LocalDate debut, fin;
    Periode(LocalDate debut, LocalDate fin) {
        if (debut == null || fin == null) throw new IllegalArgumentException("null");
        if (!debut.isBefore(fin)) throw new IllegalArgumentException("debut >= fin");
        this.debut = debut; this.fin = fin;
    }
    boolean chevauche(Periode o) { return debut.isBefore(o.fin) && o.debut.isBefore(fin); }
    LocalDate debut() { return debut; } LocalDate fin() { return fin; }
    @Override public boolean equals(Object o) { return o instanceof Periode && ((Periode) o).debut.equals(debut) && ((Periode) o).fin.equals(fin); }
    @Override public int hashCode() { return Objects.hash(debut, fin); }
    @Override public String toString() { return debut + " -> " + fin; }
}
final class Reservation {
    private final String client; private final Periode periode;
    Reservation(String client, Periode p) {
        if (client == null || client.trim().isEmpty()) throw new IllegalArgumentException("client");
        if (p == null) throw new IllegalArgumentException("periode");
        this.client = client; this.periode = p;
    }
    Periode periode() { return periode; } String client() { return client; }
}
class Chambre {
    private final String numero; private final List<Reservation> reservations = new ArrayList<>();
    Chambre(String numero) { this.numero = numero; }
    synchronized boolean estLibre(Periode p) {
        for (Reservation r : reservations) if (r.periode().chevauche(p)) return false;
        return true;
    }
    synchronized Reservation reserver(String client, Periode p) {
        if (!estLibre(p)) throw new IllegalStateException("chambre occupée");
        Reservation r = new Reservation(client, p); reservations.add(r); return r;
    }
    synchronized List<Reservation> reservations() { return Collections.unmodifiableList(new ArrayList<>(reservations)); }
}
public class C7 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) throws Exception {
        LocalDate d10 = LocalDate.of(2026, 10, 10), d12 = d10.plusDays(2), d15 = d10.plusDays(5);
        Periode a = new Periode(d10, d12), b = new Periode(d12, d15), c = new Periode(d10.plusDays(1), d15);
        eq(a.chevauche(b), false); eq(a.chevauche(c), true); eq(a, new Periode(d10, d12)); eq(a.hashCode(), new Periode(d10, d12).hashCode());
        try { new Periode(d12, d12); throw new AssertionError(); } catch (IllegalArgumentException e) {}
        Chambre ch = new Chambre("12"); ch.reserver("Awa", a); ch.reserver("Paul", b);
        try { ch.reserver("Luc", c); throw new AssertionError(); } catch (IllegalStateException e) {}
        try { ch.reservations().add(null); throw new AssertionError(); } catch (UnsupportedOperationException e) {}
        // concurrency: 50 threads fight for the same period, exactly one wins
        Chambre ch2 = new Chambre("7"); final int[] wins = {0}; List<Thread> ts = new ArrayList<>();
        Periode p = new Periode(d10, d15);
        for (int i = 0; i < 50; i++) { Thread t = new Thread(() -> { try { ch2.reserver("c", p); synchronized (wins) { wins[0]++; } } catch (IllegalStateException e) {} }); ts.add(t); t.start(); }
        for (Thread t : ts) t.join();
        eq(wins[0], 1); eq(ch2.reservations().size(), 1);
    }
}

Essential points:

ESSENTIEL : Periode immuable (final, pas de setter) avec validation (null, debut >= fin), chevauche correct avec fin exclue (debut < autre.fin et autre.debut < fin), equals/hashCode cohérents.
ESSENTIEL : Reservation immuable avec validations.
ESSENTIEL : Chambre.reserver vérifie la disponibilité et enregistre de façon atomique (méthode synchronisée, verrou ou structure concurrente équivalente), lève IllegalStateException en cas de chevauchement ; estLibre cohérent avec reserver.
ESSENTIEL : reservations() ne laisse pas modifier la liste interne (vue non modifiable ou copie défensive).

## 42. [mcq, en] The activity diagram is executed for a standard (non-express) order. What happens?

Correct: **D**

- A. Both courier and postal are booked, because the fork duplicates the token and the decision node only filters one of the two copies
- B. The order ships as soon as the postal booking is done, because a join fires when any one of its incoming edges holds a token at all
- C. The order ships once after the invoice and the postal booking, because a join only waits for the edges that can still produce a token
- D. The flow blocks at the join, which waits for a token on each of its three incoming edges, so Ship order is never reached by the flow

The fork creates two tokens. One goes to Prepare invoice. The other reaches the decision node, which sends it to exactly one of the two branches (Book postal here). The join bar has three incoming edges and, in UML, a join fires only when a token is available on every incoming edge: the edge coming from Book courier never receives one, so the join waits forever and Ship order is never executed (a deadlock). The fix is a merge node that brings the two alternative branches back into one edge before the join.

## 43. [open, fr] Cahier des charges d'une agence de location de logements à Yaoundé.

Reference:

Classes : Personne (nom, téléphone) ; Logement (adresse, ...) ; Bail (dateDebut, dureeMois, loyerMensuel) ; Paiement (mois, montant, date).
Associations : Personne (rôle propriétaire) 1 — 0..* Logement (un logement a exactement un propriétaire, une personne peut en posséder plusieurs ou aucun) ; Logement 1 — 0..* Bail (historique des baux d'un logement ; contrainte : au plus un bail actif par logement à un instant donné) ; Personne (rôle locataire) 1 — 0..* Bail ; Bail 0..* — 1..2 Personne (rôle garant) (un bail a un ou deux garants, un garant peut garantir plusieurs baux) ; Bail composé de 0..* Paiement (composition : un paiement n'existe pas sans bail, il disparaît avec lui ; multiplicité 1 côté Bail).
Choix difficiles : 1) Propriétaire, locataire et garant ne sont pas trois sous-classes de Personne : la généralisation est une classification statique et unique, or une personne peut être à la fois propriétaire et garant (classification multiple) et ses rôles changent au cours du temps ; on modélise donc des rôles (noms de rôle sur les extrémités d'association) ou un patron rôle, avec une seule classe Personne. 2) Bail est une classe à part entière et non une classe d'association entre Logement et Personne : une classe d'association n'a qu'une instance par couple de participants, alors que le même locataire peut louer plusieurs fois le même logement (historique) et un bail a ses propres associations (garants, paiements). 3) Le paiement est lié par composition au bail (cycle de vie dépendant) ; la règle « un seul bail actif » et « le locataire n'est pas son propre garant » s'exprime par des contraintes (notes ou OCL).

Essential points:

ESSENTIEL : une seule classe Personne avec des rôles (propriétaire, locataire, garant) justifiée par la classification multiple/dynamique, et non trois sous-classes exclusives.
ESSENTIEL : Bail modélisé comme classe (pas classe d'association), justifié par l'historique, les baux répétés entre mêmes parties et ses propres associations.
ESSENTIEL : multiplicités correctes : propriétaire 1 — 0..* logements ; logement 1 — 0..* baux ; locataire 1 — 0..* baux ; bail avec 1..2 garants ; composition bail — 0..* paiements.
ESSENTIEL : contrainte d'un seul bail actif par logement mentionnée.

## 44. [open, en] In the figure, ThesisStudent inherits from both Student and Employee, which both inherit from Person.

Reference:

(1) Problems: the diamond problem: ThesisStudent inherits Person twice (through Student and through Employee): without special handling there are two copies of name and id, or an ambiguity about which one is used; ambiguity of getId(): Student and Employee each override it with different meanings (student number or employee number), so a call on ThesisStudent is ambiguous and has no single meaning; semantic problem: a thesis student is one person, but the model creates one object with two identities; static classification: the class of an object is fixed at creation, so a student who becomes an employee cannot change class without creating a new object and losing identity.
(2) Java and C# only allow single inheritance of classes (they allow several interfaces): a class cannot extend two classes; default interface methods with the same signature must be resolved explicitly. C++ allows multiple inheritance of classes and solves the duplication with virtual inheritance (Person as a virtual base shares a single sub-object), the ambiguity of getId() has to be resolved by an override in ThesisStudent or by explicit qualification.
(3) Alternative A: interfaces and composition: IStudent and IEmployee interfaces; ThesisStudent implements both and delegates to composed Student and Employee components (composition over inheritance). Alternative B: roles: one Person class with 0..1 StudentRole and 0..1 EmployeeRole objects (role pattern / role classes), each carrying its own attributes and operations. Recommendation: roles, because they allow dynamic classification: roles can be added or removed at run time, the person keeps a single identity (and getId() lives on the person, with role-specific numbers in the roles), a student who becomes an employee just gains a role, and a ThesisStudent is a person who has both roles at the same time.

Essential points:

ESSENTIAL: at least two problems among diamond problem/duplicated Person state, ambiguity of getId(), identity of a single person, static classification.
ESSENTIAL: Java and C# forbid multiple class inheritance (interfaces allowed) and C++ allows it with virtual inheritance.
ESSENTIAL: two alternatives (interfaces + composition/delegation, and roles) and a recommendation of roles justified by dynamic classification and a single identity.

## 45. [mcq, fr] Quelle affirmation sur la dette technique et le refactoring est exacte ?

Correct: **B**

- A. Le refactoring consiste à ajouter des fonctionnalités en réorganisant le code, et la dette technique ne désigne que les défauts encore ouverts
- B. Le refactoring modifie la structure interne sans changer le comportement observable ; il n'est sûr que s'il s'appuie sur des tests automatisés
- C. La dette technique disparaît dès que le code passe tous ses tests, car elle mesure le nombre de tests manquants plutôt que la conception
- D. Un refactoring est sûr dès que le code recompile, puisque le compilateur garantit la conservation du comportement observable du programme

Un refactoring (Fowler) est une transformation de la structure interne qui préserve le comportement observable ; ce sont les tests automatisés qui permettent de le vérifier, la compilation ne prouve rien sur le comportement. Il n'ajoute pas de fonctionnalités. La dette technique est le coût futur des choix de conception ou de code rapides ou dégradés : elle ne se mesure pas au nombre de défauts ouverts ni de tests, et elle ne disparaît pas parce que les tests passent.

## 46. [open, fr] Le diagramme de composants montre l'assemblage de AppMobile, Boutique et PasserellePaiement.

Reference:

1) Non, l'assemblage n'est pas valide. Le composant Boutique requiert IPaiement (payer(montant) retourne un booléen) alors que PasserellePaiement fournit IPaiementV2, un autre type : la signature de payer diffère (paramètre devise en plus) et son type de retour est différent (Reçu au lieu d'un booléen) ; de plus IPaiementV2 ajoute annuler(reçu). IPaiementV2 n'est pas un sous-type de IPaiement (il ne peut pas le remplacer : une opération de même nom mais de signature différente n'est pas une redéfinition), donc la prise ne peut pas se brancher sur la boule.
2) Un test de contrat vérifie : les noms des opérations, les types et l'ordre des paramètres, les types de retour, les préconditions et postconditions, les exceptions, le protocole d'appel (l'ordre des appels autorisés) et les propriétés non fonctionnelles convenues (délais, idempotence).
3) Réparation A : ajouter un composant adaptateur (par exemple AdaptateurPaiementV1) qui fournit IPaiement, requiert IPaiementV2 et convertit les appels (devise par défaut, conversion Reçu vers booléen) ; il est déployé avec la boutique ou avec la passerelle. Réparation B : faire évoluer Boutique pour qu'elle requière IPaiementV2 (ce qui oblige à modifier et redéployer la boutique). Réparation C (variante) : la passerelle fournit les deux interfaces (IPaiement et IPaiementV2) pendant une période de transition (versionnement). Recommandation avec quatre autres boutiques clientes de l'ancienne interface : conserver la rétrocompatibilité : la passerelle continue de fournir IPaiement (ou un adaptateur) et la nouvelle interface est ajoutée à côté ; on ne casse pas les clients existants et on migre progressivement.
4) Interface fournie : ce que le composant offre aux autres (boule) ; requise : ce dont il a besoin d'un autre composant (prise). Ajouter une opération à une interface est compatible pour les clients existants (si elle est une extension) ; modifier la signature d'une opération existante est une rupture de compatibilité : les clients existants ne compilent plus ni ne fonctionnent.

Essential points:

ESSENTIEL : conclut que l'assemblage est invalide en citant les différences précises (paramètre devise, type de retour Reçu contre booléen, absence de sous-typage entre IPaiementV2 et IPaiement).
ESSENTIEL : contenu d'un test de contrat (signatures, types, pré/postconditions, exceptions, protocole).
ESSENTIEL : au moins deux réparations dont un composant adaptateur ou une double interface, avec impact sur le diagramme et le déploiement, et une recommandation justifiée par les quatre boutiques existantes (rétrocompatibilité).
ESSENTIEL : définitions correctes d'interface fournie et requise et distinction entre extension compatible et modification de signature incompatible.

## 47. [code, en] Write the PostgreSQL DDL that persists the class diagram with the class table inheritance strategy: one table 

Reference:

-- Class table inheritance: one table for the abstract superclass, one table per concrete subclass sharing its primary key.
CREATE TABLE owner (
    id         BIGSERIAL PRIMARY KEY,
    full_name  VARCHAR(120) NOT NULL,
    email      VARCHAR(190) NOT NULL UNIQUE
);

CREATE TABLE vehicle (
    id            BIGSERIAL PRIMARY KEY,
    vehicle_type  CHAR(1)      NOT NULL CHECK (vehicle_type IN ('C', 'T', 'M')),   -- discriminator: Car, Truck, Motorbike
    plate         VARCHAR(7)   NOT NULL UNIQUE CHECK (plate ~ '^[A-Z]{2}[0-9]{3}[A-Z]{2}$'),
    brand         VARCHAR(60)  NOT NULL,
    year          SMALLINT     NOT NULL CHECK (year >= 1950 AND year <= 2100),      -- the upper bound "current year + 1" cannot be a CHECK
                                                                                      -- (CURRENT_DATE is not immutable): a BEFORE INSERT/UPDATE
                                                                                      -- trigger, or the application, enforces it
    owner_id      BIGINT       NOT NULL REFERENCES owner (id),                       -- exactly one owner; an owner may own none
    UNIQUE (id, vehicle_type)                                                         -- target of the composite foreign keys below
);

CREATE TABLE car (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'C' CHECK (vehicle_type = 'C'),
    doors         SMALLINT NOT NULL CHECK (doors BETWEEN 2 AND 5),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);

CREATE TABLE truck (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'T' CHECK (vehicle_type = 'T'),
    payload_kg    NUMERIC(10,2) NOT NULL CHECK (payload_kg > 0),
    axles         SMALLINT NOT NULL CHECK (axles >= 2),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);

CREATE TABLE motorbike (
    id            BIGINT PRIMARY KEY,
    vehicle_type  CHAR(1) NOT NULL DEFAULT 'M' CHECK (vehicle_type = 'M'),
    engine_cc     INTEGER NOT NULL CHECK (engine_cc > 0),
    FOREIGN KEY (id, vehicle_type) REFERENCES vehicle (id, vehicle_type) ON DELETE CASCADE
);

Essential points:

ESSENTIAL: tables owner, vehicle, car, truck and motorbike with primary keys; subtype tables use the vehicle id as primary key and as foreign key to vehicle with ON DELETE CASCADE.
ESSENTIAL: vehicle.owner_id NOT NULL with a foreign key to owner; owner.email UNIQUE; vehicle.plate UNIQUE with a CHECK on the regular expression ^[A-Z]{2}[0-9]{3}[A-Z]{2}$; year CHECK (fixed bounds, with the current-year limit handled by a trigger or the application, explained).
ESSENTIAL: exclusive subtypes: a discriminator column in vehicle, a UNIQUE (id, discriminator) key, and in each subtype table a discriminator column fixed by a CHECK to its value and a composite foreign key (id, discriminator) to vehicle.
ESSENTIAL: attribute CHECK constraints: doors BETWEEN 2 AND 5, payload > 0, axles >= 2, engine capacity > 0.
Valid PostgreSQL syntax is expected; a small syntax slip that does not change the meaning is acceptable.

## 48. [open, en] Explain the Model-Driven Architecture (MDA) viewpoints CIM, PIM and PSM. Then classify each of the following a

Reference:

CIM (Computation Independent Model): the business or domain view of the system and its requirements, without computing details (vocabulary, processes, use cases). PIM (Platform Independent Model): the design of the system's structure and behaviour without any platform-specific technology. PSM (Platform Specific Model): the PIM refined with the details of a target platform (Java EE, .NET, a relational DBMS...).
(a) CIM: written with domain experts, describes what the system does for its users without computational details. (b) PIM: a technology-free design model. (c) PSM: it uses a Java EE profile and persistence annotations, so it is tied to a platform. (d) The generated DDL is code/text derived from the PSM, below the PSM level (a code artifact of the platform, not a model).
Model-to-model (M2M): transforms one model into another model, possibly of another metamodel, by rules (for example QVT or ATL): applies from (b) PIM to (c) PSM. Model-to-text (M2T): generates textual artifacts (source code, DDL, configuration) from a model with templates: applies from (c) PSM to (d).
Benefits: portability and platform independence of the design; productivity and consistency of generated code; traceability from requirements to code; documentation always synchronised with the model. Limits: tool maturity and lock-in; the cost of writing and maintaining the transformations; difficulty of round-trip synchronisation between modified code and models; models can become as complex as the code; generated code that is hard to read and debug; need for skills.

Essential points:

ESSENTIAL: correct definitions of CIM, PIM and PSM.
ESSENTIAL: classification: (a) CIM, (b) PIM, (c) PSM, (d) generated code/text (a code artifact below the PSM), with justification.
ESSENTIAL: model-to-model (PIM to PSM) and model-to-text (PSM to code) correctly described and applied.
ESSENTIAL: two genuine benefits and two genuine limits.

## 49. [mcq, en] In the package diagram, domain.billing and domain.shipping depend on each other. domain.billing changes often,

Correct: **C**

- A. Declare an interface in domain.billing that RateTable implements, so that billing uses its own abstraction while shipping keeps depending on billing
- B. Replace the two dependency arrows by one bidirectional association, so that the modelling tool no longer reports the packages as a cycle at all
- C. Declare an interface in domain.shipping that Invoice implements, so that shipping uses its own abstraction and billing now depends on shipping
- D. Move Invoice into domain.shipping and RateTable into domain.billing, so that each package keeps one dependency on the other package as before

The goal is to invert the dependency that goes from shipping to billing (Dependency Inversion + Stable Dependencies): shipping defines the abstraction it needs (for instance an interface that gives the amount to ship), Invoice implements it, so the arrow now goes from billing to shipping and the cycle disappears. The second option inverts the wrong arrow (shipping would still depend on billing). A bidirectional association hides nothing and keeps the cycle. Swapping the classes only moves the cycle.

## 50. [open, fr] Le diagramme de communication de la figure utilise une numérotation décimale.

Reference:

1) Ordre d'exécution (numérotation décimale) : 1 ajouterLivre(isbn) du Client vers le Panier ; 1.1 chercher(isbn) du Panier vers le Catalogue ; 1.2 réserver(isbn) du Panier vers le Stock ; 1.2.1 vérifier(isbn) du Stock vers le Catalogue (imbriqué dans la réservation) ; 2 valider() du Client vers le Panier ; 2.1 calculerTotal() du Panier vers lui-même ; 2.2 émettre(panier) du Panier vers la Facturation.
2) :Stock envoie vérifier(isbn) (message 1.2.1) au Catalogue, pendant l'exécution de réserver (1.2), après 1.1 et avant la fin du message 1 et avant 2 : c'est un appel imbriqué dans 1.2 (le numéro 1.2.1 indique qu'il est déclenché par le traitement du message 1.2).
3) Un diagramme de séquence met en évidence l'ordre chronologique, les durées d'activation (barres), les retours, les fragments (alt, loop, opt) et la durée de vie des objets, que le diagramme de communication ne montre qu'à travers la numérotation. Inversement, le diagramme de communication met en évidence la structure : quels objets sont reliés, la topologie des collaborations et le contexte d'interaction (liens), moins visibles dans la séquence.
4) 2.1 est un message de l'objet à lui-même (auto-message, appel d'une opération de :Panier sur :Panier) : il est dessiné par une boucle sur l'objet, sans lien vers un autre objet.

Essential points:

ESSENTIEL : la séquence exacte 1, 1.1, 1.2, 1.2.1, 2, 2.1, 2.2 avec l'émetteur et le récepteur de chaque message.
ESSENTIEL : 1.2.1 vérifier(isbn) est émis par Stock vers Catalogue, imbriqué dans 1.2.
ESSENTIEL : comparaison correcte : la séquence montre le temps/l'ordre/les activations/les fragments, la communication montre la structure des liens entre objets.
ESSENTIEL : 2.1 est un auto-message du Panier.

