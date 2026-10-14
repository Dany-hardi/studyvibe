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
public class Lsp { public static void main(String[] x){ System.out.println(Check.test(new Rectangle())+" "+Check.test(new Square())); } }
