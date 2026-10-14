interface A { default String hi() { return "A"; } }
interface B { default String hi() { return "B"; } }
class C2 implements A, B { public String hi() { return A.super.hi() + B.super.hi(); } }
public class Defaults { public static void main(String[] a) { System.out.println(new C2().hi()); } }
