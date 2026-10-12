-- =====================================================================
-- Wrong package_value recalculation forensics (plan 17)
-- MySQL 8.0+ / MariaDB 10.2+
--
-- LEGITIMATE package_value change reasons (excluded from "wrong"):
--   1. Package swap on the booking  -> booking_update_logs row with
--      action='updated' and package_id in old_values
--   2. passenger_type change        -> passenger_update_logs row with
--      passenger_type in old_values
--   3. extra_charge add/update      -> row with extra_charge in old_values
--   4. service_required change      -> row with service_required in old_values
--      (plan 17 §2b trigger matrix; drop this line for a strict 3-trigger list)
--   5. Direct fare-FK edit          -> row with ticket_fare_id /
--      ticket_fare_inbound_id / ticket_fare_outbound_id in old_values
--      (possible only pre-D2; a fare change justifies a value change)
--   6. Creation / passenger add     -> initial valuation within 5 s of
--      passengers.created_at (passenger created with package_value = 0,
--      first recalc writes the real value)
--
-- WRONG: package_value overwritten with LIVE fares while editing any
-- other booking or passenger field (generic booking edit, fingerprint
-- location change, name/passport/DOB edit, passenger removal re-sum,
-- the deleted recalculatePassengerValue endpoint, CLI commands).
--
-- NOTE: recalcs triggered by the CLI commands (bookings:sync-financials,
-- passengers:sync-ticket-fare[-v2]) wrote NO log rows under the
-- pre-Phase-3 observer auth guard, and a legitimate trigger that
-- recomputes at live fares after an intervening fare/package edit still
-- leaves a DRIFTED value with no "wrong" event. Query C (the Laravel
-- dry-run below) is the authoritative value-level check:
--
--   php artisan bookings:sync-financials --dry-run
-- =====================================================================

-- ---------------------------------------------------------------------
-- Query A — every wrong recalculation EVENT (forensic detail)
-- ---------------------------------------------------------------------
SELECT
    p.id                                                          AS passenger_id,
    CONCAT(p.first_name, ' ', p.last_name)                        AS passenger_name,
    p.passport_no,
    p.booking_id,
    b.invoice_id,
    l.created_at                                                  AS recalc_at,
    l.user_id,
    p.is_cancelled,
    JSON_KEYS(l.old_values)                                       AS changed_keys,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(l.old_values, '$.package_value')) AS DECIMAL(14,6)) AS old_package_value,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.package_value')) AS DECIMAL(14,6)) AS new_package_value,
    CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.package_value')) AS DECIMAL(14,6))
  - CAST(JSON_UNQUOTE(JSON_EXTRACT(l.old_values, '$.package_value')) AS DECIMAL(14,6)) AS delta
FROM passenger_update_logs l
JOIN passengers p ON p.id = l.passenger_id
JOIN bookings   b ON b.id = p.booking_id
WHERE l.action = 'updated'
  AND JSON_CONTAINS_PATH(l.old_values, 'one', '$.package_value')
  AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.package_value')
  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.old_values, '$.package_value')) AS DECIMAL(14,6))
     <> CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.package_value')) AS DECIMAL(14,6))
  -- (1) no legitimate per-passenger trigger in the same request window
  AND NOT EXISTS (
        SELECT 1
        FROM passenger_update_logs t
        WHERE t.passenger_id = l.passenger_id
          AND t.action = 'updated'
          AND t.created_at BETWEEN l.created_at - INTERVAL 5 SECOND
                               AND l.created_at + INTERVAL 5 SECOND
          AND (   JSON_CONTAINS_PATH(t.old_values, 'one', '$.passenger_type')
               OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.extra_charge')
               OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.service_required')
               OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_id')
               OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_inbound_id')
               OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_outbound_id')
          )
  )
  -- (2) no package swap on the booking in the same request window
  AND NOT EXISTS (
        SELECT 1
        FROM booking_update_logs s
        WHERE s.booking_id = p.booking_id
          AND s.action = 'updated'
          AND s.created_at BETWEEN l.created_at - INTERVAL 5 SECOND
                               AND l.created_at + INTERVAL 5 SECOND
          AND JSON_CONTAINS_PATH(s.old_values, 'one', '$.package_id')
  )
  -- (3) not the passenger's initial valuation at creation
  AND NOT (l.created_at BETWEEN p.created_at - INTERVAL 5 SECOND
                            AND p.created_at + INTERVAL 5 SECOND)
ORDER BY l.created_at DESC;

-- ---------------------------------------------------------------------
-- Query B — passengers CURRENTLY carrying a wrongly-recalculated value
-- (last package_value write was a wrong event, with no healing package
-- swap or authenticated 'recalculated' repair row after it)
-- ---------------------------------------------------------------------
WITH wrong_events AS (
    SELECT l.passenger_id, MAX(l.created_at) AS last_wrong_at
    FROM passenger_update_logs l
    JOIN passengers p ON p.id = l.passenger_id
    WHERE l.action = 'updated'
      AND JSON_CONTAINS_PATH(l.old_values, 'one', '$.package_value')
      AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.package_value')
      AND CAST(JSON_UNQUOTE(JSON_EXTRACT(l.old_values, '$.package_value')) AS DECIMAL(14,6))
         <> CAST(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.package_value')) AS DECIMAL(14,6))
      AND NOT EXISTS (
            SELECT 1 FROM passenger_update_logs t
            WHERE t.passenger_id = l.passenger_id
              AND t.action = 'updated'
              AND t.created_at BETWEEN l.created_at - INTERVAL 5 SECOND AND l.created_at + INTERVAL 5 SECOND
              AND (   JSON_CONTAINS_PATH(t.old_values, 'one', '$.passenger_type')
                   OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.extra_charge')
                   OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.service_required')
                   OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_id')
                   OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_inbound_id')
                   OR JSON_CONTAINS_PATH(t.old_values, 'one', '$.ticket_fare_outbound_id')
              )
      )
      AND NOT EXISTS (
            SELECT 1 FROM booking_update_logs s
            WHERE s.booking_id = p.booking_id
              AND s.action = 'updated'
              AND s.created_at BETWEEN l.created_at - INTERVAL 5 SECOND AND l.created_at + INTERVAL 5 SECOND
              AND JSON_CONTAINS_PATH(s.old_values, 'one', '$.package_id')
      )
      AND NOT (l.created_at BETWEEN p.created_at - INTERVAL 5 SECOND AND p.created_at + INTERVAL 5 SECOND)
    GROUP BY l.passenger_id
)
SELECT
    p.id                                              AS passenger_id,
    CONCAT(p.first_name, ' ', p.last_name)            AS passenger_name,
    p.passport_no,
    p.booking_id,
    b.invoice_id,
    p.package_value                                   AS current_stored_value,
    w.last_wrong_at                                   AS last_wrong_recalc_at,
    (SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(x.new_values, '$.package_value')) AS DECIMAL(14,6))
       FROM passenger_update_logs x
       WHERE x.passenger_id = p.id
         AND x.created_at  = w.last_wrong_at
         AND x.action      = 'updated'
       ORDER BY x.id DESC
       LIMIT 1)                                       AS value_from_last_wrong_recalc
FROM wrong_events w
JOIN passengers p ON p.id = w.passenger_id
JOIN bookings   b ON b.id = p.booking_id
WHERE p.is_cancelled = 0
  AND NOT EXISTS (
        SELECT 1 FROM cancelled_bookings cb
        WHERE cb.booking_id = b.id
          AND cb.status = 'cancelled'
          AND cb.deleted_at IS NULL
  )
  AND NOT EXISTS (
        SELECT 1 FROM booking_update_logs s
        WHERE s.booking_id = b.id
          AND s.action = 'updated'
          AND JSON_CONTAINS_PATH(s.old_values, 'one', '$.package_id')
          AND s.created_at > w.last_wrong_at
  )
  AND NOT EXISTS (
        SELECT 1 FROM passenger_update_logs r
        WHERE r.passenger_id = p.id
          AND r.action = 'recalculated'
          AND r.created_at > w.last_wrong_at
  )
ORDER BY w.last_wrong_at DESC;
