<?php

namespace App\Services;

use App\Enums\PassengerType;
use App\Enums\TicketType;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use App\Support\LogReplay;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BookingService
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function calculatePassengerType($dateOfBirth, $stayDuration = null): string
    {
        if (! $dateOfBirth) {
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
        if (! $stayDuration) {
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

    /**
     * Package value for a passenger, reconstructed from HISTORIC prices as of
     * $at (the uniform historic anchor: max(created_at, lastSwapAt) — or
     * now() at creation/add/swap moments), never from live rows.
     *
     * With $at = now() the result is numerically identical to the old live
     * calculation: replaying up to now always lands on the live values.
     */
    public function calculatePackageValue(Passenger $passenger, ?CarbonInterface $at = null): float
    {
        $at ??= now();

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

        if ($serviceRequired !== 'visa_only') {
            if ($package && $package->is_double_ticket) {
                // Replay against the passenger's own fare FKs (package-level
                // fares are the pre-D2 fallback when the FKs are unset).
                $inboundFare = $passenger->ticketFareInbound ?? $package->ticketFareInbound;
                $outboundFare = $passenger->ticketFareOutbound ?? $package->ticketFareOutbound;
                $inboundAmount = $inboundFare ? (float) $this->historicFareValue($inboundFare, 'selling_fare', $at) : 0;
                $outboundAmount = $outboundFare ? (float) $this->historicFareValue($outboundFare, 'selling_fare', $at) : 0;
                $baseFare = $inboundAmount + $outboundAmount;
                // Percentages come from the INBOUND fare, with the historical
                // ?? 70 / ?? 30 defaults preserved (null fare or null replayed
                // value ⇒ default).
                $ticketAmount = match ($passengerType) {
                    'child' => $baseFare * ((float) (($inboundFare ? $this->historicFareValue($inboundFare, 'child_fare_percentage', $at) : null) ?? 70)) / 100,
                    'infant' => $baseFare * ((float) (($inboundFare ? $this->historicFareValue($inboundFare, 'infant_fare_percentage', $at) : null) ?? 30)) / 100,
                    default => $baseFare,
                };
            } elseif ($ticketFare) {
                $sellingFare = (float) $this->historicFareValue($ticketFare, 'selling_fare', $at);
                $offerPrice = $this->historicFareValue($ticketFare, 'offer_price', $at);
                $ticketType = $this->historicFareValue($ticketFare, 'ticket_type', $at);
                $baseFare = strtolower((string) $ticketType) === TicketType::OFFER->value
                    ? (float) ($offerPrice ?? $sellingFare)
                    : $sellingFare;
                $ticketAmount = match ($passengerType) {
                    'child' => $baseFare * ((float) $this->historicFareValue($ticketFare, 'child_fare_percentage', $at)) / 100,
                    'infant' => $baseFare * ((float) $this->historicFareValue($ticketFare, 'infant_fare_percentage', $at)) / 100,
                    default => $baseFare,
                };
            }
        }

        if ($serviceRequired !== 'ticket_only' && $package) {
            // visa_selling_price_id replayed to the era's immutable price row.
            $visaSellingPriceId = $package->valueAt('visa_selling_price_id', $at);
            $visaAmount = $visaSellingPriceId
                ? (float) (VisaSellingPrice::find($visaSellingPriceId)?->selling_price ?? 0)
                : 0;
            $serviceChargeAmount = (float) ($package->valueAt('service_charge', $at) ?? 0);
        }

        if ($serviceRequired === 'ticket_only' && $package) {
            $serviceChargeAmount = (float) ($package->valueAt('service_charge', $at) ?? 0);
        }

        return $ticketAmount + $visaAmount + $serviceChargeAmount + (float) ($passenger->extra_charge ?? 0);
    }

    /**
     * Historic raw value of a fare column at $at. Honors a preloaded
     * updateLogs relation (loadMissing below) so batch operations replay
     * in-memory after a single query per fare.
     *
     * @return string|int|float|null raw replayed value; ?? composition at the
     *                               call site preserves legacy defaults
     *                               (offer ?? selling, pct ?? 70/30)
     */
    private function historicFareValue(TicketFare $fare, string $column, CarbonInterface $at): string|int|float|null
    {
        $fare->loadMissing('updateLogs');

        $fallback = $fare->{$column};
        if ($fallback instanceof \BackedEnum) {
            $fallback = $fallback->value;
        }

        return LogReplay::valueFromEvents(
            LogReplay::eventsFromLogs($fare->updateLogs),
            $column,
            $at,
            $fallback
        );
    }

    public function recalculateBookingTotal(Booking $booking): float
    {
        foreach ($booking->passengers as $passenger) {
            $passenger->package_value = $this->calculatePackageValue($passenger);
            $passenger->save();
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
        $booking->save();

        return $total;
    }

    public function syncFinancials(Booking $booking, ?string $reason = null): void
    {
        $oldTotal = (float) $booking->total_value;
        $oldDiscount = (float) $booking->discount_amount;

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
            $booking->save();
        }

        $invoice = $booking->invoice;
        if ($invoice) {
            $oldDiscounted = max(0, $oldTotal - $oldDiscount);
            $newDiscounted = max(0, $booking->total_value - $discountAmount);
            $delta = $newDiscounted - $oldDiscounted;

            $this->invoiceService->updateTotals(
                $invoice,
                (float) $invoice->total_amount + $delta,
                $reason
            );
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

        return 'INV-'.date('Y').'-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function generateInvoiceId(int $branchId): string
    {
        $year = date('y');
        $branch = Branch::find($branchId);
        $prefix = strtoupper($branch?->branch_code ?? '(###)');

        do {
            $random = substr(str_shuffle('0123456789'), 0, 4);
            $invoiceId = $prefix.'-'.$random.$year;
        } while (Booking::where('invoice_id', $invoiceId)->exists());

        return $invoiceId;
    }

    public function getFingerprintCharge($districtId, ?string $location = null): float
    {
        if (! $districtId || ! $location || strtolower($location) === 'office') {
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
        $package = Package::findOrFail($data['package_id']);
        $branchId = $data['booking_branch_id'] ?? $data['fingerprint_branch_id'] ?? null;

        $booking = Booking::create([
            'user_id' => $data['user_id'] ?? auth()->id(),
            'customer_id' => $data['customer_id'],
            'district_id' => $data['district_id'] ?? null,
            'fingerprint_branch_id' => $data['fingerprint_branch_id'] ?? null,
            'package_id' => $package->id,
            'package_name' => $package->package_name,
            'fingerprint_charge_id' => $data['fingerprint_charge_id'] ?? null,
            'booking_branch_id' => $branchId,
            'invoice_id' => $data['invoice_id'] ?? $this->generateInvoiceId($branchId),
            'date_gap_id' => $data['date_gap_id'] ?? FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => $data['fingerprint_location'] ?? 'Office',
            'pax_qty' => count($data['passengers']),
            'discount_type' => $data['discount_type'] ?? 'fixed_amount',
            'discount_value' => $data['discount_value'] ?? 0,
            'discount_amount' => 0,
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

    public function createInvoiceForBooking(Booking $booking): Invoice
    {
        return app(InvoiceService::class)->createForBooking($booking);
    }
}
