abstract class Forme { abstract int aire(); }
class Rectangle extends Forme { int l, h; Rectangle(int l, int h) { this.l = l; this.h = h; } int aire() { return l * h; } }
class Triangle extends Forme { int b, h; Triangle(int b, int h) { this.b = b; this.h = h; } int aire() { return b * h / 2; } }
class Carre extends Rectangle { Carre(int c) { super(c, c); } }
public class Areas { public static void main(String[] a) {
    Forme[] f = { new Rectangle(2, 3), new Triangle(5, 3), new Carre(3), new Rectangle(4, 1) };
    int s = 0; for (Forme x : f) s += x.aire(); System.out.println(s); } }
