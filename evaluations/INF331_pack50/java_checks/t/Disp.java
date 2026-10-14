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
public class Disp {
    public static void main(String[] args) {
        Animal a = new Dog();
        Dog d = new Dog();
        System.out.println(a.name + " " + a.kind());
        System.out.println(a.sound(d));
        System.out.println(d.sound(a));
        System.out.println(a.who());
    }
}
