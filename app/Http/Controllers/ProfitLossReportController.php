<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Passenger;
use App\Services\ProfitCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitLossReportController extends Controller
{
    private const BOOKING_WITHS = [
        'customer',
        'invoice',
        'fingerprint',
        'fingerprintCharge',
        'package.ticketFare',
        'package.ticketFareInbound',
        'package.ticketFareOutbound',
        'passengers.visaSubmission.cancelledSubmissions',
        'passengers.visaSubmission.visaSellingPrice',
        'passengers.allIssuedTickets.ticketFare',
        'passengers.allIssuedTickets.reIssuedTickets',
        'passengers.allIssuedTickets.refundedTickets',
    ];

    private function bookingsQuery(Request $request)
    {
        $query = Booking::with(self::BOOKING_WITHS)
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($request->booking_date_from) {
            $query->whereDate('created_at', '>=', $request->booking_date_from);
        }
        if ($request->booking_date_to) {
            $query->whereDate('created_at', '<=', $request->booking_date_to);
        }

        return $query->get();
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->booking_date_from) {
            $query->whereDate('bookings.created_at', '>=', $request->booking_date_from);
        }
        if ($request->booking_date_to) {
            $query->whereDate('bookings.created_at', '<=', $request->booking_date_to);
        }
    }

    private function applyEffectiveDateFilter($query, Request $request): void
    {
        if ($request->effective_date_from || $request->effective_date_to) {
            $from = $request->effective_date_from ?? '1970-01-01';
            $to = $this->effectiveDateTo($request);
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('passengers.visa_profit_effective_at', [$from, $to])
                    ->orWhereBetween('passengers.ticket_profit_effective_at', [$from, $to])
                    ->orWhereBetween('passengers.service_charge_effective_at', [$from, $to]);
            });
        }
    }

    private function effectiveDateTo(Request $request): string
    {
        if (! $request->effective_date_to) {
            return now()->toDateTimeString();
        }

        return $request->effective_date_to.' 23:59:59';
    }

    private function calculateEffectiveDateProfit(Passenger $passenger, string $dateFrom, string $dateTo): float
    {
        $total = 0.0;

        if ($passenger->visa_profit_effective_at
            && $passenger->visa_profit_effective_at->toDateTimeString() >= $dateFrom
            && $passenger->visa_profit_effective_at->toDateTimeString() <= $dateTo) {
            $total += (float) $passenger->visa_profit;
        }

        if ($passenger->ticket_profit_effective_at
            && $passenger->ticket_profit_effective_at->toDateTimeString() >= $dateFrom
            && $passenger->ticket_profit_effective_at->toDateTimeString() <= $dateTo) {
            $total += (float) $passenger->ticket_profit;
        }

        if ($passenger->service_charge_effective_at
            && $passenger->service_charge_effective_at->toDateTimeString() >= $dateFrom
            && $passenger->service_charge_effective_at->toDateTimeString() <= $dateTo) {
            $total += (float) $passenger->service_charge;
        }

        return round($total, 6);
    }

    private function effectiveComponentValue(Passenger $passenger, string $profitColumn, string $effectiveColumn, string $dateFrom, string $dateTo): float
    {
        $effectiveAt = $passenger->{$effectiveColumn};
        if (! $effectiveAt
            || $effectiveAt->toDateTimeString() < $dateFrom
            || $effectiveAt->toDateTimeString() > $dateTo) {
            return 0.0;
        }

        return (float) ($passenger->{$profitColumn} ?? 0);
    }

    private function passengerHasEffectiveComponentInRange(Passenger $passenger, string $dateFrom, string $dateTo): bool
    {
        foreach (['visa_profit_effective_at', 'ticket_profit_effective_at', 'service_charge_effective_at'] as $column) {
            $value = $passenger->{$column};
            if ($value
                && $value->toDateTimeString() >= $dateFrom
                && $value->toDateTimeString() <= $dateTo) {
                return true;
            }
        }

        return false;
    }

    private function applyCustomerSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('bookings.invoice_id', 'like', "%{$search}%")
                ->orWhere('customers.name', 'like', "%{$search}%")
                ->orWhere('customers.passport_no', 'like', "%{$search}%")
                ->orWhere('customers.iqama_no', 'like', "%{$search}%");
        });
    }

    private function applyPassengerSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('bookings.invoice_id', 'like', "%{$search}%")
                ->orWhere('customers.name', 'like', "%{$search}%")
                ->orWhere('passengers.first_name', 'like', "%{$search}%")
                ->orWhere('passengers.last_name', 'like', "%{$search}%")
                ->orWhere('passengers.passport_no', 'like', "%{$search}%")
                ->orWhere('customers.passport_no', 'like', "%{$search}%")
                ->orWhere('customers.iqama_no', 'like', "%{$search}%");
        });
    }

    private function applyBranchFilter($query, Request $request)
    {
        if ($request->filled('branch_id')) {
            $query->where('bookings.booking_branch_id', $request->branch_id);
        }

        return $query;
    }

    public function getFilters()
    {
        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return response()->json(['branches' => $branches]);
    }

    public function summary(Request $request)
    {
        $search = trim((string) $request->search);
        $filter = $request->profit_loss_filter;

        $customer = Booking::query()
            ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
            ->leftJoin('fingerprints', 'fingerprints.booking_id', '=', 'bookings.id')
            ->leftJoin('invoices', 'invoices.booking_id', '=', 'bookings.id')
            ->leftJoin(
                DB::raw('(SELECT p.booking_id, SUM(p.profit) as ptotal FROM passengers p GROUP BY p.booking_id) AS psum'),
                'psum.booking_id',
                '=',
                'bookings.id'
            )
            ->where('bookings.is_cancelled', false)
            ->whereNotNull('bookings.invoice_id')
            ->when($search, fn ($q) => $this->applyCustomerSearch($q, $search))
            ->when($filter === 'profit', fn ($q) => $q->where('bookings.profit', '>=', 0))
            ->when($filter === 'loss', fn ($q) => $q->where('bookings.profit', '<', 0))
            ->selectRaw('
                COUNT(bookings.id) as count,
                COALESCE(SUM(invoices.total_amount), 0) as package_value,
                COALESCE(SUM(fingerprints.profit), 0) as fingerprint_profit,
                COALESCE(SUM(psum.ptotal), 0) as passenger_profit_total,
                COALESCE(SUM(bookings.discount_amount), 0) as discount,
                COALESCE(SUM(bookings.profit), 0) as total_profit
            ');
        $this->applyDateFilters($customer, $request);
        $this->applyBranchFilter($customer, $request);
        $customer = $customer->first();

        $passenger = Passenger::query()
            ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
            ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
            ->where('bookings.is_cancelled', false)
            ->whereNotNull('bookings.invoice_id')
            ->when($search, fn ($q) => $this->applyPassengerSearch($q, $search))
            ->when($filter === 'profit', fn ($q) => $q->where('passengers.profit', '>=', 0))
            ->when($filter === 'loss', fn ($q) => $q->where('passengers.profit', '<', 0));

        $isEffectiveMode = $request->filled('effective_date_from') || $request->filled('effective_date_to');

        if ($isEffectiveMode) {
            $this->applyEffectiveDateFilter($passenger, $request);
            $dateFrom = $request->effective_date_from ?? '1970-01-01';
            $dateTo = $this->effectiveDateTo($request);
            $passenger->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(passengers.package_value), 0) as package_value,
                COALESCE(SUM(CASE WHEN passengers.visa_profit_effective_at BETWEEN ? AND ? THEN passengers.visa_profit ELSE 0 END), 0) as total_visa_profit,
                COALESCE(SUM(CASE WHEN passengers.ticket_profit_effective_at BETWEEN ? AND ? THEN passengers.ticket_profit ELSE 0 END), 0) as total_ticket_profit,
                COALESCE(SUM(
                    CASE WHEN passengers.visa_profit_effective_at BETWEEN ? AND ? THEN passengers.visa_profit ELSE 0 END
                    + CASE WHEN passengers.ticket_profit_effective_at BETWEEN ? AND ? THEN passengers.ticket_profit ELSE 0 END
                    + CASE WHEN passengers.service_charge_effective_at BETWEEN ? AND ? THEN passengers.service_charge ELSE 0 END
                ), 0) as total_profit
            ', [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo]);
        } else {
            $this->applyDateFilters($passenger, $request);
            $passenger->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(passengers.package_value), 0) as package_value,
                COALESCE(SUM(passengers.visa_profit), 0) as total_visa_profit,
                COALESCE(SUM(passengers.ticket_profit), 0) as total_ticket_profit,
                COALESCE(SUM(passengers.profit), 0) as total_profit
            ');
        }

        $this->applyBranchFilter($passenger, $request);
        $passenger = $passenger->first();

        return response()->json([
            'customer' => [
                'count' => (int) $customer->count,
                'package_value' => (float) $customer->package_value,
                'fingerprint_profit' => (float) $customer->fingerprint_profit,
                'passenger_profit_total' => (float) $customer->passenger_profit_total,
                'discount' => (float) $customer->discount,
                'total_profit' => (float) $customer->total_profit,
            ],
            'passenger' => [
                'count' => (int) $passenger->count,
                'package_value' => (float) $passenger->package_value,
                'total_visa_profit' => (float) $passenger->total_visa_profit,
                'total_ticket_profit' => (float) $passenger->total_ticket_profit,
                'total_profit' => (float) $passenger->total_profit,
            ],
        ]);
    }

    private function mapCustomers($bookings, ProfitCalculationService $profitService): array
    {
        return $bookings->map(fn (Booking $booking) => [
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name ?? '',
            'customer_passport' => $booking->customer->passport_no ?? '',
            'customer_iqama' => $booking->customer->iqama_no ?? '',
            'mobile' => $booking->customer->mobile_no ?? '',
            'pax_qty' => $booking->pax_qty,
            'package_value' => (float) ($booking->invoice->total_amount ?? 0),
            'fingerprint_profit' => (float) ($booking->fingerprint?->profit ?? 0),
            'passenger_profit_total' => (float) $booking->passengers->sum('profit'),
            'discount' => (float) ($booking->discount_amount ?? 0),
            'total_profit' => (float) ($booking->profit ?? 0),
            'breakdown' => $profitService->getCustomerProfitBreakdown($booking),
        ])->values()->toArray();
    }

    private function mapPassengers($bookings, ProfitCalculationService $profitService): array
    {
        return $bookings->flatMap(fn (Booking $booking) => $booking->passengers->map(
            fn ($passenger) => $this->mapPassenger($passenger, $profitService)
        ))->values()->toArray();
    }

    private function mapPassenger($passenger, ProfitCalculationService $profitService): array
    {
        $booking = $passenger->booking;

        return [
            'id' => (int) $passenger->id,
            'invoice_id' => $booking->invoice_id,
            'customer_name' => $booking->customer->name ?? '',
            'customer_passport' => $booking->customer->passport_no ?? '',
            'customer_iqama' => $booking->customer->iqama_no ?? '',
            'mobile' => $passenger->mobile_no,
            'passenger_name' => trim($passenger->first_name.' '.$passenger->last_name),
            'passenger_passport' => $passenger->passport_no ?? '',
            'package_value' => (float) ($passenger->package_value ?? 0),
            'total_profit' => (float) ($passenger->profit ?? 0),
            'breakdown' => $profitService->getPassengerProfitBreakdownDetailed($passenger),
        ];
    }

    public function data(Request $request)
    {
        $tab = $request->get('tab', 'customer');
        $perPage = (int) $request->get('per_page', 25);
        $page = max(1, (int) $request->get('page', 1));
        $search = trim((string) $request->search);
        $filter = $request->profit_loss_filter;
        $profitService = app(ProfitCalculationService::class);

        if ($tab === 'passenger') {
            $isEffectiveMode = $request->filled('effective_date_from') || $request->filled('effective_date_to');

            $query = Passenger::query()
                ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
                ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
                ->where('bookings.is_cancelled', false)
                ->whereNotNull('bookings.invoice_id');

            if ($isEffectiveMode) {
                $this->applyEffectiveDateFilter($query, $request);
            } else {
                $this->applyDateFilters($query, $request);
            }

            if ($search) {
                $this->applyPassengerSearch($query, $search);
            }
            if ($filter === 'profit') {
                $query->where('passengers.profit', '>=', 0);
            }
            if ($filter === 'loss') {
                $query->where('passengers.profit', '<', 0);
            }
            $this->applyBranchFilter($query, $request);

            $paginator = $query->select('passengers.*')->paginate($perPage, ['*'], 'page', $page);

            $ids = collect($paginator->items())->pluck('id');

            $bookings = Booking::with(self::BOOKING_WITHS)
                ->whereHas('passengers', fn ($q) => $q->whereIn('id', $ids))
                ->get();

            $passengerMap = $bookings->flatMap->passengers->keyBy('id');

            $rows = $ids->map(function ($id) use ($passengerMap, $profitService, $isEffectiveMode, $request) {
                $passenger = $passengerMap->get($id);
                $row = $this->mapPassenger($passenger, $profitService);

                if ($isEffectiveMode) {
                    $from = $request->effective_date_from ?? '1970-01-01';
                    $to = $this->effectiveDateTo($request);

                    $row['total_profit'] = $this->calculateEffectiveDateProfit($passenger, $from, $to);
                    $row['visa_profit'] = $this->effectiveComponentValue(
                        $passenger, 'visa_profit', 'visa_profit_effective_at', $from, $to
                    );
                    $row['ticket_profit'] = $this->effectiveComponentValue(
                        $passenger, 'ticket_profit', 'ticket_profit_effective_at', $from, $to
                    );
                }

                return $row;
            })->values();

            return response()->json([
                'data' => $rows->toArray(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ]);
        }

        // customer tab
        $query = Booking::query()
            ->leftJoin('customers', 'customers.id', '=', 'bookings.customer_id')
            ->where('bookings.is_cancelled', false)
            ->whereNotNull('bookings.invoice_id');
        $this->applyDateFilters($query, $request);

        if ($search) {
            $this->applyCustomerSearch($query, $search);
        }
        if ($filter === 'profit') {
            $query->where('bookings.profit', '>=', 0);
        }
        if ($filter === 'loss') {
            $query->where('bookings.profit', '<', 0);
        }
        $this->applyBranchFilter($query, $request);

        $bookingIds = $query->pluck('bookings.id');
        $total = $bookingIds->count();

        $bookings = Booking::with(self::BOOKING_WITHS)->whereIn('id', $bookingIds)->get();
        $rows = $this->mapCustomers($bookings, $profitService);

        $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $rows,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
        ]);
    }

    public function print(Request $request)
    {
        $type = $request->get('type', 'customer');
        $currency = $request->get('currency', 'SAR');
        $dateFrom = $request->booking_date_from;
        $dateTo = $request->booking_date_to;
        $search = trim((string) $request->search);
        $profitLossFilter = $request->profit_loss_filter;

        $query = Booking::with(self::BOOKING_WITHS)
            ->where('is_cancelled', false)
            ->whereHas('invoice');

        if ($request->booking_date_from) {
            $query->whereDate('created_at', '>=', $request->booking_date_from);
        }
        if ($request->booking_date_to) {
            $query->whereDate('created_at', '<=', $request->booking_date_to);
        }
        $this->applyBranchFilter($query, $request);
        $bookings = $query->get();

        $profitService = app(ProfitCalculationService::class);
        $customers = collect($this->mapCustomers($bookings, $profitService));
        $passengers = collect($this->mapPassengers($bookings, $profitService));

        $isEffectiveMode = $request->filled('effective_date_from') || $request->filled('effective_date_to');
        if ($type === 'passenger' && $isEffectiveMode) {
            $dateFrom = $request->effective_date_from ?? '1970-01-01';
            $dateTo = $this->effectiveDateTo($request);

            $passengerModels = $bookings->flatMap->passengers;
            $passengerById = $passengerModels->keyBy('id');

            $passengers = $passengers->map(function ($row) use ($passengerById, $dateFrom, $dateTo) {
                $passenger = $passengerById->get($row['id'] ?? null);
                if (! $passenger || ! $this->passengerHasEffectiveComponentInRange($passenger, $dateFrom, $dateTo)) {
                    return null;
                }
                $row['total_profit'] = $this->calculateEffectiveDateProfit($passenger, $dateFrom, $dateTo);
                $row['visa_profit'] = $this->effectiveComponentValue(
                    $passenger, 'visa_profit', 'visa_profit_effective_at', $dateFrom, $dateTo
                );
                $row['ticket_profit'] = $this->effectiveComponentValue(
                    $passenger, 'ticket_profit', 'ticket_profit_effective_at', $dateFrom, $dateTo
                );

                return $row;
            })->filter(fn ($row) => $row !== null)->values();
        }

        if ($search) {
            $q = strtolower($search);
            if ($type === 'passenger') {
                $passengers = $passengers->filter(fn ($r) => str_contains(strtolower($r['invoice_id'] ?? ''), $q)
                    || str_contains(strtolower($r['customer_name'] ?? ''), $q)
                    || str_contains(strtolower($r['passenger_name'] ?? ''), $q)
                    || str_contains(strtolower($r['passenger_passport'] ?? ''), $q)
                )->values();
            } else {
                $customers = $customers->filter(fn ($r) => str_contains(strtolower($r['invoice_id'] ?? ''), $q)
                    || str_contains(strtolower($r['customer_name'] ?? ''), $q)
                    || str_contains(strtolower($r['customer_passport'] ?? ''), $q)
                    || str_contains(strtolower($r['customer_iqama'] ?? ''), $q)
                )->values();
            }
        }

        if ($profitLossFilter === 'profit') {
            $passengers = $passengers->filter(fn ($r) => (float) $r['total_profit'] >= 0)->values();
            $customers = $customers->filter(fn ($r) => (float) $r['total_profit'] >= 0)->values();
        }
        if ($profitLossFilter === 'loss') {
            $passengers = $passengers->filter(fn ($r) => (float) $r['total_profit'] < 0)->values();
            $customers = $customers->filter(fn ($r) => (float) $r['total_profit'] < 0)->values();
        }

        $summary = $this->summary($request)->getData(true);

        $branchName = $request->filled('branch_id')
            ? Branch::find($request->branch_id)?->name
            : null;

        return view('reports.profit-loss-print', compact(
            'type', 'currency', 'customers', 'passengers', 'dateFrom', 'dateTo',
            'search', 'profitLossFilter', 'summary', 'branchName'
        ));
    }
}
