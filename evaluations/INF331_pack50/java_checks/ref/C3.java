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
