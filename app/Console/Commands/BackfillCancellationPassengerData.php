<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\CancelledBooking;
use App\Models\PassengerStatus;
use Illuminate\Console\Command;

class BackfillCancellationPassengerData extends Command
{
    protected $signature = 'backfill:cancellation-passenger-data';

    protected $description = 'Backfill passenger statuses and snapshots for old cancelled bookings';

    public function handle(): int
    {
        $holdStatus = PassengerStatus::firstOrCreate(['name' => 'Hold']);
        $cancelStatus = PassengerStatus::firstOrCreate(['name' => 'Cancel']);

        $this->newLine();
        $this->info('Backfilling cancellation passenger data...');
        $this->newLine();

        $this->backfillProcessingBookings($holdStatus);
        $this->backfillConfirmedBookings($cancelStatus);

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function backfillProcessingBookings(PassengerStatus $holdStatus): void
    {
        $this->line('--- PROCESSING cancellations (snapshot IS NULL) ---');

        $cancelledBookings = CancelledBooking::where('status', 'cancellation processing')
            ->whereNull('passenger_statuses_snapshot')
            ->get();

        if ($cancelledBookings->isEmpty()) {
            $this->line('  No records to backfill.');
            $this->newLine();

            return;
        }

        $bookingsUpdated = 0;
        $passengersUpdated = 0;

        foreach ($cancelledBookings as $cancelledBooking) {
            $booking = $cancelledBooking->booking;
            if (! $booking) {
                continue;
            }

            $passengers = $booking->passengers()->get();
            if ($passengers->isEmpty()) {
                continue;
            }

            $snapshot = $passengers->pluck('passenger_status_id', 'id')->toArray();
            $cancelledBooking->update(['passenger_statuses_snapshot' => $snapshot]);

            foreach ($passengers as $passenger) {
                $currentStatusId = $passenger->passenger_status_id;
                if ($currentStatusId === null || $currentStatusId !== $holdStatus->id) {
                    $cancelStatusId = PassengerStatus::where('name', 'Cancel')->value('id');
                    if ($currentStatusId === $cancelStatusId) {
                        continue;
                    }
                    $passenger->update(['passenger_status_id' => $holdStatus->id]);
                    $passengersUpdated++;
                }
            }

            $bookingsUpdated++;
        }

        $this->line("  Bookings backfilled: {$bookingsUpdated}");
        $this->line("  Passengers set to Hold: {$passengersUpdated}");
        $this->newLine();
    }

    private function backfillConfirmedBookings(PassengerStatus $cancelStatus): void
    {
        $this->line('--- CANCELLED (confirmed) cancellations ---');

        $cancelledBookings = CancelledBooking::where('status', 'cancelled')->get();

        if ($cancelledBookings->isEmpty()) {
            $this->line('  No records to backfill.');
            $this->newLine();

            return;
        }

        $bookingsUpdated = 0;
        $passengersUpdated = 0;

        foreach ($cancelledBookings as $cancelledBooking) {
            $booking = $cancelledBooking->booking;
            if (! $booking) {
                continue;
            }

            $passengers = $booking->passengers()->get();
            if ($passengers->isEmpty()) {
                continue;
            }

            $cancelledAt = $cancelledBooking->updated_at;
            foreach ($passengers as $passenger) {
                $updates = [];
                if ($passenger->passenger_status_id !== $cancelStatus->id) {
                    $updates['passenger_status_id'] = $cancelStatus->id;
                }
                if (! $passenger->is_cancelled) {
                    $updates['is_cancelled'] = true;
                }
                if (is_null($passenger->cancelled_at)) {
                    $updates['cancelled_at'] = $cancelledAt;
                }
                if ((float) $passenger->profit !== 0.0) {
                    $updates['profit'] = 0;
                }
                if (! empty($updates)) {
                    $passenger->update($updates);
                    $passengersUpdated++;
                }
            }

            if ((float) $booking->profit !== 0.0) {
                $booking->update(['profit' => 0]);
            }

            if ($booking->invoice && $booking->invoice->status !== InvoiceStatus::REFUNDED) {
                $booking->invoice->update([
                    'status' => InvoiceStatus::REFUNDED,
                    'balance' => 0,
                ]);
            } elseif ($booking->invoice && (float) $booking->invoice->balance !== 0.0) {
                $booking->invoice->update(['balance' => 0]);
            }

            $bookingsUpdated++;
        }

        $this->line("  Bookings backfilled: {$bookingsUpdated}");
        $this->line("  Passengers updated: {$passengersUpdated}");
        $this->newLine();
    }
}
