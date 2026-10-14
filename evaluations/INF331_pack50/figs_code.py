# -*- coding: utf-8 -*-
"""Code listings shown as pictures (the question texts never contain code)."""
CODES = {}

CODES['lsp_fr'] = dict(title='Listing 1', code='''
class Rectangle {
    protected int w, h;
    void setWidth(int w)  { this.w = w; }
    void setHeight(int h) { this.h = h; }
    int area() { return w * h; }
}
class Square extends Rectangle {
    @Override void setWidth(int w)  { this.w = w; this.h = w; }
    @Override void setHeight(int h) { this.w = h; this.h = h; }
}
class Check {
    static boolean test(Rectangle r) {
        r.setWidth(5);
        r.setHeight(4);
        return r.area() == 20;
    }
}
''')

CODES['init_en'] = dict(title='Listing 1', code='''
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
public class Main {
    public static void main(String[] args) { new Derived(); }
}
''')

CODES['cover_fr'] = dict(title='Listing 1', code='''
static int tarif(int age, boolean membre) {
    int t = 20;
    if (age < 12) t = 10;
    if (membre && age >= 12) t -= 5;
    return t;
}

// Jeu de tests :   T1 : tarif(8, false)      T2 : tarif(30, true)
''')

CODES['smell_order_en'] = dict(title='Listing 1: OrderManager.java', code='''
public class OrderManager {
    public double process(Order o, String country, boolean vip, String payType) {
        double total = 0;
        for (int i = 0; i < o.items.size(); i++) {
            total = total + o.items.get(i).price * o.items.get(i).qty;
        }
        if (country.equals("CM")) { total = total * 1.1925; }
        else if (country.equals("FR")) { total = total * 1.20; }
        else if (country.equals("DE")) { total = total * 1.19; }
        if (vip) { total = total * 0.9; }
        if (payType.equals("CARD")) {
            System.out.println("Charging card " + o.cardNo);
            Bank.charge(o.cardNo, total);
        } else if (payType.equals("PAYPAL")) {
            System.out.println("Charging paypal " + o.email);
            PayPal.charge(o.email, total);
        }
        Mailer.send(o.email, "Order " + o.id + " total " + total);
        Database.save(o);
        return total;
    }
}
''', width=17.5)

CODES['solid_fr'] = dict(title='Listing 1', code='''
interface Machine {
    void print(Document d);
    void scan(Document d);
    void fax(Document d);
}
class OldPrinter implements Machine {
    public void print(Document d) { /* imprime */ }
    public void scan(Document d)  { throw new UnsupportedOperationException(); }
    public void fax(Document d)   { throw new UnsupportedOperationException(); }
}

class ReportService {
    private final MySqlRepository repo = new MySqlRepository();

    public String export(String format) {
        List<Row> rows = repo.findAll();
        if (format.equals("PDF")) return PdfWriter.write(rows);
        if (format.equals("CSV")) return CsvWriter.write(rows);
        throw new IllegalArgumentException(format);
    }
}
''', width=16.5)

CODES['dispatch_fr'] = dict(title='Listing 1', code='''
class Animal {
    String name = "animal";
    static String kind() { return "A"; }
    String sound(Object o) { return "A.obj"; }
    String sound(Dog d)    { return "A.dog"; }
    String who() { return name + "/" + sound(this); }
}
class Dog extends Animal {
    String name = "dog";
    static String kind() { return "D"; }
    @Override String sound(Object o) { return "D.obj"; }
    String sound(Animal a) { return "D.animal"; }
}
public class Main {
    public static void main(String[] args) {
        Animal a = new Dog();
        Dog d = new Dog();
        System.out.println(a.name + " " + a.kind());   // ligne 1
        System.out.println(a.sound(d));                // ligne 2
        System.out.println(d.sound(a));                // ligne 3
        System.out.println(a.who());                   // ligne 4
    }
}
''', width=16.5)

CODES['payroll_en'] = dict(title='Listing 1: PayrollCalculator.java', code='''
public class PayrollCalculator {
    public double monthlyPay(String contract, double baseSalary, int overtimeHours) {
        switch (contract) {
            case "PERMANENT":
                return baseSalary + overtimeHours * (baseSalary / 160) * 1.25;
            case "CONTRACTOR":
                return baseSalary + overtimeHours * (baseSalary / 160);
            case "INTERN":
                return Math.min(baseSalary, 50000);
            default:
                throw new IllegalArgumentException("Unknown contract " + contract);
        }
    }
}
''', width=17.0)
