<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Collection;

class CostTrackingService
{
    public function getPassengerCosts(Booking $booking): Collection
    {
        $fingerprintCost = $booking->fingerprint?->cost ?? 0;
        $perPassengerFpCost = $booking->passengers->count() > 0
            ? $fingerprintCost / $booking->passengers->count()
            : 0;

        return $booking->passengers->map(fn($p) => [
            'passenger_id'     => $p->id,
            'passenger_name'   => $p->first_name . ' ' . $p->last_name,
            'fingerprint_cost' => $fingerprintCost > 0 ? $perPassengerFpCost : 0,
            'visa_cost'        => $this->getPassengerVisaCost($p),
            'ticket_cost'      => $this->getPassengerTicketCost($p),
            'total_cost'       => 0,
        ])->map(fn($item) => array_merge($item, [
            'total_cost' => $item['fingerprint_cost'] + $item['visa_cost'] + $item['ticket_cost'],
        ]));
    }

    public function getBookingCostSummary(Booking $booking): array
    {
        $passengerCosts = $this->getPassengerCosts($booking);
        return [
            'fingerprint_cost' => $passengerCosts->sum('fingerprint_cost'),
            'visa_cost'        => $passengerCosts->sum('visa_cost'),
            'ticket_cost'      => $passengerCosts->sum('ticket_cost'),
            'total_cost'       => $passengerCosts->sum('total_cost'),
            'passengers'       => $passengerCosts,
        ];
    }

    private function getPassengerVisaCost($passenger): float
    {
        $visa = $passenger->visaSubmission;
        if (!$visa || $visa->status?->value !== 'issued') return 0;
        return (float) ($visa->final_cost ?? 0);
    }

    private function getPassengerTicketCost($passenger): float
    {
        $ticket = $passenger->latestIssuedTicket;
        if (!$ticket || !in_array($ticket->status, ['issued', 're-issued'])) return 0;
        return (float) ($ticket->net_fare ?? 0);
    }
}
