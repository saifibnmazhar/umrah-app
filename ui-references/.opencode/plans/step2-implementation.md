# Step 2 Implementation Plan

## Edit 1: Comment out old navigation in each file

For each of 27 HTML files:
1. Find the old navigation HTML (typically between `<nav class=...>` and `</nav>`)
2. Wrap it in HTML comment tags: `<!-- OLD NAVIGATION ... END OLD NAVIGATION -->`

## Edit 2: Include new navigation

Replace the comment wrapper with:
```
<!-- NEW NAVIGATION -->
<!--#include file="nav.html" -->
<!-- NEW NAVIGATION END -->
```

## Edit 3: Save file

No changes needed - file auto-saves after edit.

---

## Files to Update (27)

1. dashboard.html
2. booking.html
3. fare-admin.html
4. visa-admin.html
5. settings.html
6. fingerprint-staff.html
7. fingerprint-admin.html
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
19. invoice-details.html
20. passenger-details.html
21. visa-passenger-details.html
22. fare-passenger-details.html
23. package-details.html
24. branch-due-details.html
25. add-ticket-confirmation.html
26. refund-confirmation.html
27. re-issue-confirmation.html