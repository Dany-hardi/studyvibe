import org.junit.jupiter.api.*;
import static org.junit.jupiter.api.Assertions.*;
import java.time.LocalDate;
import java.util.*;

public class InvoiceReminderTest {
    private static final LocalDate TODAY = LocalDate.of(2026, 10, 14);

    /** Test double: a clock that always says the same day. */
    static class FixedClock implements Clock {
        public LocalDate today() { return TODAY; }
    }

    /** Spy: records every mail and can be told to fail for some recipients. */
    static class SpyMailer implements Mailer {
        final List<String[]> sent = new ArrayList<>();
        final Set<String> failFor = new HashSet<>();
        public void send(String to, String subject) {
            if (failFor.contains(to)) throw new IllegalStateException("smtp down for " + to);
            sent.add(new String[] { to, subject });
        }
    }

    private SpyMailer mailer;
    private InvoiceReminder reminder;

    private void setUp() {
        mailer = new SpyMailer();
        reminder = new InvoiceReminder(new FixedClock(), mailer);
    }
    private static Invoice invoice(String n, int daysFromToday, boolean paid, String email) {
        return new Invoice(n, TODAY.plusDays(daysFromToday), paid, email);
    }

    @Test @DisplayName("no invoices: nothing is sent and zero is returned")
    public void noInvoicesSendsNothing() {
        setUp();
        int count = reminder.remindOverdue(Collections.emptyList());
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "no mail expected");
    }

    @Test @DisplayName("an invoice due today is not overdue")
    public void dueTodayIsNotOverdue() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F1", 0, false, "a@x.org")));
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "due today must not be reminded");
    }

    @Test @DisplayName("an unpaid invoice due yesterday is reminded once with the expected subject")
    public void unpaidDueYesterdayIsReminded() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F2", -1, false, "b@x.org")));
        assertEquals(1, count);
        assertEquals(1, mailer.sent.size(), "one mail");
        assertEquals("b@x.org", mailer.sent.get(0)[0]);
        assertEquals("Invoice F2 is overdue", mailer.sent.get(0)[1]);
    }

    @Test @DisplayName("a paid invoice is never reminded, even if long overdue")
    public void paidInvoiceIsIgnored() {
        setUp();
        int count = reminder.remindOverdue(Arrays.asList(invoice("F3", -90, true, "c@x.org")));
        assertEquals(0, count);
        assertTrue(mailer.sent.isEmpty(), "paid invoice must not be reminded");
    }

    @Test @DisplayName("several overdue invoices: one mail each, others untouched")
    public void severalOverdueInvoices() {
        setUp();
        List<Invoice> all = Arrays.asList(invoice("F4", -3, false, "d@x.org"), invoice("F5", 2, false, "e@x.org"), invoice("F6", -10, false, "f@x.org"));
        int count = reminder.remindOverdue(all);
        assertEquals(2, count);
        assertEquals("Invoice F4 is overdue", mailer.sent.get(0)[1]);
        assertEquals("Invoice F6 is overdue", mailer.sent.get(1)[1]);
    }

    @Test @DisplayName("a failing mail does not stop the others and is not counted")
    public void failingMailDoesNotStopTheOthers() {
        setUp();
        mailer.failFor.add("g@x.org");
        List<Invoice> all = Arrays.asList(invoice("F7", -1, false, "g@x.org"), invoice("F8", -1, false, "h@x.org"));
        int count = reminder.remindOverdue(all);
        assertEquals(1, count);
        assertEquals(1, mailer.sent.size(), "only the successful mail is recorded");
        assertEquals("h@x.org", mailer.sent.get(0)[0]);
    }

    @Test @DisplayName("a null list is rejected with IllegalArgumentException")
    public void nullListIsRejected() {
        setUp();
        assertThrows(IllegalArgumentException.class, () -> reminder.remindOverdue(null));
    }
}
