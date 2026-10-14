import java.time.LocalDate;
import java.util.*;
interface Clock { LocalDate today(); }
interface Mailer { void send(String to, String subject); }
class Invoice {
    private final String number; private final LocalDate dueDate; private final boolean paid; private final String customerEmail;
    Invoice(String n, LocalDate d, boolean p, String e) { number = n; dueDate = d; paid = p; customerEmail = e; }
    String getNumber() { return number; } LocalDate getDueDate() { return dueDate; } boolean isPaid() { return paid; } String getCustomerEmail() { return customerEmail; }
}
class InvoiceReminder {
    private final Clock clock; private final Mailer mailer;
    InvoiceReminder(Clock c, Mailer m) { clock = c; mailer = m; }
    int remindOverdue(List<Invoice> invoices) {
        if (invoices == null) throw new IllegalArgumentException("invoices");
        int sent = 0;
        for (Invoice i : invoices) {
            if (!i.isPaid() && i.getDueDate().isBefore(clock.today())) {
                try { mailer.send(i.getCustomerEmail(), "Invoice " + i.getNumber() + " is overdue"); sent++; } catch (RuntimeException e) { /* skipped */ }
            }
        }
        return sent;
    }
}
