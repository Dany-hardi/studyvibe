class P {
    String name = "P";
    static String s() { return "Ps"; }
    String m(Object o) { return "P.obj"; }
    String m(String s) { return "P.str"; }
}
class C extends P {
    String name = "C";
    static String s() { return "Cs"; }
    String m(Object o) { return "C.obj"; }
}
public class Dispatch {
    public static void main(String[] a) {
        P p = new C(); Object o = "x";
        System.out.println(p.name + "|" + p.s() + "|" + p.m(o) + "|" + p.m("x"));
    }
}
