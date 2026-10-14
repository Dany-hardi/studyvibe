interface A { default String hi() { return "A"; } }
interface B { default String hi() { return "B"; } }
class C implements A, B { }
public class DefaultsBad { public static void main(String[] a) { System.out.println(new C().hi()); } }
