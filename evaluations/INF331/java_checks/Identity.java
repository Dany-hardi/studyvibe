class Chien { private String nom; Chien(String n) { nom = n; } void setNom(String n) { nom = n; } String getNom() { return nom; } }
public class Identity { public static void main(String[] x) {
    Chien a = new Chien("Charlie"); Chien b = new Chien("Charlie"); Chien c = a; c.setNom("Max");
    System.out.println((a == b) + " " + a.getNom() + " " + b.getNom() + " " + (a == c)); } }
