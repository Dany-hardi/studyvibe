class Vehicle { int move() { return 1; } }
class Train extends Vehicle { int move() { return super.move() * 3; } }
class Car extends Vehicle { int move() { return super.move() + 10; } }
class Boat extends Vehicle { int move() { return 5; } }
public class Vehicles { public static void main(String[] a) {
    Vehicle[] v = { new Car(), new Boat(), new Train() }; int s = 0; for (Vehicle x : v) s += x.move(); System.out.println(s); } }
