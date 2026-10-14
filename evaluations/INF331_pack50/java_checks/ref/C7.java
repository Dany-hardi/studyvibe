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
