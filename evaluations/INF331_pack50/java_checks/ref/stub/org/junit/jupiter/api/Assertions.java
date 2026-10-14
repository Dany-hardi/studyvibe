package org.junit.jupiter.api;
public class Assertions {
    public static void assertEquals(Object e, Object a) { if (!java.util.Objects.equals(e, a)) throw new AssertionError("expected " + e + " but was " + a); }
    public static void assertEquals(Object e, Object a, String m) { if (!java.util.Objects.equals(e, a)) throw new AssertionError(m + ": expected " + e + " but was " + a); }
    public static void assertEquals(int e, int a, String m) { if (e != a) throw new AssertionError(m + ": expected " + e + " but was " + a); }
    public static void assertTrue(boolean c, String m) { if (!c) throw new AssertionError(m); }
    public static void assertTrue(boolean c) { if (!c) throw new AssertionError(); }
    public static <T extends Throwable> T assertThrows(Class<T> k, Runnable r) {
        try { r.run(); } catch (Throwable t) { if (k.isInstance(t)) return k.cast(t); throw new AssertionError("wrong exception " + t); }
        throw new AssertionError("no exception");
    }
}
