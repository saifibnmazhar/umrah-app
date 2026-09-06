<?php

namespace App\Services;

use App\Enums\FingerprintLocation;
use App\Enums\PassengerType;
use App\Enums\PaymentBy;
use App\Enums\ServiceRequired;
use App\Enums\TicketType;
use App\Enums\VisaStatus;
use App\Models\Booking;
use App\Models\Fingerprint;
use App\Models\IssuedTicketLog;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Models\VisaUpdateLog;

class ProfitCalculationService
{
    public function recalculatePassengerProfit(Passenger $passenger): float
    {
        $passenger->unsetRelation('allIssuedTickets');
        $passenger->unsetRelation('visaSubmission');

        $breakdown = $this->getPassengerProfitBreakdown($passenger);

        $visaEffectiveAt = $this->determineVisaEffectiveDate($passenger);
        $ticketEffectiveAt = $this->determineTicketEffectiveDate($passenger);
        $serviceChargeEffectiveAt = ($visaEffectiveAt && $ticketEffectiveAt)
            ? max($visaEffectiveAt, $ticketEffectiveAt)
            : null;

        $passenger->updateQuietly(array_merge(
            $breakdown,
            [
                'profit' => $breakdown['total'],
                'visa_profit_effective_at' => $visaEffectiveAt,
                'ticket_profit_effective_at' => $ticketEffectiveAt,
                'service_charge_effective_at' => $serviceChargeEffectiveAt,
            ]
        ));

        return (float) $passenger->profit;
    }

    public function recalculateBookingProfit(Booking $booking): float
    {
        $booking->loadMissing('passengers', 'fingerprint', 'fingerprintCharge');

        foreach ($booking->passengers as $passenger) {
            if ($passenger->is_cancelled) {
                $passenger->profit = 0;
                $passenger->saveQuietly();

                continue;
            }
            $this->recalculatePassengerProfit($passenger);
        }

        $fingerprintProfit = 0;

        if ($booking->fingerprint) {
            $fingerprintProfit = $this->calculateFingerprintProfit($booking->fingerprint);

            $booking->fingerprint->profit = round($fingerprintProfit, 6);
            $booking->fingerprint->saveQuietly();
        }

        $effectivePassengerProfit = 0;
        $activePassengers = $booking->passengers->where('is_cancelled', false);
        $allPassengersEffective = $activePassengers->isNotEmpty();

        foreach ($activePassengers as $passenger) {
            if ($this->isPassengerProfitEffective($passenger)) {
                $effectivePassengerProfit += (float) $passenger->profit;
            } else {
                $allPassengersEffective = false;
            }
        }

        $discount = $allPassengersEffective ? (float) ($booking->discount_amount ?? 0) : 0;

        $booking->profit = round(
            $effectivePassengerProfit + $fingerprintProfit - $discount,
            6
        );
        $booking->saveQuietly();

        return (float) $booking->profit;
    }

    public function calculateFingerprintProfit(Fingerprint $fingerprint): float
    {
        $location = $fingerprint->booking->fingerprint_location;
        $cost = (float) ($fingerprint->cost ?? 0);

        if ($location !== FingerprintLocation::HOME || $cost <= 0) {
            return 0.0;
        }

        $charge = (float) ($fingerprint->booking->fingerprintCharge?->fingerprint_charge ?? 0);

        return $charge - $cost;
    }

    public function getPassengerProfitBreakdown(Passenger $passenger): array
    {
        $visaProfit = $this->calculateVisaProfit($passenger);
        $ticketProfit = $this->calculateTicketProfit($passenger);
        $additionalTicketProfit = $this->calculateAdditionalTicketProfit($passenger);
        $reIssueProfit = $this->calculateReIssueProfit($passenger);
        $refundProfit = $this->calculateRefundProfit($passenger);
        $reIssueCost = $this->calculateReIssueCost($passenger);
        $serviceCharge = $this->calculateServiceCharge($passenger);

        $visaEffectiveAt = $this->determineVisaEffectiveDate($passenger);
        $ticketEffectiveAt = $this->determineTicketEffectiveDate($passenger);
        $serviceChargeEffectiveAt = ($visaEffectiveAt && $ticketEffectiveAt)
            ? max($visaEffectiveAt, $ticketEffectiveAt)
            : null;

        return [
            'visa_profit' => round($visaProfit, 6),
            'ticket_profit' => round($ticketProfit, 6),
            'additional_ticket_profit' => round($additionalTicketProfit, 6),
            're_issue_profit' => round($reIssueProfit, 6),
            'refund_profit' => round($refundProfit, 6),
            're_issue_cost' => round($reIssueCost, 6),
            'service_charge' => round($serviceCharge, 6),
            'total' => round(
                $visaProfit
                    + $ticketProfit
                    + $additionalTicketProfit
                    + $reIssueProfit
                    + $refundProfit
                    + $serviceCharge
                    - $reIssueCost,
                6
            ),
            'visa_profit_effective_at' => $visaEffectiveAt,
            'ticket_profit_effective_at' => $ticketEffectiveAt,
            'service_charge_effective_at' => $serviceChargeEffectiveAt,
        ];
    }

    public function getCustomerProfitBreakdown(Booking $booking): array
    {
        $booking->loadMissing('passengers', 'fingerprint', 'fingerprintCharge');

        $passengers = [];
        $allPassengersEffective = $booking->passengers->where('is_cancelled', false)->isNotEmpty();

        foreach ($booking->passengers as $passenger) {
            if ($passenger->is_cancelled) {
                continue;
            }

            $effective = $this->isPassengerProfitEffective($passenger);

            $passengers[] = [
                'name' => trim($passenger->first_name.' '.$passenger->last_name),
                'profit' => round((float) $passenger->profit, 6),
                'effective' => $effective,
            ];

            if (! $effective) {
                $allPassengersEffective = false;
            }
        }

        $fingerprintEffective = $booking->fingerprint
            && $booking->fingerprint_location === FingerprintLocation::HOME
            && (float) ($booking->fingerprint->cost ?? 0) > 0;

        $fingerprint = $fingerprintEffective ? [
            'effective' => true,
            'location' => $booking->fingerprint_location?->value,
            'charge' => round((float) ($booking->fingerprintCharge?->fingerprint_charge ?? 0), 6),
            'cost' => round((float) ($booking->fingerprint?->cost ?? 0), 6),
            'profit' => round((float) ($booking->fingerprint?->profit ?? 0), 6),
            'reason' => null,
        ] : [
            'effective' => false,
            'location' => $booking->fingerprint ? $booking->fingerprint_location?->value : null,
            'charge' => 0.0,
            'cost' => 0.0,
            'profit' => 0.0,
            'reason' => ! $booking->fingerprint
                ? 'No fingerprint record'
                : ($booking->fingerprint_location === FingerprintLocation::HOME
                    ? 'Fingerprint cost not set'
                    : 'Fingerprint location is office'),
        ];

        $recap = $this->getPassengerProfitBreakdownForPassengers($booking);

        return $recap + [
            'passengers' => $passengers,
            'fingerprint' => $fingerprint,
            'discount' => [
                'effective' => $allPassengersEffective,
                'amount' => $allPassengersEffective ? round((float) ($booking->discount_amount ?? 0), 6) : 0.0,
            ],
        ];
    }

    public function getPassengerProfitBreakdownDetailed(Passenger $passenger): array
    {
        $breakdown = $this->getPassengerProfitBreakdown($passenger);

        $visa = $this->visaBreakdown($passenger);
        $ticket = $this->ticketBreakdown($passenger);
        $additional = $this->additionalTicketsBreakdown($passenger);

        if ($visa) {
            $breakdown['visa'] = $visa;
        }
        if ($ticket) {
            $breakdown['ticket'] = $ticket;
        }
        $breakdown['additional_tickets'] = $additional;

        return $breakdown;
    }

    private function getPassengerProfitBreakdownForPassengers(Booking $booking): array
    {
        $total = 0.0;

        foreach ($booking->passengers as $passenger) {
            if ($passenger->is_cancelled) {
                continue;
            }
            if ($this->isPassengerProfitEffective($passenger)) {
                $total += (float) $passenger->profit;
            }
        }

        $fingerprintProfit = $booking->fingerprint
            && $booking->fingerprint_location === FingerprintLocation::HOME
            && (float) ($booking->fingerprint->cost ?? 0) > 0
            ? (float) ($booking->fingerprint?->profit ?? 0)
            : 0.0;

        $activePassengers = $booking->passengers->where('is_cancelled', false);
        $allPassengersEffective = $activePassengers->isNotEmpty();
        foreach ($activePassengers as $passenger) {
            if (! $this->isPassengerProfitEffective($passenger)) {
                $allPassengersEffective = false;
                break;
            }
        }

        $discount = $allPassengersEffective ? (float) ($booking->discount_amount ?? 0) : 0;

        return [
            'total' => round($total + $fingerprintProfit - $discount, 6),
        ];
    }

    public function backfillAllBookings(): void
    {
        Booking::query()
            ->where('is_cancelled', false)
            ->with([
                'passengers.visaSubmission.cancelledSubmissions',
                'passengers.allIssuedTickets.ticketFare',
                'passengers.allIssuedTickets.reIssuedTickets',
                'passengers.allIssuedTickets.refundedTickets',
                'package.ticketFare',
                'package.ticketFareInbound',
                'package.ticketFareOutbound',
                'fingerprint.booking.fingerprintCharge',
                'fingerprintCharge',
            ])
            ->chunkById(100, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $this->recalculateBookingProfit($booking);
                }
            });
    }

    private function calculateVisaProfit(Passenger $passenger): float
    {
        if (! $this->isVisaProfitEffective($passenger)) {
            return 0.0;
        }

        $visa = $passenger->visaSubmission;

        if (! $visa) {
            return 0.0;
        }

        $cancellationFees = (float) $visa->cancelledSubmissions->sum('cancellation_fee');

        return (float) ($visa->visaSellingPrice?->selling_price ?? 0)
            - (float) ($visa->net_visa_cost ?? 0)
            - (float) ($visa->agent_commission ?? 0)
            - (float) ($visa->additional_cost ?? 0)
            - $cancellationFees;
    }

    private function calculateTicketProfit(Passenger $passenger): float
    {
        $tickets = $this->regularTickets($passenger);

        if ($tickets->isEmpty() || ! $this->isTicketProfitEffective($passenger)) {
            return 0.0;
        }

        return $this->getPackageTicketSellingFare($passenger)
            - (float) $tickets->sum('net_fare');
    }

    private function calculateAdditionalTicketProfit(Passenger $passenger): float
    {
        return (float) $passenger->allIssuedTickets
            ->filter(fn ($t) => $t->issue_type === 'additional'
                && in_array($t->status, ['issued', 're-issued', 'refunded'], true))
            ->sum(fn ($t) => $this->fareSellingPrice($t->ticketFare, $passenger) - (float) ($t->net_fare ?? 0));
    }

    private function calculateReIssueProfit(Passenger $passenger): float
    {
        return (float) $this->passengerReIssues($passenger)
            ->filter(fn ($r) => $r->payment_by === PaymentBy::CUSTOMER)
            ->sum('service_charge');
    }

    private function calculateReIssueCost(Passenger $passenger): float
    {
        return (float) $this->passengerReIssues($passenger)
            ->filter(fn ($r) => $r->payment_by === PaymentBy::COMPANY)
            ->sum('total_cost');
    }

    private function calculateRefundProfit(Passenger $passenger): float
    {
        return (float) $passenger->allIssuedTickets
            ->flatMap(fn ($t) => $t->refundedTickets)
            ->sum('service_charge');
    }

    public function additionalTicketEffectiveDate($ticket): ?string
    {
        if ($ticket->issued_date) {
            return $ticket->issued_date instanceof \DateTimeInterface
                ? $ticket->issued_date->format('Y-m-d H:i:s')
                : (string) $ticket->issued_date;
        }

        $log = IssuedTicketLog::where('issued_ticket_id', $ticket->id)
            ->where('new_data->status', 'issued')
            ->latest('created_at')
            ->first();

        return $log?->created_at?->format('Y-m-d H:i:s');
    }

    private function dateInRange(?string $date, string $from, string $to): bool
    {
        return $date !== null && $date >= $from && $date <= $to;
    }

    private function createdInRange($model, string $from, string $to): bool
    {
        $created = $model->created_at;

        if (! $created) {
            return false;
        }

        $date = $created instanceof \DateTimeInterface
            ? $created->format('Y-m-d H:i:s')
            : (string) $created;

        return $this->dateInRange($date, $from, $to);
    }

    public function effectiveAdditionalTicketProfit(Passenger $passenger, string $from, string $to): float
    {
        return (float) $passenger->allIssuedTickets
            ->sum(fn ($t) => $this->additionalTicketEffectiveValue($t, $passenger, $from, $to));
    }

    public function additionalTicketEffectiveValue($ticket, Passenger $passenger, string $from, string $to): float
    {
        if ($ticket->issue_type !== 'additional'
            || ! in_array($ticket->status, ['issued', 're-issued', 'refunded'], true)) {
            return 0.0;
        }

        if (! $this->dateInRange($this->additionalTicketEffectiveDate($ticket), $from, $to)) {
            return 0.0;
        }

        return $this->fareSellingPrice($ticket->ticketFare, $passenger) - (float) ($ticket->net_fare ?? 0);
    }

    public function effectiveReIssueProfit(Passenger $passenger, string $from, string $to): float
    {
        return (float) $this->passengerReIssues($passenger)
            ->filter(fn ($r) => $r->payment_by === PaymentBy::CUSTOMER)
            ->filter(fn ($r) => $this->createdInRange($r, $from, $to))
            ->sum('service_charge');
    }

    public function effectiveReIssueCost(Passenger $passenger, string $from, string $to): float
    {
        return (float) $this->passengerReIssues($passenger)
            ->filter(fn ($r) => $r->payment_by === PaymentBy::COMPANY)
            ->filter(fn ($r) => $this->createdInRange($r, $from, $to))
            ->sum('total_cost');
    }

    public function effectiveRefundProfit(Passenger $passenger, string $from, string $to): float
    {
        return (float) $passenger->allIssuedTickets
            ->flatMap(fn ($t) => $t->refundedTickets)
            ->filter(fn ($r) => $this->createdInRange($r, $from, $to))
            ->sum('service_charge');
    }

    public function calculateEffectiveDateProfitDetailed(Passenger $passenger, string $from, string $to): array
    {
        $visa = $this->effectiveComponentValue($passenger, 'visa_profit', 'visa_profit_effective_at', $from, $to);
        $ticket = $this->effectiveComponentValue($passenger, 'ticket_profit', 'ticket_profit_effective_at', $from, $to);
        $service = $this->effectiveComponentValue($passenger, 'service_charge', 'service_charge_effective_at', $from, $to);
        $additional = $this->effectiveAdditionalTicketProfit($passenger, $from, $to);
        $reIssueProfit = $this->effectiveReIssueProfit($passenger, $from, $to);
        $refundProfit = $this->effectiveRefundProfit($passenger, $from, $to);
        $reIssueCost = $this->effectiveReIssueCost($passenger, $from, $to);

        return [
            'visa_profit' => round($visa, 6),
            'ticket_profit' => round($ticket, 6),
            'service_charge' => round($service, 6),
            'additional_ticket_profit' => round($additional, 6),
            're_issue_profit' => round($reIssueProfit, 6),
            'refund_profit' => round($refundProfit, 6),
            're_issue_cost' => round($reIssueCost, 6),
            'total' => round($visa + $ticket + $service + $additional + $reIssueProfit + $refundProfit - $reIssueCost, 6),
        ];
    }

    private function effectiveComponentValue(Passenger $passenger, string $profitColumn, string $effectiveColumn, string $from, string $to): float
    {
        $effectiveAt = $passenger->{$effectiveColumn};

        if (! $effectiveAt) {
            return 0.0;
        }

        $date = $effectiveAt instanceof \DateTimeInterface
            ? $effectiveAt->format('Y-m-d H:i:s')
            : (string) $effectiveAt;

        if (! $this->dateInRange($date, $from, $to)) {
            return 0.0;
        }

        return (float) ($passenger->{$profitColumn} ?? 0);
    }

    private function visaBreakdown(Passenger $passenger): ?array
    {
        if (! $this->isVisaProfitEffective($passenger)) {
            return null;
        }

        $visa = $passenger->visaSubmission;

        if (! $visa) {
            return null;
        }

        $sellingPrice = (float) ($visa->visaSellingPrice?->selling_price ?? 0);
        $netVisaCost = (float) ($visa->net_visa_cost ?? 0);
        $agentCommission = (float) ($visa->agent_commission ?? 0);
        $additionalCost = (float) ($visa->additional_cost ?? 0);
        $cancellationFees = (float) $visa->cancelledSubmissions->sum('cancellation_fee');

        return [
            'selling_price' => round($sellingPrice, 6),
            'net_visa_cost' => round($netVisaCost, 6),
            'agent_commission' => round($agentCommission, 6),
            'additional_cost' => round($additionalCost, 6),
            'cancellation_fees' => round($cancellationFees, 6),
            'profit' => round($sellingPrice - $netVisaCost - $agentCommission - $additionalCost - $cancellationFees, 6),
        ];
    }

    private function ticketBreakdown(Passenger $passenger): ?array
    {
        $tickets = $this->regularTickets($passenger);

        if ($tickets->isEmpty() || ! $this->isTicketProfitEffective($passenger)) {
            return null;
        }

        $sellingFare = $this->getPackageTicketSellingFare($passenger);

        $netFares = [];
        $index = 0;
        foreach ($tickets as $ticket) {
            $index++;
            $netFares[] = [
                'issue_type' => $ticket->issue_type ?: 'regular',
                'label' => $this->ticketTypeLabel($ticket->issue_type, $index),
                'net_fare' => round((float) ($ticket->net_fare ?? 0), 6),
            ];
        }

        return [
            'selling_fare' => round($sellingFare, 6),
            'net_fares' => $netFares,
            'profit' => round($sellingFare - (float) $tickets->sum('net_fare'), 6),
        ];
    }

    private function additionalTicketsBreakdown(Passenger $passenger): array
    {
        $tickets = $passenger->allIssuedTickets
            ->filter(fn ($t) => $t->issue_type === 'additional'
                && in_array($t->status, ['issued', 're-issued', 'refunded'], true));

        $items = [];
        $profit = 0.0;

        foreach ($tickets as $ticket) {
            $sellingFare = $this->fareSellingPrice($ticket->ticketFare, $passenger);
            $netFare = (float) ($ticket->net_fare ?? 0);
            $itemProfit = $sellingFare - $netFare;
            $profit += $itemProfit;

            $items[] = [
                'selling_fare' => round($sellingFare, 6),
                'net_fare' => round($netFare, 6),
                'profit' => round($itemProfit, 6),
            ];
        }

        return [
            'items' => $items,
            'profit' => round($profit, 6),
        ];
    }

    private function ticketTypeLabel(?string $issueType, int $index): string
    {
        return match ($issueType) {
            'pending_outbound' => 'Pending Outbound',
            'regular', null => 'Regular',
            default => 'Ticket '.$index,
        };
    }

    private function calculateServiceCharge(Passenger $passenger): float
    {
        if (! $this->isVisaProfitEffective($passenger) || ! $this->isTicketProfitEffective($passenger)) {
            return 0.0;
        }

        return (float) ($passenger->booking->package->service_charge ?? 0);
    }

    private function determineVisaEffectiveDate(Passenger $passenger): ?string
    {
        if (! $this->isVisaProfitEffective($passenger)) {
            return null;
        }

        $visa = $passenger->visaSubmission;
        if (! $visa) {
            return null;
        }

        $issuedLog = VisaUpdateLog::where('visa_submission_id', $visa->id)
            ->where('new_values->status', 'issued')
            ->latest('created_at')
            ->first();

        if ($issuedLog) {
            return $issuedLog->created_at->toDateTimeString();
        }

        $submittedLog = VisaUpdateLog::where('visa_submission_id', $visa->id)
            ->where('new_values->status', 'submitted')
            ->latest('created_at')
            ->first();

        return $submittedLog?->created_at?->toDateTimeString();
    }

    private function determineTicketEffectiveDate(Passenger $passenger): ?string
    {
        if (! $this->isTicketProfitEffective($passenger)) {
            return null;
        }

        $tickets = $this->regularTickets($passenger);
        if ($tickets->isEmpty()) {
            return null;
        }

        return $tickets->max('issued_date')?->toDateTimeString();
    }

    private function isVisaProfitEffective(Passenger $passenger): bool
    {
        if ($passenger->service_required === ServiceRequired::TICKET_ONLY) {
            return true;
        }

        return $passenger->visaSubmission?->status === VisaStatus::ISSUED;
    }

    private function isTicketProfitEffective(Passenger $passenger): bool
    {
        if ($passenger->service_required === ServiceRequired::VISA_ONLY) {
            return true;
        }

        $tickets = $this->regularTickets($passenger);

        return $tickets->isNotEmpty()
            && $tickets->every(fn ($t) => in_array($t->status, ['issued', 're-issued', 'refunded'], true));
    }

    private function isPassengerProfitEffective(Passenger $passenger): bool
    {
        return $this->isVisaProfitEffective($passenger)
            && $this->isTicketProfitEffective($passenger);
    }

    private function regularTickets(Passenger $passenger)
    {
        return $passenger->allIssuedTickets->filter(
            fn ($t) => $t->issue_type === null
                || in_array($t->issue_type, ['regular', 'pending_outbound'], true)
        );
    }

    private function passengerReIssues(Passenger $passenger)
    {
        return $passenger->allIssuedTickets->flatMap(fn ($t) => $t->reIssuedTickets);
    }

    private function getPackageTicketSellingFare(Passenger $passenger): float
    {
        $package = $passenger->booking->package;

        if (! $package) {
            return 0.0;
        }

        if ($package->is_double_ticket) {
            return $this->fareSellingPrice($package->ticketFareInbound, $passenger)
                + $this->fareSellingPrice($package->ticketFareOutbound, $passenger);
        }

        return $this->fareSellingPrice($package->ticketFare, $passenger);
    }

    private function fareSellingPrice(?TicketFare $fare, Passenger $passenger): float
    {
        if (! $fare) {
            return 0.0;
        }

        $base = $fare->ticket_type === TicketType::OFFER
            ? (float) ($fare->offer_price ?? 0)
            : (float) ($fare->selling_fare ?? 0);

        return match ($passenger->passenger_type) {
            PassengerType::CHILD => $base * (float) ($fare->child_fare_percentage ?? 100) / 100,
            PassengerType::INFANT => $base * (float) ($fare->infant_fare_percentage ?? 100) / 100,
            default => $base,
        };
    }
}
