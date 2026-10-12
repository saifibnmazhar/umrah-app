<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Pure, model-agnostic replay of *_update_logs event streams.
 *
 * Shared by TicketFare::valueAt(), Package::valueAt() and the historic
 * package-value calculator. The replay reconstructs the value a column had
 * at a past moment by seeding from the start of recorded history and walking
 * every logged change up to (and including) the anchor.
 */
class LogReplay
{
    /**
     * Normalize a collection of update-log models into chronological event
     * arrays. Rows with a null created_at are skipped (the column is nullable
     * on package/ticket_fare logs).
     *
     * @param  iterable<mixed>  $logs
     * @return array<int, array{action: string, old_values: array, new_values: array, created_at: string}>
     */
    public static function eventsFromLogs(iterable $logs): array
    {
        return collect($logs)
            ->filter(fn ($log) => $log->created_at)
            ->sortBy('created_at')
            ->values()
            ->map(fn ($log) => [
                'action' => $log->action,
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
                'created_at' => $log->created_at->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * Pure log-replay: seed the value from the start of recorded history (the
     * created-event snapshot, else the value just before the first recorded
     * change), then apply each change up to and including $at.
     *
     * Notes on the generalized engine:
     * - Seeding honors any action type's old_values, so a leading
     *   `fare_updated` cascade row (written manually after updateQuietly
     *   suppresses observers) seeds correctly.
     * - The forward walk applies both `updated` and `fare_updated` rows.
     * - Never isset()/empty() — 0 and "70.00" are legitimate values.
     *
     * @param  array<int, array{action: string, old_values: array, new_values: array, created_at: string}>  $events  chronological
     * @param  mixed  $fallback  live value used when the column has no recorded history
     * @return string|int|float|null raw replayed value (int for FK columns, string for ticket_type/decimal-cast
     *                               columns); null only when a logged value was null — callers decide via ??
     */
    public static function valueFromEvents(array $events, string $column, \DateTimeInterface|string $at, mixed $fallback): string|int|float|null
    {
        $anchor = Carbon::parse($at);

        // --- STEP 1: seed — value in effect at the start of recorded history ---
        $seeded = false;
        $seed = null;
        foreach ($events as $event) {
            if ($event['action'] === 'created' && array_key_exists($column, $event['new_values'])) {
                $seed = $event['new_values'][$column];
                $seeded = true;
                break;
            }
            if (array_key_exists($column, $event['old_values'])) {
                $seed = $event['old_values'][$column];
                $seeded = true;
                break;
            }
        }

        // --- STEP 2: forward walk — apply changes up to and including $at ---
        $value = $seed;
        foreach ($events as $event) {
            if (Carbon::parse($event['created_at'])->gt($anchor)) {
                break;
            }
            if (($event['action'] === 'updated' || $event['action'] === 'fare_updated')
                && array_key_exists($column, $event['new_values'])) {
                $value = $event['new_values'][$column];
                $seeded = true;
            }
        }

        if (! $seeded) {
            // No history for this column — natural fallback (rule 4).
            return is_scalar($fallback) && ! is_bool($fallback) ? $fallback : null;
        }

        return is_scalar($value) && ! is_bool($value) ? $value : null;
    }
}
