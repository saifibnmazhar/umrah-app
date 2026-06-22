<?php

namespace App\Http\Controllers;

use App\Models\VisaSubmission;
use App\Models\VisaAgent;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class VisaReportController extends Controller
{
    const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $visaAgents = VisaAgent::orderBy('name')->get(['id', 'name']);

        return view('reports.visa', compact('visaAgents'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = $this->buildQuery($request);
        $submissions = $query->paginate(self::PER_PAGE);
        $items = $this->mapReportData($submissions->items());

        $allQuery = $this->buildQuery($request);
        $allSubmissions = $allQuery->get();
        $allItems = $this->mapReportData($allSubmissions->all());
        $summary = $this->computeSummary($allItems);

        return response()->json([
            'data' => $items,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $submissions->currentPage(),
                'last_page'    => $submissions->lastPage(),
                'per_page'     => $submissions->perPage(),
                'total'        => $submissions->total(),
            ],
        ]);
    }

    protected function buildQuery(Request $request)
    {
        $query = VisaSubmission::query()
            ->with([
                'passenger.booking.customer',
                'passenger.booking',
                'visaAgent',
            ]);

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('passenger', function ($pq) use ($search) {
                    $pq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('mobile_no', 'like', "%{$search}%")
                       ->orWhere('passport_no', 'like', "%{$search}%");
                })->orWhereHas('passenger.booking', function ($bq) use ($search) {
                    $bq->where('invoice_id', 'like', "%{$search}%");
                })->orWhereHas('passenger.booking.customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })->orWhere('visa_number', 'like', "%{$search}%");
            });
        }

        if ($request->visa_submit_date_from) {
            $query->whereDate('created_at', '>=', $request->visa_submit_date_from);
        }
        if ($request->visa_submit_date_to) {
            $query->whereDate('created_at', '<=', $request->visa_submit_date_to);
        }

        if ($request->flight_date_from) {
            $query->whereHas('passenger', fn($q) => $q->whereDate('flight_date_from', '>=', $request->flight_date_from));
        }
        if ($request->flight_date_to) {
            $query->whereHas('passenger', fn($q) => $q->whereDate('flight_date_to', '<=', $request->flight_date_to));
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $query->orderBy('created_at', 'desc');

        return $query;
    }

    protected function mapReportData(array $submissions): array
    {
        $currencyRateService = app(CurrencyRateService::class);
        $result = [];
        foreach ($submissions as $submission) {
            $passenger = $submission->passenger;
            $booking = $passenger?->booking;
            $customer = $booking?->customer;

            $rate = $booking?->currencyRate?->rate
                ?? $currencyRateService->getRateForDate($booking?->created_at)?->rate
                ?? 0;

            $iqama = $customer?->iqama_no;
            $passport = $passenger?->passport_no;
            $result[] = [
                'id' => $submission->id,
                'invoice_no' => $booking?->invoice_id ?? '-',
                'customer_name' => $customer?->name ?? '-',
                'customer_iqama' => $iqama ? "IQAMA: {$iqama}" : 'N/A',
                'pax_name' => $passenger ? trim(($passenger->first_name ?? '') . ' ' . ($passenger->last_name ?? '')) : '-',
                'pax_passport' => $passport ? "Passport: {$passport}" : 'N/A',
                'mobile' => $passenger?->mobile_no ?? '-',
                'customer_mobile' => $customer?->mobile_no ?? '-',
                'visa_submit_date' => $submission->created_at?->format('d-M-Y'),
                'visa_status' => $submission->status?->value ?? 'pending',
                'flight_date' => $passenger?->flight_date_display ?? '-',
                'visa_number' => $submission->visa_number ?? '-',
                'visa_agent' => $submission->visaAgent?->name ?? '-',
                'agent_cost' => (float)($submission->final_cost ?? 0),
                'rate' => $rate,
            ];
        }
        return $result;
    }

    protected function computeSummary(array $items): array
    {
        $uniqueInvoices = collect($items)->pluck('invoice_no')->unique();
        $totalRecords = count($items);
        $totalAgentCost = collect($items)->sum('agent_cost');

        $statusCounts = collect($items)->groupBy('visa_status')->map->count();

        return [
            'total_records' => $totalRecords,
            'total_invoices' => $uniqueInvoices->count(),
            'total_agent_cost' => $totalAgentCost,
            'pending' => $statusCounts->get('pending', 0),
            'submitted' => $statusCounts->get('submitted', 0),
            'issued' => $statusCounts->get('issued', 0),
            'cancelled' => $statusCounts->get('cancelled', 0),
        ];
    }
}
