import java.lang.reflect.*;
public class RunTests {
    public static void main(String[] a) throws Exception {
        Class<?> k = Class.forName(a[0]); int ok = 0;
        for (Method m : k.getDeclaredMethods()) if (m.isAnnotationPresent(org.junit.jupiter.api.Test.class)) { m.invoke(k.getDeclaredConstructor().newInstance()); ok++; }
        System.out.println(ok + " tests passed");
    }
}
