class X { static { System.out.print("S"); } { System.out.print("I"); } X() { System.out.print("C"); } }
public class StaticInit { public static void main(String[] a) { new X(); new X(); System.out.println(); } }
