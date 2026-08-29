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
use App\Models\Passenger;
use App\Models\TicketFare;

class ProfitCalculationService
{
    public function recalculatePassengerProfit(Passenger $passenger): float
    {
        $passenger->unsetRelation('allIssuedTickets');
        $passenger->unsetRelation('visaSubmission');

        $breakdown = $this->getPassengerProfitBreakdown($passenger);

        $passenger->profit = round($breakdown['total'], 6);
        $passenger->saveQuietly();

        return (float) $passenger->profit;
    }

    public function recalculateBookingProfit(Booking $booking): float
    {
        $booking->loadMissing('passengers', 'fingerprint', 'fingerprintCharge');

        foreach ($booking->passengers as $passenger) {
            $this->recalculatePassengerProfit($passenger);
        }

        $fingerprintProfit = 0;

        if ($booking->fingerprint) {
            $fingerprintProfit = $this->calculateFingerprintProfit($booking->fingerprint);

            $booking->fingerprint->profit = round($fingerprintProfit, 6);
            $booking->fingerprint->saveQuietly();
        }

        $effectivePassengerProfit = 0;
        $allPassengersEffective = $booking->passengers->isNotEmpty();

        foreach ($booking->passengers as $passenger) {
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
        ];
    }

    public function backfillAllBookings(): void
    {
        Booking::query()
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

    private function calculateServiceCharge(Passenger $passenger): float
    {
        if (! $this->isVisaProfitEffective($passenger) || ! $this->isTicketProfitEffective($passenger)) {
            return 0.0;
        }

        return (float) ($passenger->booking->package->service_charge ?? 0);
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
