<?php

namespace App\Queries;

use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Route;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;

class BookingPassengerQuery
{
    protected $query;

    public function __construct(Request $request)
    {
        $this->query = Passenger::query()->orderBy('created_at', 'desc');
        $this->applyFilters($request);
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getBaseQueryForAggregates()
    {
        return clone $this->query;
    }

    protected function applyFilters(Request $request): void
    {
        $this->applyBranchScope()
            ->applyBookingStatus($request)
            ->applyFingerprintStatus($request)
            ->applyVisaStatus($request)
            ->applyTicketStatus($request)
            ->applyVisaAgent($request)
            ->applyBookingBranch($request)
            ->applyBookingDate($request)
            ->applyFlightDate($request)
            ->applyActualFlight($request)
            ->applyReturnDate($request)
            ->applyPassengerStatus($request)
            ->applyStatusChange($request)
            ->applyRouteDisplay($request)
            ->applyPackage($request)
            ->applyTicketAgent($request)
            ->applySearch($request)
            ->applyPaymentWise($request);
    }

    protected function applyBranchScope(): static
    {
        $branchId = auth()->user()?->branch_id;
        if ($branchId) {
            $this->query->whereHas('booking', fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->where('booking_branch_id', $branchId)
                    ->orWhere('fingerprint_branch_id', $branchId);
            }));
        }

        return $this;
    }

    protected function applyBookingStatus(Request $request): static
    {
        $status = $request->get('booking_status') ?? 'active';
        if ($status && $status !== 'all') {
            if ($status === 'active') {
                $this->query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', false));
            } elseif ($status === 'cancellation_processing') {
                $this->query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                    ->whereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancellation processing')));
            } elseif ($status === 'cancelled') {
                $this->query->whereHas('booking', fn ($bq) => $bq->where('is_cancelled', true)
                    ->where(fn ($bw) => $bw->whereDoesntHave('cancelledBooking')
                        ->orWhereHas('cancelledBooking', fn ($cq) => $cq->where('status', 'cancelled'))));
            }
        }

        return $this;
    }

    protected function applyFingerprintStatus(Request $request): static
    {
        if ($request->filled('fingerprint_status')) {
            $this->query->whereHas('fingerprintDetail', fn ($q) => $q->where('status', $request->input('fingerprint_status')));
        }

        return $this;
    }

    protected function applyVisaStatus(Request $request): static
    {
        if ($request->filled('visa_status')) {
            $this->query->whereHas('visaSubmission', fn ($q) => $q->where('status', $request->input('visa_status')));
        }

        return $this;
    }

    protected function applyTicketStatus(Request $request): static
    {
        if (! $request->filled('ticket_status')) {
            return $this;
        }
        $val = $request->input('ticket_status');
        $q = $this->query;

        if (in_array($val, ['partial-re-issued', 'partial-refunded', 're-issued', 'refunded'])) {
            $targetStatus = str_contains($val, 're-issued') ? 're-issued' : 'refunded';
            $isPartial = str_starts_with($val, 'partial-');
            $q->where(function ($wq) use ($targetStatus, $isPartial) {
                if ($isPartial) {
                    $wq->where(function ($wq2) use ($targetStatus) {
                        $wq2->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $targetStatus)
                            ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                            ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                ->where('status', '!=', $targetStatus));
                    })->orWhere(function ($wq2) use ($targetStatus) {
                        $wq2->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                            ->where('status', $targetStatus))
                            ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', '!=', $targetStatus)
                                ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')));
                    });
                } else {
                    $wq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $targetStatus)
                        ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                        ->where(function ($wq2) use ($targetStatus) {
                            $wq2->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                ->where('status', $targetStatus))
                                ->orWhereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound'));
                        });
                }
            });
        } elseif (str_starts_with($val, 'issued-') || str_starts_with($val, 'awaiting-group')) {
            $isIssued = str_starts_with($val, 'issued-');
            $status = $isIssued ? 'issued' : 'awaiting-group';
            $routeFilter = $isIssued ? substr($val, 7) : substr($val, 15);
            if ($routeFilter === 'inbound' || $routeFilter === 'outbound' || $routeFilter === 'both') {
                $q->where(function ($wq) use ($status, $routeFilter, $isIssued) {
                    $wq->where(function ($nq) use ($status, $routeFilter) {
                        $nq->whereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound'))
                            ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                                ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular'))
                                ->whereHas('ticketFare.route', fn ($rq) => match ($routeFilter) {
                                    'inbound' => $rq->where('route_type', 'oneway_inbound'),
                                    'outbound' => $rq->where('route_type', 'oneway_outbound'),
                                    'both' => $rq->whereIn('route_type', ['round', 'multi_city']),
                                }));
                    });
                    if ($routeFilter === 'inbound') {
                        $wq->orWhere(function ($oq) use ($status, $isIssued) {
                            $poStatus = $isIssued ? 'pending' : 'awaiting-group';
                            $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                                ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')));
                            if ($isIssued) {
                                $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', $poStatus));
                            } else {
                                $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', '!=', $poStatus));
                            }
                        });
                        $wq->orWhere(function ($oq) use ($status) {
                            $oq->whereHas('booking.package', fn ($pq) => $pq->where('is_double_ticket', true))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                                    ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                                ->whereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', $status));
                        });
                    } elseif ($routeFilter === 'outbound') {
                        $wq->orWhere(function ($oq) use ($status, $isIssued) {
                            if ($isIssued) {
                                $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', 'pending')
                                    ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                                    ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                        ->where('status', 'issued'));
                            } else {
                                $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', '!=', $status)
                                    ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                                    ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                        ->where('status', $status));
                            }
                        });
                        $wq->orWhere(function ($oq) use ($status) {
                            $oq->whereHas('booking.package', fn ($pq) => $pq->where('is_double_ticket', true))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', $status))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', '!=', $status)
                                    ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')));
                        });
                    } elseif ($routeFilter === 'both') {
                        $wq->orWhere(function ($oq) use ($status) {
                            $oq->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                                ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', $status));
                        });
                        $wq->orWhere(function ($oq) use ($status) {
                            $oq->whereHas('booking.package', fn ($pq) => $pq->where('is_double_ticket', true))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound')
                                    ->where('status', $status))
                                ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                                    ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')));
                        });
                    }
                });
            } elseif ($routeFilter === '') {
                $q->whereDoesntHave('allIssuedTickets', fn ($iq) => $iq->where('issue_type', 'pending_outbound'))
                    ->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $status)
                        ->where(fn ($iq) => $iq->whereNull('issue_type')->orWhere('issue_type', 'regular')));
            }
        } else {
            $q->whereHas('allIssuedTickets', fn ($iq) => $iq->where('status', $val)
                ->where(fn ($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular')));
        }

        return $this;
    }

    protected function canFilterByAgent(): bool
    {
        return auth()->user()?->roles->pluck('name')
            ->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Ticket Admin'])->isNotEmpty() ?? false;
    }

    protected function applyVisaAgent(Request $request): static
    {
        if ($request->filled('visa_agent_id') && $this->canFilterByAgent()) {
            $this->query->whereHas('visaSubmission.visaAgent', fn ($q) => $q->where('id', $request->input('visa_agent_id')));
        }

        return $this;
    }

    protected function applyBookingBranch(Request $request): static
    {
        if ($request->filled('booking_branch_id')) {
            $this->query->whereHas('booking', fn ($q) => $q->where('booking_branch_id', $request->input('booking_branch_id')));
        }

        return $this;
    }

    protected function applyBookingDate(Request $request): static
    {
        if ($request->filled('booking_date_from')) {
            $this->query->whereHas('booking', fn ($q) => $q->whereDate('created_at', '>=', $request->input('booking_date_from')));
        }
        if ($request->filled('booking_date_to')) {
            $this->query->whereHas('booking', fn ($q) => $q->whereDate('created_at', '<=', $request->input('booking_date_to')));
        }

        return $this;
    }

    protected function applyFlightDate(Request $request): static
    {
        if ($request->filled('flight_date_from')) {
            $this->query->whereDate('flight_date_from', '>=', $request->input('flight_date_from'));
        }
        if ($request->filled('flight_date_to')) {
            $this->query->whereDate('flight_date_from', '<=', $request->input('flight_date_to'));
        }

        return $this;
    }

    protected function applyActualFlight(Request $request): static
    {
        if ($request->filled('actual_flight_from')) {
            $this->query->whereHas('issuedTickets', fn ($q) => $q->whereIn('status', ['issued', 're-issued'])->whereDate('inbound_date', '>=', $request->input('actual_flight_from')));
        }
        if ($request->filled('actual_flight_to')) {
            $this->query->whereHas('issuedTickets', fn ($q) => $q->whereIn('status', ['issued', 're-issued'])->whereDate('inbound_date', '<=', $request->input('actual_flight_to')));
        }

        return $this;
    }

    protected function applyReturnDate(Request $request): static
    {
        if ($request->filled('return_date_from')) {
            $this->query->whereHas('issuedTickets', fn ($q) => $q->whereIn('status', ['issued', 're-issued'])->whereDate('outbound_date', '>=', $request->input('return_date_from')));
        }
        if ($request->filled('return_date_to')) {
            $this->query->whereHas('issuedTickets', fn ($q) => $q->whereIn('status', ['issued', 're-issued'])->whereDate('outbound_date', '<=', $request->input('return_date_to')));
        }

        return $this;
    }

    protected function applyPassengerStatus(Request $request): static
    {
        if ($request->filled('passenger_status')) {
            $this->query->where('passenger_status_id', $request->input('passenger_status'));
        }

        return $this;
    }

    protected function applyStatusChange(Request $request): static
    {
        if (! $request->filled('status_change_action')) {
            return $this;
        }
        $action = $request->input('status_change_action');
        $dateFrom = $request->input('status_change_from');
        $dateTo = $request->input('status_change_to');

        if (in_array($action, ['visa_submitted', 'visa_issued', 'ticket_issued'])) {
            $excludeIds = PassengerStatus::whereIn('name', ['Cancel', 'Delivered', 'Hold'])->pluck('id')->toArray();
            $this->query->where(function ($sub) use ($excludeIds) {
                $sub->whereNull('passenger_status_id')->orWhereNotIn('passenger_status_id', $excludeIds);
            });
            match ($action) {
                'visa_submitted' => $this->query->whereHas('visaSubmission', fn ($vs) => $vs->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where(fn ($log) => $log->where('action', 'submitted')->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'"));
                    if ($dateFrom) {
                        $log->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $log->whereDate('created_at', '<=', $dateTo);
                    }
                })),
                'visa_issued' => $this->query->whereHas('visaSubmission', fn ($vs) => $vs->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where(fn ($log) => $log->where('action', 'issued')->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'"));
                    if ($dateFrom) {
                        $log->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $log->whereDate('created_at', '<=', $dateTo);
                    }
                })),
                'ticket_issued' => $this->query->whereHas('issuedTickets', fn ($it) => $it->whereHas('logs', function ($log) use ($dateFrom, $dateTo) {
                    $log->where(fn ($log) => $log->where('action', 'issued')->orWhere('new_data->status', 'issued'));
                    if ($dateFrom) {
                        $log->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $log->whereDate('created_at', '<=', $dateTo);
                    }
                })),
                default => null,
            };
        } else {
            $this->query->where('passenger_status_id', $action);
            if ($dateFrom || $dateTo) {
                $this->query->where(function ($query) use ($action, $dateFrom, $dateTo) {
                    $query->where(fn ($q) => $q->whereHas('updateLogs', function ($logQ) use ($action, $dateFrom, $dateTo) {
                        $logQ->where('action', 'updated')->where('new_values->passenger_status_id', $action);
                        if ($dateFrom) {
                            $logQ->whereDate('created_at', '>=', $dateFrom);
                        }
                        if ($dateTo) {
                            $logQ->whereDate('created_at', '<=', $dateTo);
                        }
                    }))->orWhere(fn ($q) => $q->whereDoesntHave('updateLogs', fn ($logQ) => $logQ->where('action', 'updated')->where('new_values->passenger_status_id', $action))
                        ->when($dateFrom, fn ($q) => $q->whereDate('updated_at', '>=', $dateFrom))
                        ->when($dateTo, fn ($q) => $q->whereDate('updated_at', '<=', $dateTo)));
                });
            }
        }

        return $this;
    }

    protected function applyRouteDisplay(Request $request): static
    {
        $selected = $request->input('route_display');
        if (! $selected && $request->filled('route_id')) {
            $route = Route::find($request->input('route_id'));
            $selected = $route ? $this->routeDisplay($route) : null;
        }
        if (! $selected) {
            return $this;
        }
        $routeIds = Route::with(['fromCity', 'toCity', 'returnCity', 'multiSegments.fromCity', 'multiSegments.toCity'])->get()
            ->filter(fn ($r) => $this->routeDisplay($r) === $selected)
            ->pluck('id')->toArray();
        if (! empty($routeIds)) {
            $this->query->where(fn ($q) => $q->whereHas('ticketFare', fn ($q) => $q->whereIn('route_id', $routeIds))
                ->orWhereHas('ticketFareInbound', fn ($q) => $q->whereIn('route_id', $routeIds))
                ->orWhereHas('ticketFareOutbound', fn ($q) => $q->whereIn('route_id', $routeIds)));
        }

        return $this;
    }

    protected function routeDisplay($r): string
    {
        return match ($r->route_type?->value) {
            'multi_city' => $r->multiSegments->map(fn ($s) => ($s->fromCity?->code ?? '?').'-'.($s->toCity?->code ?? '?'))->implode(', '),
            'round' => ($r->fromCity?->code ?? '?').'-'.($r->toCity?->code ?? '?').'-'.($r->returnCity?->code ?? '?'),
            default => ($r->fromCity?->code ?? '?').'-'.($r->toCity?->code ?? '?'),
        };
    }

    protected function applyPackage(Request $request): static
    {
        if ($request->filled('package_id')) {
            $this->query->whereHas('booking', fn ($q) => $q->where('package_id', $request->input('package_id')));
        }

        return $this;
    }

    protected function applyTicketAgent(Request $request): static
    {
        if ($request->filled('ticket_agent_id') && $this->canFilterByAgent()) {
            $this->query->whereHas('latestIssuedTicket.ticketAgent', fn ($q) => $q->where('id', $request->input('ticket_agent_id')));
        }

        return $this;
    }

    protected function applySearch(Request $request): static
    {
        if (! $request->filled('search')) {
            return $this;
        }
        $search = $request->input('search');
        $tab = $request->get('tab', 'passenger');
        if ($tab === 'booking') {
            $this->query->where(fn ($query) => $query->whereHas('booking', fn ($q) => $q->where('invoice_id', 'like', "%{$search}%"))
                ->orWhereHas('booking.customer', fn ($q) => $q->where('mobile_no', 'like', "%{$search}%"))
                ->orWhere('passport_no', 'like', "%{$search}%"));
        } else {
            $this->query->where(fn ($query) => $query->where('mobile_no', 'like', "%{$search}%")
                ->orWhere('passport_no', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereHas('booking', fn ($q) => $q->where('invoice_id', 'like', "%{$search}%"))
                ->orWhereHas('issuedTickets', fn ($q) => $q->where('ticket_number', 'like', "%{$search}%")->orWhere('pnr', 'like', "%{$search}%")));
        }

        return $this;
    }

    protected function applyPaymentWise(Request $request): static
    {
        if (! $request->filled('payment_wise')) {
            return $this;
        }
        $paymentWise = $request->input('payment_wise');
        $rate = (float) (app(CurrencyRateService::class)->getCurrentRateValue() ?? 0);
        $this->query->whereHas('booking.invoice', function ($iq) use ($paymentWise, $rate) {
            if ($paymentWise === 'clear') {
                $iq->where('balance', '<=', 0);
            } elseif ($paymentWise === 'due') {
                $iq->where('balance', '>', 0);
            } elseif ($paymentWise === 'due_below_1000') {
                $iq->where('balance', '>', 0);
                if ($rate > 0) {
                    $iq->whereRaw('balance * ? < 1000', [$rate]);
                } else {
                    $iq->where('balance', '<', 1000);
                }
            } elseif ($paymentWise === 'due_above_1000') {
                if ($rate > 0) {
                    $iq->whereRaw('balance * ? >= 1000', [$rate]);
                } else {
                    $iq->where('balance', '>=', 1000);
                }
            }
        });

        return $this;
    }
}
