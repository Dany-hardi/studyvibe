class A { String f() { return "A"; } String g() { return f() + "g"; } }
class B extends A { String f() { return "B" + super.f(); } }
class C extends B { String g() { return super.g() + "C"; } }
public class SuperChain { public static void main(String[] a) { System.out.println(new C().g()); } }
