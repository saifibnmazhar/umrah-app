# Financial Mismatch Queries

## Query 1: Booking total_value vs SUM(package_value) + fingerprint_charge

```sql
SELECT
    b.id AS booking_id,
    b.invoice_id,
    i.id AS invoice_id,
    b.total_value,
    i.total_amount,
    CAST(
        COALESCE(SUM(p.package_value), 0)
        + CASE WHEN b.fingerprint_location = 'home'
               THEN COALESCE(fc.fingerprint_charge, 0)
               ELSE 0
          END
        AS DECIMAL(14,2)
    ) AS sum_package_value_with_fingerprint
FROM bookings b
JOIN invoices i ON i.booking_id = b.id
JOIN passengers p ON p.booking_id = b.id
LEFT JOIN fingerprint_charges fc ON fc.district_id = b.district_id
GROUP BY b.id, b.invoice_id, i.id, b.total_value, i.total_amount,
         b.fingerprint_location, fc.fingerprint_charge
HAVING CAST(COALESCE(b.total_value, 0) AS DECIMAL(14,2))
    != CAST(
        COALESCE(SUM(p.package_value), 0)
        + CASE WHEN b.fingerprint_location = 'home'
               THEN COALESCE(fc.fingerprint_charge, 0)
               ELSE 0
          END
        AS DECIMAL(14,2)
    );
```

## Query 2: Invoice total_amount vs SUM(package_value) + fingerprint_charge - discount

```sql
SELECT
    b.id AS booking_id,
    b.invoice_id,
    i.id AS invoice_id,
    b.total_value,
    i.total_amount,
    b.discount_amount,
    CAST(
        COALESCE(SUM(p.package_value), 0)
        + CASE WHEN b.fingerprint_location = 'home'
               THEN COALESCE(fc.fingerprint_charge, 0)
               ELSE 0
          END
        - COALESCE(b.discount_amount, 0)
        AS DECIMAL(14,2)
    ) AS expected_invoice_amount
FROM bookings b
JOIN invoices i ON i.booking_id = b.id
JOIN passengers p ON p.booking_id = b.id
LEFT JOIN fingerprint_charges fc ON fc.district_id = b.district_id
GROUP BY b.id, b.invoice_id, i.id, b.total_value, i.total_amount,
         b.discount_amount, b.fingerprint_location, fc.fingerprint_charge
HAVING CAST(COALESCE(i.total_amount, 0) AS DECIMAL(14,2))
    != CAST(
        COALESCE(SUM(p.package_value), 0)
        + CASE WHEN b.fingerprint_location = 'home'
               THEN COALESCE(fc.fingerprint_charge, 0)
               ELSE 0
          END
        - COALESCE(b.discount_amount, 0)
        AS DECIMAL(14,2)
    );
```
