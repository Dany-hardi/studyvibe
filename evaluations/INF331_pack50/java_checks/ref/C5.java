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
