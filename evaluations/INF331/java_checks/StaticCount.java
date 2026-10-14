class Compte { static int nb = 0; int id; Compte() { id = ++nb; } }
public class StaticCount { public static void main(String[] x) {
    Compte a = new Compte(); Compte b = new Compte(); a = new Compte();
    System.out.println(a.id + "," + b.id + "," + Compte.nb + "," + b.nb); } }
