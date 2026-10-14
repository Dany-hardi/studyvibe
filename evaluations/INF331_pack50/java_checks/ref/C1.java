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
