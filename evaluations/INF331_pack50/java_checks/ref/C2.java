import java.util.*;
import java.util.concurrent.CopyOnWriteArrayList;
interface Observer { void update(double v); }
class Sensor {
    private double value; private final double threshold;
    private final List<Observer> observers = new CopyOnWriteArrayList<>();
    Sensor(double threshold) { this.threshold = threshold; }
    void attach(Observer o) { if (o != null && !observers.contains(o)) observers.add(o); }
    void detach(Observer o) { observers.remove(o); }
    void setValue(double v) {
        this.value = v;
        if (v > threshold) {
            for (Observer o : observers) {
                try { o.update(v); } catch (RuntimeException e) { /* the others still get notified */ }
            }
        }
    }
}
class Dashboard implements Observer { double lastValue; public void update(double v) { lastValue = v; } }
class AlarmLogger implements Observer { int alarms; public void update(double v) { alarms++; } }
public class C2 {
    static void eq(Object a, Object b) { if (!a.equals(b)) throw new AssertionError(a + " != " + b); }
    public static void main(String[] x) {
        Sensor s = new Sensor(50); Dashboard d = new Dashboard(); AlarmLogger l = new AlarmLogger();
        s.attach(d); s.attach(d); s.attach(null); s.attach(l);
        s.setValue(50); eq(l.alarms, 0);
        s.setValue(60); eq(l.alarms, 1); eq(d.lastValue, 60.0);
        Observer selfRemoving = new Observer() { public void update(double v) { s.detach(this); } };
        Observer bad = v -> { throw new RuntimeException("boom"); };
        s.attach(selfRemoving); s.attach(bad); s.attach(new AlarmLogger());
        s.setValue(70); eq(l.alarms, 2);
        s.setValue(80); eq(l.alarms, 3);
        s.detach(new Dashboard());
    }
}
