class A { A() { System.out.print("A"); init(); } void init() { System.out.print("a"); } }
class B extends A { int x = 5; B() { System.out.print("B"); } void init() { System.out.print(x); } }
public class InitOrder { public static void main(String[] a) { new B(); System.out.println(); } }
