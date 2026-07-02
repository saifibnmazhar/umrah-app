<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Passenger;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Enums\PassengerType;
use App\Enums\TicketType;
use Carbon\Carbon;

class BookingService
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}
    public function calculatePassengerType($dateOfBirth, $stayDuration = null): string
    {
        if (!$dateOfBirth) {
            return PassengerType::ADULT->value;
        }

        $dob = Carbon::parse($dateOfBirth);
        $ageInMonths = $dob->diffInMonths(Carbon::now());

        // if ($stayDuration) {
        //     $stayDays = $this->parseStayDurationDays($stayDuration);
        //     if ($stayDays !== null) {
        //         $adjustmentDays = $stayDays < 30 ? 30 : 90;
        //         $effectiveDob = $dob->copy()->subDays($adjustmentDays);
        //         $effectiveAgeInMonths = $effectiveDob->diffInMonths(Carbon::now());
        //         $ageInMonths = max($ageInMonths, $effectiveAgeInMonths);
        //     }
        // }

        return match (true) {
            $ageInMonths < 19 => PassengerType::INFANT->value,
            $ageInMonths < 139 => PassengerType::CHILD->value,
            default => PassengerType::ADULT->value,
        };
    }

    private function parseStayDurationDays($stayDuration): ?int
    {
        if (!$stayDuration) {
            return null;
        }
        if (preg_match('/(\d+)\s*Days?/', $stayDuration, $matches)) {
            return (int) $matches[1];
        }
        return null;
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

    public function calculatePackageValue(Passenger $passenger): float
    {
        $ticketFare = $passenger->ticketFare;
        $booking = $passenger->booking;
        $package = $booking->package;
        $serviceRequired = $passenger->service_required;
        if ($serviceRequired instanceof \BackedEnum) {
            $serviceRequired = $serviceRequired->value;
        }
        
        $passengerType = $passenger->passenger_type;
        if ($passengerType instanceof \BackedEnum) {
            $passengerType = $passengerType->value;
        }
        $passengerType = strtolower($passengerType);

        $ticketAmount = 0;
        $visaAmount = 0;
        $serviceChargeAmount = 0;

        if ($ticketFare && $serviceRequired !== 'visa_only') {
            $baseFare = $ticketFare->ticket_type === TicketType::OFFER
                ? (float) ($ticketFare->offer_price ?? $ticketFare->selling_fare)
                : (float) $ticketFare->selling_fare;
            $ticketAmount = match ($passengerType) {
                'child'  => $baseFare * ((float) $ticketFare->child_fare_percentage) / 100,
                'infant' => $baseFare * ((float) $ticketFare->infant_fare_percentage) / 100,
                default  => $baseFare,
            };
        }

        if ($serviceRequired !== 'ticket_only' && $package) {
            $visaAmount = (float) ($package->visaSellingPrice?->selling_price ?? 0);
            $serviceChargeAmount = (float) ($package->service_charge ?? 0);
        }

        if ($serviceRequired === 'ticket_only' && $package) {
            $serviceChargeAmount = (float) ($package->service_charge ?? 0);
        }

        return $ticketAmount + $visaAmount + $serviceChargeAmount;
    }

    public function recalculateBookingTotal(Booking $booking): float
    {
        foreach ($booking->passengers as $passenger) {
            $passenger->package_value = $this->calculatePackageValue($passenger);
            $passenger->saveQuietly();
        }

        $passengerTotal = (float) $booking->passengers->sum('package_value');
        
        $fingerprintLocation = $booking->fingerprint_location;
        if ($fingerprintLocation instanceof \BackedEnum) {
            $fingerprintLocation = $fingerprintLocation->value;
        }
        
        $fingerprintCharge = $this->getFingerprintCharge(
            $booking->district_id,
            $fingerprintLocation
        );
        
        $total = $passengerTotal + $fingerprintCharge;

        $booking->total_value = $total;
        $booking->saveQuietly();

        return $total;
    }

    public function syncFinancials(Booking $booking): void
    {
        $this->recalculateBookingTotal($booking);

        $discountType = $booking->discount_type;
        if ($discountType instanceof \BackedEnum) {
            $discountType = $discountType->value;
        }

        $discountAmount = $this->calculateDiscount(
            $booking->total_value,
            $discountType ?? 'fixed',
            $booking->discount_value ?? 0
        );

        if ($booking->discount_amount != $discountAmount) {
            $booking->discount_amount = $discountAmount;
            $booking->saveQuietly();
        }

        $invoice = $booking->invoice;
        if ($invoice) {
            $discountedTotal = max(0, $booking->total_value - $discountAmount);
            $this->invoiceService->updateTotals($invoice, $discountedTotal);
        }
    }

    public function calculateTotal(Booking $booking): array
    {
        $passengers = $booking->passengers;

        $passengerTotal = (float) $passengers->sum('package_value');

        $fingerprintLocation = $booking->fingerprint_location;
        if ($fingerprintLocation instanceof \BackedEnum) {
            $fingerprintLocation = $fingerprintLocation->value;
        }

        $fingerprintCharge = $this->getFingerprintCharge(
            $booking->district_id,
            $fingerprintLocation
        );

        $subtotal = $passengerTotal + $fingerprintCharge;
        $discount = $this->calculateDiscount(
            $subtotal,
            $booking->discount_type ?? 'fixed',
            $booking->discount_value ?? 0
        );

        $total = $subtotal - $discount;

        return [
            'package_value' => $passengerTotal,
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

    public function generateInvoiceId(int $branchId): string
    {
        $year = date('y');
        $branch = Branch::find($branchId);
        $prefix = strtoupper($branch?->branch_code ?? '(###)');

        do {
            $random = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
            $invoiceId = $prefix . $random . $year;
        } while (Booking::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }

    public function getFingerprintCharge($districtId, ?string $location = null): float
    {
        if (!$districtId || !$location || strtolower($location) === 'office') {
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
            'fingerprint_branch_id' => $data['fingerprint_branch_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'fingerprint_location' => $data['fingerprint_location'] ?? 'Office',
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

    public function createInvoiceForBooking(Booking $booking): \App\Models\Invoice
    {
        return app(\App\Services\InvoiceService::class)->createForBooking($booking);
    }
}