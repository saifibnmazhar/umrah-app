# Backfill Cancelled Visa Agent ID — Preview

Run this `SELECT` to see which `visa_agent_id` each `cancelled_submissions` row would get after the positional backfill. The `rn` column shows the position within each `visa_submission_id`, ordered by `created_at, id`.

```sql
-- Preview mapping before running UPDATE
SELECT cs.id AS cancelled_submission_id,
       cs.visa_submission_id,
       cs.created_at AS cs_created_at,
       vul2.created_at AS log_created_at,
       cs2.rn AS position,
       JSON_UNQUOTE(JSON_EXTRACT(vul2.old_values, '$.visa_agent_id')) AS proposed_visa_agent_id,
       cs.visa_agent_id AS current_visa_agent_id
FROM (
    SELECT id,
           visa_submission_id,
           created_at,
           ROW_NUMBER() OVER (
               PARTITION BY visa_submission_id
               ORDER BY created_at, id
           ) AS rn
    FROM cancelled_submissions
) cs2
INNER JOIN (
    SELECT old_values,
           visa_submission_id,
           created_at,
           ROW_NUMBER() OVER (
               PARTITION BY visa_submission_id
               ORDER BY created_at, id
           ) AS rn
    FROM visa_update_logs
    WHERE action = 'cancelled'
) vul2 ON cs2.visa_submission_id = vul2.visa_submission_id
      AND cs2.rn = vul2.rn
INNER JOIN cancelled_submissions cs ON cs.id = cs2.id
ORDER BY cs.visa_submission_id, cs2.rn;
```

## Actual UPDATE command

Once you're satisfied with the preview, the update command in Laravel is:

```bash
php artisan visa:backfill-cancelled-agent
```

Or the raw SQL equivalent:

```sql
UPDATE cancelled_submissions cs
INNER JOIN (
    SELECT cs2.id,
           vul2.old_values
    FROM (
        SELECT id,
               visa_submission_id,
               ROW_NUMBER() OVER (
                   PARTITION BY visa_submission_id
                   ORDER BY created_at, id
               ) AS rn
        FROM cancelled_submissions
    ) cs2
    INNER JOIN (
        SELECT old_values,
               visa_submission_id,
               ROW_NUMBER() OVER (
                   PARTITION BY visa_submission_id
                   ORDER BY created_at, id
               ) AS rn
        FROM visa_update_logs
        WHERE action = 'cancelled'
    ) vul2 ON cs2.visa_submission_id = vul2.visa_submission_id
          AND cs2.rn = vul2.rn
) mapped ON cs.id = mapped.id
SET cs.visa_agent_id = JSON_UNQUOTE(JSON_EXTRACT(mapped.old_values, '$.visa_agent_id'))
WHERE cs.visa_agent_id IS NULL;
```

## Notes

- Uses `ROW_NUMBER()` (requires MariaDB 10.2+ or MySQL 8+).
- Each cancelled submission is matched positionally to its corresponding log entry (1st cancelled_submission → 1st cancel log, 2nd → 2nd, etc.).
- Only updates rows where `visa_agent_id IS NULL`.
- If a log entry has no `visa_agent_id` in `old_values`, the corresponding cancelled submission will remain NULL.
