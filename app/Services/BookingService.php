<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Enums\PassengerType;
use Carbon\Carbon;

class BookingService
{
    public function calculatePassengerType($dateOfBirth): string
    {
        if (!$dateOfBirth) {
            return PassengerType::ADULT->value;
        }

        $dob = Carbon::parse($dateOfBirth);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        return match (true) {
            $ageInMonths < 24 => PassengerType::INFANT->value,
            $ageInMonths < 144 => PassengerType::CHILD->value,
            default => PassengerType::ADULT->value,
        };
    }

    public function calculateDiscount(float $total, string $type, float $value): float
    {
        if ($value <= 0) {
            return 0;
        }

        if ($type === 'percentage') {
            return $total * ($value / 100);
        }

        return min($value, $total);
    }

    public function calculateTotal(Booking $booking): array
    {
        $passengers = $booking->passengers;
        $package = $booking->package;
        
        $packageValue = $package ? ($package->offer_price ?? $package->regular_price) * $passengers->count() : 0;
        
        $fingerprintCharge = $this->getFingerprintCharge(
            $booking->district_id,
            $booking->fingerprint_location
        );
        
        $subtotal = $packageValue + $fingerprintCharge;
        $discount = $this->calculateDiscount(
            $subtotal,
            $booking->discount_type ?? 'fixed',
            $booking->discount_value ?? 0
        );
        
        $total = $subtotal - $discount;

        return [
            'package_value' => $packageValue,
            'fingerprint_charge' => $fingerprintCharge,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'passenger_count' => $passengers->count(),
        ];
    }

    public function generateInvoiceNumber(): string
    {
        $lastBooking = Booking::orderBy('id', 'desc')->first();
        $nextNumber = $lastBooking ? $lastBooking->id + 1 : 1;
        
        return 'INV-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getFingerprintCharge($districtId, string $location = 'Office'): float
    {
        if (!$districtId || $location === 'Office') {
            return 0;
        }

        $fingerprintCharge = FingerprintCharge::where('district_id', $districtId)->first();

        return $fingerprintCharge ? (float) $fingerprintCharge->fingerprint_charge : 0;
    }

    public function validatePassengerCount(int $count, int $max = 10): bool
    {
        return $count > 0 && $count <= $max;
    }

    public function processBookingWithPassengers(array $data): Booking
    {
        $booking = Booking::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'customer_id' => $data['customer_id'],
            'district_id' => $data['district_id'] ?? null,
            'office_id' => $data['office_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'fingerprint_location' => $data['fingerprint_location'] ?? 'Office',
            'fingerprint_office' => $data['fingerprint_office'] ?? null,
            'pax_qty' => count($data['passengers']),
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? 0,
            'remarks' => $data['remarks'] ?? null,
        ]);

        foreach ($data['passengers'] as $passengerData) {
            $passengerData['booking_id'] = $booking->id;
            $passengerData['passenger_type'] = $this->calculatePassengerType($passengerData['date_of_birth']);
            
            Passenger::create($passengerData);
        }

        return $booking;
    }

    public function updateBookingTotals(Booking $booking): array
    {
        $totals = $this->calculateTotal($booking);
        
        return $totals;
    }
}