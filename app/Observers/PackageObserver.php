<?php

namespace App\Observers;

use App\Enums\CancelledBookingStatus;
use App\Models\Package;
use App\Models\PackageUpdateLog;
use App\Services\ProfitCalculationService;
use Illuminate\Support\Facades\Auth;

class PackageObserver
{
    // service_charge kept for logging; profit recalc from snapshot is harmless
    // ticket_fare_* and is_double_ticket removed — locked packages can't change them
    private const PROFIT_FIELDS = [
        'service_charge',
    ];

    public function created(Package $package): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $package->attributesToArray(),
        ]);
    }

    public function updated(Package $package): void
    {
        if ($package->wasChanged(self::PROFIT_FIELDS)) {
            $package->bookings()
                ->whereDoesntHave('cancelledBooking', fn ($q) => $q->where('status', CancelledBookingStatus::CANCELLED->value))
                ->chunkById(100, function ($bookings): void {
                    $service = app(ProfitCalculationService::class);
                    foreach ($bookings as $booking) {
                        $service->recalculateBookingProfit($booking);
                    }
                });
        }

        $dirty = $package->getDirty();
        if (empty($dirty)) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $original = $package->getOriginal();
        $oldValues = [];
        $newValues = [];
        foreach ($dirty as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function deleting(Package $package): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $oldValues = collect($package->attributesToArray())
            ->except(['created_at', 'updated_at'])
            ->toArray();

        PackageUpdateLog::create([
            'package_id' => $package->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
        ]);
    }
}
