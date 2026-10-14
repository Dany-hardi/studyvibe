import java.util.*;
interface ContractPay { double pay(double baseSalary, int overtimeHours); }
class PermanentPay implements ContractPay {
    public double pay(double base, int ot) { return base + ot * (base / 160) * 1.25; }
}
class ContractorPay implements ContractPay {
    public double pay(double base, int ot) { return base + ot * (base / 160); }
}
class InternPay implements ContractPay {
    public double pay(double base, int ot) { return Math.min(base, 50000); }
}
class PayrollCalculator {
    private final Map<String, ContractPay> registry = new HashMap<>();
    PayrollCalculator() {
        register("PERMANENT", new PermanentPay());
        register("CONTRACTOR", new ContractorPay());
        register("INTERN", new InternPay());
    }
    void register(String contract, ContractPay p) { registry.put(contract, p); }
    public double monthlyPay(String contract, double baseSalary, int overtimeHours) {
        ContractPay p = registry.get(contract);
        if (p == null) throw new IllegalArgumentException("Unknown contract " + contract);
        return p.pay(baseSalary, overtimeHours);
    }
}
public class C6 {
    static void near(double a, double b) { if (Math.abs(a - b) > 1e-9) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        PayrollCalculator c = new PayrollCalculator();
        near(c.monthlyPay("PERMANENT", 160000, 10), 160000 + 10 * 1000 * 1.25);
        near(c.monthlyPay("CONTRACTOR", 160000, 10), 170000);
        near(c.monthlyPay("INTERN", 80000, 10), 50000); near(c.monthlyPay("INTERN", 30000, 10), 30000);
        try { c.monthlyPay("X", 1, 1); throw new AssertionError(); } catch (IllegalArgumentException e) { if (!e.getMessage().equals("Unknown contract X")) throw e; }
        c.register("TRAINEE", (b, o) -> 10); near(c.monthlyPay("TRAINEE", 1, 1), 10);
    }
}
