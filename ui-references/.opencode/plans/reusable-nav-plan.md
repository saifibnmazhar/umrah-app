# Reusable Navigation Plan

## Step 1: Create Navigation File

**File:** `/home/me/projects/bm-umrah/nav.html`

### Contents:

1. **JavaScript Functions**
   - `toggleMobileMenu()` - Toggle mobile menu visibility
   - `setActiveNav(activePage)` - Highlight active navigation item
   - Auto-detect current page from URL

2. **Desktop Navigation HTML**
   - 8 items: Dashboard, Booking, Fingerprint Admin, Fingerprint Staff, Visa Admin, Ticket Admin, Reports (dropdown), Settings

3. **Mobile Navigation HTML**
   - Same 8 items in collapsible format with hamburger toggle

4. **Dropdown Items** (11 reports)
   - Fingerprint Report, Visa Report, Visa Agent Report, Ticket Statement, Pending Outbound Ticket Report, Re-Issue & Refund Report, Ticket Agent Report, Payment Receiving Report, Due Report, Profit/Loss Report, User-wise Sales Report

---

## Step 2: How to Include in Each Page

In each HTML file:
```html
<!-- OLD NAVIGATION -->
    [old nav code]
<!-- END OLD NAVIGATION -->

<!-- NEW NAVIGATION -->
<!--#include file="nav.html" -->
<!-- NEW NAVIGATION END -->
```

---

## Step 3: Files to Update (27 total)

### Main Pages (7):
1. dashboard.html
2. booking.html
3. fare-admin.html
4. visa-admin.html
5. settings.html
6. fingerprint-staff.html
7. fingerprint-admin.html

### Report Pages (11):
8. statement.html
9. profit-loss-report.html
10. fingerprint-report.html
11. visa-report.html
12. visa-agent-report.html
13. ticket-agent-report.html
14. due-report.html
15. reissue-refund-report.html
16. user-wise-sales-report.html
17. pending-outbound-ticket-report.html
18. payment-receiving-report.html

### Details Pages (9):
19. invoice-details.html
20. passenger-details.html
21. visa-passenger-details.html
22. fare-passenger-details.html
23. package-details.html
24. branch-due-details.html
25. add-ticket-confirmation.html
26. refund-confirmation.html
27. re-issue-confirmation.html

---

## Step 4: Implementation Order

1. Create `/home/me/projects/bm-umrah/nav.html`
2. For each file:
   - Wrap old nav in HTML comments
   - Add new nav include
3. Test navigation works

---

## Notes

- Navigation auto-detects current page and highlights active item
- CSS uses Tailwind classes (already loaded via CDN)
- Mobile menu works via hamburger button