class Etudiant { private final int[] notes; Etudiant(int[] n) { this.notes = n; } int[] getNotes() { return notes; }
  double moyenne() { double s = 0; for (int v : notes) s += v; return s / notes.length; } }
public class Leak { public static void main(String[] a) {
    int[] n = {10, 12}; Etudiant e = new Etudiant(n); System.out.print(e.moyenne() + " ");
    n[0] = 0; e.getNotes()[1] = 20; System.out.println(e.moyenne()); } }
