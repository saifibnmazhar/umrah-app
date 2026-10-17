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
-- dry-run below) is the authoritative value-level check. Query B is its
-- SQL counterpart: it lists the passengers still carrying a stored value
-- that disagrees with the historic replay (historic_package_value).
--
--   php artisan bookings:sync-financials --dry-run
-- =====================================================================

-- =====================================================================
-- Shared fragments: anchor + historic column replay (LogReplay port)
-- =====================================================================
--
-- Every historic value below replays its *_update_logs stream at the
-- passenger's anchor, mirroring App\Support\LogReplay::valueFromEvents:
--   1. SEED = created.new_values[col]; else the first old_values[col]
--      ever logged for the column (start of recorded history);
--   2. WALK = last new_values[col] with created_at <= anchor
--      (actions 'updated' AND 'fare_updated');
--   3. FALLBACK = live row value when the column has no recorded history.
--
-- JSON null extracts as the text 'null' — every extract is guarded with
-- NULLIF(..., 'null') before CAST. 0 and "0.00" are legitimate values, so
-- the replay tests JSON_CONTAINS_PATH, never truthiness.
--
-- Anchor = earliest-era anchoring (App\Support\PackageEras::anchorAt):
-- max(earliest moment the booking's CURRENT package became active,
-- passenger.created_at). The era start is booking creation when the
-- current package was the initial one, else the earliest logged swap
-- targeting it. Swaps before 2026-07-14 are unlogged and fall back to
-- created_at. passenger_type / service_required / extra_charge are read
-- from the LIVE passenger row, exactly like the PHP calculator.
--
-- The full shared replay chain -- anchored -> replayed -> assembled ->
-- historic -- is embedded verbatim by Query A, Query B and Query H. The
-- 'historic' CTE resolves each passenger's historic_package_value and
-- stored_minus_historic; only the final SELECT differs per query.
--
-- Reusable CTE (paste after WITH, adapt the final SELECT):
--
--   anchored AS (
--       SELECT
--           p.id AS passenger_id,
--           GREATEST(
--               COALESCE((
--                   SELECT MIN(s.created_at)
--                     FROM booking_update_logs s
--                    WHERE s.booking_id = p.booking_id
--                      AND s.action = 'updated'
--                      AND JSON_CONTAINS_PATH(s.new_values, 'one', '$.package_id')
--                      AND CAST(JSON_UNQUOTE(JSON_EXTRACT(s.new_values, '$.package_id')) AS UNSIGNED) = b.package_id
--               ), b.created_at),
--               p.created_at
--           ) AS anchor_at
--         FROM passengers p
--         JOIN bookings b ON b.id = p.booking_id
--        -- AND p.id = ?   -- add to scope a single passenger
--   )
--
-- Column replay expression (replace <FK>, <LIVE>, <ANCHOR>, <KEY>, <CAST>):
--
--   COALESCE(
--       (SELECT <CAST>(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.<KEY>')), 'null'))
--          FROM <LOG_TABLE> l
--         WHERE l.<FK> = <FK_VALUE>
--           AND l.created_at <= <ANCHOR>
--           AND (l.action = 'updated' OR l.action = 'fare_updated')
--           AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.<KEY>')
--         ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
--       (SELECT <CAST>(NULLIF(JSON_UNQUOTE(
--                 CASE WHEN c.id IS NOT NULL
--                      THEN JSON_EXTRACT(c.new_values, '$.<KEY>')
--                      ELSE JSON_EXTRACT(f.old_values, '$.<KEY>') END), 'null')))
--          FROM <LOG_TABLE> f
--          LEFT JOIN <LOG_TABLE> c
--            ON c.<FK> = <FK_VALUE> AND c.action = 'created'
--           AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.<KEY>')
--         WHERE f.<FK> = <FK_VALUE>
--           AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.<KEY>')
--         ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
--       <LIVE>
--   )
--
-- Ticket-type columns replay as text (LOWER() at comparison: the column is
-- an enum on the live row but a plain string inside the logs).
--
-- ---------------------------------------------------------------------
-- Query A — every wrong recalculation EVENT (forensic detail)
-- ---------------------------------------------------------------------
WITH anchored AS (
    SELECT
        p.id AS passenger_id,
        GREATEST(
            COALESCE((
                SELECT MIN(s.created_at)
                  FROM booking_update_logs s
                 WHERE s.booking_id = p.booking_id
                   AND s.action = 'updated'
                   AND JSON_CONTAINS_PATH(s.new_values, 'one', '$.package_id')
                   AND CAST(JSON_UNQUOTE(JSON_EXTRACT(s.new_values, '$.package_id')) AS UNSIGNED) = b.package_id
            ), b.created_at),
            p.created_at
        ) AS anchor_at
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
),
replayed AS (
    SELECT
        p.id                                       AS passenger_id,
        CONCAT(p.first_name, ' ', p.last_name)     AS passenger_name,
        p.passport_no,
        p.booking_id,
        b.invoice_id,
        p.package_value                            AS current_stored_value,
        a.anchor_at                                AS anchor_at,
        LOWER(p.passenger_type)                    AS pax_type,
        LOWER(p.service_required)                  AS svc,
        pk.is_double_ticket                        AS is_double,
        COALESCE(p.extra_charge, 0)                AS historic_extra,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.selling_fare
        )                                          AS h_selling,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.offer_price')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.offer_price')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.offer_price')
                           ELSE JSON_EXTRACT(f.old_values, '$.offer_price') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.offer_price')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.offer_price')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.offer_price
        )                                          AS h_offer,
        COALESCE(
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.ticket_type')), 'null'))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.ticket_type')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.ticket_type')
                           ELSE JSON_EXTRACT(f.old_values, '$.ticket_type') END), 'null'))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.ticket_type')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.ticket_type')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            LOWER(tf.ticket_type)
        )                                          AS h_type,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.child_fare_percentage
        )                                          AS h_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.infant_fare_percentage
        )                                          AS h_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.selling_fare
        )                                          AS h_inbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_outbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_outbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_outbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfout.selling_fare
        )                                          AS h_outbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.child_fare_percentage,
            70
        )                                          AS h_in_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.infant_fare_percentage,
            30
        )                                          AS h_in_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.visa_selling_price_id')), 'null') AS UNSIGNED)
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.visa_selling_price_id')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.visa_selling_price_id')
                           ELSE JSON_EXTRACT(f.old_values, '$.visa_selling_price_id') END), 'null') AS UNSIGNED)
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.visa_selling_price_id')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.visa_selling_price_id')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.visa_selling_price_id
        )                                          AS h_visa_id,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.service_charge')), 'null') AS DECIMAL(14,6))
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.service_charge')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.service_charge')
                           ELSE JSON_EXTRACT(f.old_values, '$.service_charge') END), 'null') AS DECIMAL(14,6))
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.service_charge')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.service_charge')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.service_charge,
            0
        )                                          AS h_service
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
      JOIN packages pk ON pk.id = b.package_id
      JOIN anchored a ON a.passenger_id = p.id
      LEFT JOIN ticket_fares tf ON tf.id = p.ticket_fare_id
      LEFT JOIN ticket_fares tfin ON tfin.id = p.ticket_fare_inbound_id
      LEFT JOIN ticket_fares tfout ON tfout.id = p.ticket_fare_outbound_id
),
assembled AS (
    SELECT
        r.*,
        CASE WHEN r.svc <> 'visa_only' AND r.is_double = 1
             THEN COALESCE(r.h_inbound, 0) + COALESCE(r.h_outbound, 0)
             ELSE 0 END                             AS dbl_base,
        CASE WHEN r.svc <> 'visa_only' AND (r.is_double IS NULL OR r.is_double = 0)
             THEN CASE WHEN r.h_type = 'offer' THEN COALESCE(r.h_offer, r.h_selling, 0)
                       ELSE COALESCE(r.h_selling, 0) END
             ELSE 0 END                             AS sgl_base
      FROM replayed r
),
historic AS (
SELECT
    passenger_id,
    passenger_name,
    passport_no,
    booking_id,
    invoice_id,
    anchor_at,
    current_stored_value,
    historic_extra,
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra                              AS historic_package_value,
    current_stored_value - (
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra)                             AS stored_minus_historic
  FROM assembled
),
events AS (
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
ORDER BY l.created_at DESC
)
SELECT
    e.passenger_id,
    e.passenger_name,
    e.passport_no,
    e.booking_id,
    e.invoice_id,
    e.recalc_at,
    e.user_id,
    e.is_cancelled,
    e.changed_keys,
    e.old_package_value,
    e.new_package_value,
    e.delta,
    h.anchor_at,
    h.historic_package_value,
    e.new_package_value - h.historic_package_value AS stored_minus_historic
  FROM events e
  JOIN historic h ON h.passenger_id = e.passenger_id
ORDER BY e.recalc_at DESC;


-- ---------------------------------------------------------------------
-- Query B — passengers CURRENTLY carrying a wrongly-recalculated value
-- (last package_value write was a wrong event, with no healing package
-- swap or authenticated 'recalculated' repair row after it).
--
-- The event screen and the value check are one query: each flagged
-- passenger is LEFT JOINed to the shared historic replay (the same
-- anchored -> replayed -> assembled -> historic chain the other
-- queries use), so every row also carries
--   historic_package_value  the anchor replay's "supposed to be" value
--   stored_minus_historic   p.package_value - historic_package_value
-- Compare the two to see how far the stored value drifted from history.
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
),
anchored AS (
    SELECT
        p.id AS passenger_id,
        GREATEST(
            COALESCE((
                SELECT MIN(s.created_at)
                  FROM booking_update_logs s
                 WHERE s.booking_id = p.booking_id
                   AND s.action = 'updated'
                   AND JSON_CONTAINS_PATH(s.new_values, 'one', '$.package_id')
                   AND CAST(JSON_UNQUOTE(JSON_EXTRACT(s.new_values, '$.package_id')) AS UNSIGNED) = b.package_id
            ), b.created_at),
            p.created_at
        ) AS anchor_at
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
),
replayed AS (
    SELECT
        p.id                                       AS passenger_id,
        CONCAT(p.first_name, ' ', p.last_name)     AS passenger_name,
        p.passport_no,
        p.booking_id,
        b.invoice_id,
        p.package_value                            AS current_stored_value,
        a.anchor_at                                AS anchor_at,
        LOWER(p.passenger_type)                    AS pax_type,
        LOWER(p.service_required)                  AS svc,
        pk.is_double_ticket                        AS is_double,
        COALESCE(p.extra_charge, 0)                AS historic_extra,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.selling_fare
        )                                          AS h_selling,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.offer_price')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.offer_price')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.offer_price')
                           ELSE JSON_EXTRACT(f.old_values, '$.offer_price') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.offer_price')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.offer_price')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.offer_price
        )                                          AS h_offer,
        COALESCE(
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.ticket_type')), 'null'))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.ticket_type')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.ticket_type')
                           ELSE JSON_EXTRACT(f.old_values, '$.ticket_type') END), 'null'))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.ticket_type')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.ticket_type')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            LOWER(tf.ticket_type)
        )                                          AS h_type,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.child_fare_percentage
        )                                          AS h_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.infant_fare_percentage
        )                                          AS h_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.selling_fare
        )                                          AS h_inbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_outbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_outbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_outbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfout.selling_fare
        )                                          AS h_outbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.child_fare_percentage,
            70
        )                                          AS h_in_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.infant_fare_percentage,
            30
        )                                          AS h_in_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.visa_selling_price_id')), 'null') AS UNSIGNED)
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.visa_selling_price_id')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.visa_selling_price_id')
                           ELSE JSON_EXTRACT(f.old_values, '$.visa_selling_price_id') END), 'null') AS UNSIGNED)
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.visa_selling_price_id')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.visa_selling_price_id')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.visa_selling_price_id
        )                                          AS h_visa_id,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.service_charge')), 'null') AS DECIMAL(14,6))
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.service_charge')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.service_charge')
                           ELSE JSON_EXTRACT(f.old_values, '$.service_charge') END), 'null') AS DECIMAL(14,6))
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.service_charge')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.service_charge')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.service_charge,
            0
        )                                          AS h_service
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
      JOIN packages pk ON pk.id = b.package_id
      JOIN anchored a ON a.passenger_id = p.id
      LEFT JOIN ticket_fares tf ON tf.id = p.ticket_fare_id
      LEFT JOIN ticket_fares tfin ON tfin.id = p.ticket_fare_inbound_id
      LEFT JOIN ticket_fares tfout ON tfout.id = p.ticket_fare_outbound_id
),
assembled AS (
    SELECT
        r.*,
        CASE WHEN r.svc <> 'visa_only' AND r.is_double = 1
             THEN COALESCE(r.h_inbound, 0) + COALESCE(r.h_outbound, 0)
             ELSE 0 END                             AS dbl_base,
        CASE WHEN r.svc <> 'visa_only' AND (r.is_double IS NULL OR r.is_double = 0)
             THEN CASE WHEN r.h_type = 'offer' THEN COALESCE(r.h_offer, r.h_selling, 0)
                       ELSE COALESCE(r.h_selling, 0) END
             ELSE 0 END                             AS sgl_base
      FROM replayed r
),
historic AS (
SELECT
    passenger_id,
    passenger_name,
    passport_no,
    booking_id,
    invoice_id,
    anchor_at,
    current_stored_value,
    historic_extra,
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra                              AS historic_package_value,
    current_stored_value - (
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra)                             AS stored_minus_historic
  FROM assembled
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
       LIMIT 1)                                       AS value_from_last_wrong_recalc,
    h.anchor_at,
    h.historic_package_value,
    p.package_value - h.historic_package_value        AS stored_minus_historic
FROM wrong_events w
JOIN passengers p ON p.id = w.passenger_id
JOIN bookings   b ON b.id = p.booking_id
LEFT JOIN historic h ON h.passenger_id = p.id
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

-- ---------------------------------------------------------------------
-- Query H — historic package_value replay per passenger (the
-- "supposed to be" value). Mirrors
-- BookingService::calculatePackageValue($p, $p->historicAnchor()).
--
-- This is the pure replay: one row per passenger with the anchor, the
-- replayed historic_package_value and stored_minus_historic. Query A
-- joins this same shared chain for per-event detail; Query B LEFT JOINs
-- it to attach the historic value to the passengers it flags.
-- ---------------------------------------------------------------------
WITH anchored AS (
    SELECT
        p.id AS passenger_id,
        GREATEST(
            COALESCE((
                SELECT MIN(s.created_at)
                  FROM booking_update_logs s
                 WHERE s.booking_id = p.booking_id
                   AND s.action = 'updated'
                   AND JSON_CONTAINS_PATH(s.new_values, 'one', '$.package_id')
                   AND CAST(JSON_UNQUOTE(JSON_EXTRACT(s.new_values, '$.package_id')) AS UNSIGNED) = b.package_id
            ), b.created_at),
            p.created_at
        ) AS anchor_at
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
),
replayed AS (
    SELECT
        p.id                                       AS passenger_id,
        CONCAT(p.first_name, ' ', p.last_name)     AS passenger_name,
        p.passport_no,
        p.booking_id,
        b.invoice_id,
        p.package_value                            AS current_stored_value,
        a.anchor_at                                AS anchor_at,
        LOWER(p.passenger_type)                    AS pax_type,
        LOWER(p.service_required)                  AS svc,
        pk.is_double_ticket                        AS is_double,
        COALESCE(p.extra_charge, 0)                AS historic_extra,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.selling_fare
        )                                          AS h_selling,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.offer_price')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.offer_price')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.offer_price')
                           ELSE JSON_EXTRACT(f.old_values, '$.offer_price') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.offer_price')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.offer_price')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.offer_price
        )                                          AS h_offer,
        COALESCE(
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.ticket_type')), 'null'))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.ticket_type')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT LOWER(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.ticket_type')
                           ELSE JSON_EXTRACT(f.old_values, '$.ticket_type') END), 'null'))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.ticket_type')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.ticket_type')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            LOWER(tf.ticket_type)
        )                                          AS h_type,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.child_fare_percentage
        )                                          AS h_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tf.infant_fare_percentage
        )                                          AS h_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.selling_fare
        )                                          AS h_inbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.selling_fare')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_outbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.selling_fare')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.selling_fare')
                           ELSE JSON_EXTRACT(f.old_values, '$.selling_fare') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_outbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.selling_fare')
              WHERE f.ticket_fare_id = p.ticket_fare_outbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.selling_fare')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfout.selling_fare
        )                                          AS h_outbound,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.child_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.child_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.child_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.child_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.child_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.child_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.child_fare_percentage,
            70
        )                                          AS h_in_child_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.infant_fare_percentage')), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs l
              WHERE l.ticket_fare_id = p.ticket_fare_inbound_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.infant_fare_percentage')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.infant_fare_percentage')
                           ELSE JSON_EXTRACT(f.old_values, '$.infant_fare_percentage') END), 'null') AS DECIMAL(14,6))
               FROM ticket_fare_update_logs f
               LEFT JOIN ticket_fare_update_logs c
                 ON c.ticket_fare_id = p.ticket_fare_inbound_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.infant_fare_percentage')
              WHERE f.ticket_fare_id = p.ticket_fare_inbound_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.infant_fare_percentage')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            tfin.infant_fare_percentage,
            30
        )                                          AS h_in_infant_pct,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.visa_selling_price_id')), 'null') AS UNSIGNED)
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.visa_selling_price_id')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.visa_selling_price_id')
                           ELSE JSON_EXTRACT(f.old_values, '$.visa_selling_price_id') END), 'null') AS UNSIGNED)
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.visa_selling_price_id')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.visa_selling_price_id')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.visa_selling_price_id
        )                                          AS h_visa_id,
        COALESCE(
            (SELECT CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.new_values, '$.service_charge')), 'null') AS DECIMAL(14,6))
               FROM package_update_logs l
              WHERE l.package_id = b.package_id
                AND l.created_at <= a.anchor_at
                AND (l.action = 'updated' OR l.action = 'fare_updated')
                AND JSON_CONTAINS_PATH(l.new_values, 'one', '$.service_charge')
              ORDER BY l.created_at DESC, l.id DESC LIMIT 1),
            (SELECT CAST(NULLIF(JSON_UNQUOTE(
                      CASE WHEN c.id IS NOT NULL
                           THEN JSON_EXTRACT(c.new_values, '$.service_charge')
                           ELSE JSON_EXTRACT(f.old_values, '$.service_charge') END), 'null') AS DECIMAL(14,6))
               FROM package_update_logs f
               LEFT JOIN package_update_logs c
                 ON c.package_id = b.package_id AND c.action = 'created'
                AND JSON_CONTAINS_PATH(c.new_values, 'one', '$.service_charge')
              WHERE f.package_id = b.package_id
                AND JSON_CONTAINS_PATH(f.old_values, 'one', '$.service_charge')
              ORDER BY f.created_at ASC, f.id ASC LIMIT 1),
            pk.service_charge,
            0
        )                                          AS h_service
      FROM passengers p
      JOIN bookings b ON b.id = p.booking_id
      JOIN packages pk ON pk.id = b.package_id
      JOIN anchored a ON a.passenger_id = p.id
      LEFT JOIN ticket_fares tf ON tf.id = p.ticket_fare_id
      LEFT JOIN ticket_fares tfin ON tfin.id = p.ticket_fare_inbound_id
      LEFT JOIN ticket_fares tfout ON tfout.id = p.ticket_fare_outbound_id
),
assembled AS (
    SELECT
        r.*,
        CASE WHEN r.svc <> 'visa_only' AND r.is_double = 1
             THEN COALESCE(r.h_inbound, 0) + COALESCE(r.h_outbound, 0)
             ELSE 0 END                             AS dbl_base,
        CASE WHEN r.svc <> 'visa_only' AND (r.is_double IS NULL OR r.is_double = 0)
             THEN CASE WHEN r.h_type = 'offer' THEN COALESCE(r.h_offer, r.h_selling, 0)
                       ELSE COALESCE(r.h_selling, 0) END
             ELSE 0 END                             AS sgl_base
      FROM replayed r
),
historic AS (
SELECT
    passenger_id,
    passenger_name,
    passport_no,
    booking_id,
    invoice_id,
    anchor_at,
    current_stored_value,
    historic_extra,
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra                              AS historic_package_value,
    current_stored_value - (
    CASE WHEN svc = 'visa_only' THEN 0
         WHEN is_double = 1 THEN
              CASE WHEN pax_type = 'child' THEN dbl_base * h_in_child_pct / 100
                   WHEN pax_type = 'infant' THEN dbl_base * h_in_infant_pct / 100
                   ELSE dbl_base END
         WHEN pax_type = 'child' THEN sgl_base * COALESCE(h_child_pct, 70) / 100
         WHEN pax_type = 'infant' THEN sgl_base * COALESCE(h_infant_pct, 30) / 100
         ELSE sgl_base END
    + CASE WHEN svc = 'ticket_only' THEN 0
           ELSE COALESCE((SELECT v.selling_price FROM visa_selling_prices v WHERE v.id = h_visa_id), 0) END
    + COALESCE(h_service, 0)
    + historic_extra)                             AS stored_minus_historic
  FROM assembled
)
SELECT
    passenger_id,
    passenger_name,
    passport_no,
    booking_id,
    invoice_id,
    anchor_at,
    current_stored_value,
    historic_extra,
    historic_package_value,
    stored_minus_historic
  FROM historic
ORDER BY passenger_id;
