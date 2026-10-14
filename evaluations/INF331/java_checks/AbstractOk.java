abstract class S { abstract void f(); void g() { } }
class T extends S { void f() { } }
public class AbstractOk { public static void main(String[] a) { new T().f(); System.out.println("ok"); } }
