class Rect { protected int w, h; void setW(int w) { this.w = w; } void setH(int h) { this.h = h; } int area() { return w * h; } }
class Square extends Rect { void setW(int w) { this.w = w; this.h = w; } void setH(int h) { this.w = h; this.h = h; } }
public class Lsp { public static void main(String[] a) { Rect r = new Square(); r.setW(5); r.setH(4); System.out.println(r.area()); } }
