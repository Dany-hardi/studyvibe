class Base {
    Base() { System.out.print("B" + describe() + " "); }
    String describe() { return "base"; }
}
class Derived extends Base {
    private String tag = "ready";
    private final int n;
    Derived() { super(); n = 7; System.out.print("D" + describe() + " "); }
    @Override String describe() { return tag + n; }
}
public class Init { public static void main(String[] a) { new Derived(); System.out.println(); } }
