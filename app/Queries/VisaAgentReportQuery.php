<?php

namespace App\Queries;

use App\Models\CancelledSubmission;
use App\Models\Payment;
use App\Models\VisaAgent;
use App\Models\VisaSubmission;
use App\Support\DateFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisaAgentReportQuery
{
    /**
     * Build a summary row for a single visa agent.
     */
    public function buildAgentRow(VisaAgent $agent, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $agentId = $agent->id;

        $totalSubmitted = $this->totalSubmittedQuery($agentId, $dateFrom, $dateTo)->count();

        $payable = $this->issuedPayable($agentId, $dateFrom, $dateTo);
        $totalIssued = $this->issuedQuery($agentId, $dateFrom, $dateTo)->count();

        $price = $this->priceStats($agentId, $dateFrom, $dateTo);

        $cancellationFee = $this->cancelledQuery($agentId, $dateFrom, $dateTo)
            ->sum('cancellation_fee');

        $paid = $this->paidQuery($agentId, $dateFrom, $dateTo)
            ->sum('amount');

        $payableVal = (float) $payable;
        $paidVal = (float) $paid;
        $feeVal = (float) $cancellationFee;
        $balance = $paidVal - $payableVal - $feeVal;

        return [
            'id' => $agent->id,
            'name' => $agent->name,
            'totalSubmitted' => $totalSubmitted,
            'totalIssued' => $totalIssued,
            'price' => [
                'max' => $price->max,
                'min' => $price->min,
                'avg' => $price->avg,
            ],
            'payable' => $payableVal,
            'paid' => $paidVal,
            'balance' => $balance,
            'cancellationFee' => $feeVal,
        ];
    }

    /**
     * Build combined transaction rows (submission, issuance, cancellation, payment).
     */
    public function buildCombinedRows(VisaAgent $visaAgent, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $agentId = $visaAgent->id;
        $rows = collect();

        $rows = $rows->merge($this->submissionRows($agentId));
        $rows = $rows->merge($this->issuedRows($agentId));
        $rows = $rows->merge($this->cancelledRows($agentId));
        $rows = $rows->merge($this->paymentRows($agentId));

        $rows = $rows->sortBy('sort_date')->values();

        if ($dateFrom || $dateTo) {
            $rows = $rows->filter(function ($item) use ($dateFrom, $dateTo) {
                $itemDate = Carbon::parse($item['date']);
                if ($dateFrom && $itemDate->lt(Carbon::parse($dateFrom))) {
                    return false;
                }
                if ($dateTo && $itemDate->gt(Carbon::parse($dateTo))) {
                    return false;
                }

                return true;
            })->values();
        }

        $runningPayable = 0;
        $runningPaid = 0;
        $runningFee = 0;
        $rows = $rows->map(function ($item) use (&$runningPayable, &$runningPaid, &$runningFee) {
            $runningPayable += $item['payable'];
            $runningPaid += $item['paid'];
            $runningFee += $item['cancellation_fee'];
            $item['balance'] = $runningPaid - $runningPayable - $runningFee;

            return $item;
        });

        return $rows->map(fn ($item) => collect($item)->forget('sort_date')->all());
    }

    /** -----------------------------------------------------------------
     *  Submission rows (status = submitted with issued status log)
     * ----------------------------------------------------------------- */
    protected function submissionRows(int $agentId): Collection
    {
        $rows = collect();

        $submittedSubmissions = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'submitted')
            ->with([
                'passenger.booking',
                'logs' => fn ($q) => $q
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'")
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->get();

        foreach ($submittedSubmissions as $submission) {
            $submissionLog = $submission->logs->first();
            $newValues = $submissionLog ? $submissionLog->new_values : [];
            $estimatedCost = (float) ($newValues['net_visa_cost'] ?? $submission->net_visa_cost ?? 0);
            $rowDate = $submissionLog ? $submissionLog->created_at : $submission->created_at;

            $rows->push([
                'date' => DateFormatter::short($rowDate),
                'sort_date' => DateFormatter::iso($rowDate),
                'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                'passport_no' => $submission->passenger->passport_no ?? '-',
                'status' => 'Submitted',
                'estimated_cost' => $estimatedCost,
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        return $rows;
    }

    /** -----------------------------------------------------------------
     *  Issued rows
     * ----------------------------------------------------------------- */
    protected function issuedRows(int $agentId): Collection
    {
        $rows = collect();

        $issuedSubmissions = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued')
            ->with([
                'passenger.booking',
                'logs' => fn ($q) => $q
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'")
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->get();

        foreach ($issuedSubmissions as $submission) {
            $issueLog = $submission->logs->first();
            $rowDate = $issueLog ? $issueLog->created_at : $submission->updated_at;

            $rows->push([
                'date' => DateFormatter::short($rowDate),
                'sort_date' => DateFormatter::iso($rowDate),
                'invoice_id' => $submission->passenger->booking->invoice_id ?? '-',
                'passenger_name' => trim(($submission->passenger->first_name ?? '').' '.($submission->passenger->last_name ?? '')),
                'passport_no' => $submission->passenger->passport_no ?? '-',
                'status' => 'Issued',
                'estimated_cost' => 0,
                'payable' => (float) ($submission->net_visa_cost ?? 0) + (float) ($submission->additional_cost ?? 0),
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        return $rows;
    }

    /** -----------------------------------------------------------------
     *  Cancelled rows
     * ----------------------------------------------------------------- */
    protected function cancelledRows(int $agentId): Collection
    {
        $rows = collect();

        $cancelledSubmissions = CancelledSubmission::where('visa_agent_id', $agentId)
            ->with('visaSubmission.passenger.booking')
            ->get();

        foreach ($cancelledSubmissions as $cs) {
            $invoiceId = '-';
            $passengerName = '-';
            $passportNo = '-';

            if ($cs->visaSubmission && $cs->visaSubmission->passenger) {
                $passenger = $cs->visaSubmission->passenger;
                $invoiceId = $passenger->booking->invoice_id ?? '-';
                $passengerName = trim(($passenger->first_name ?? '').' '.($passenger->last_name ?? ''));
                $passportNo = $passenger->passport_no ?? '-';
            }

            $rows->push([
                'date' => DateFormatter::short($cs->created_at),
                'sort_date' => DateFormatter::iso($cs->created_at),
                'invoice_id' => $invoiceId,
                'passenger_name' => $passengerName,
                'passport_no' => $passportNo,
                'status' => 'Cancelled',
                'estimated_cost' => 0,
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellation_fee' => (float) ($cs->cancellation_fee ?? 0),
            ]);
        }

        return $rows;
    }

    /** -----------------------------------------------------------------
     *  Payment rows
     * ----------------------------------------------------------------- */
    protected function paymentRows(int $agentId): Collection
    {
        $rows = collect();

        $payments = Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
            ->get();

        foreach ($payments as $payment) {
            $rows->push([
                'date' => DateFormatter::short($payment->payment_date),
                'sort_date' => DateFormatter::iso($payment->payment_date),
                'invoice_id' => null,
                'passenger_name' => null,
                'passport_no' => null,
                'status' => 'Payment',
                'estimated_cost' => 0,
                'payable' => 0,
                'paid' => (float) ($payment->amount ?? 0),
                'balance' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        return $rows;
    }

    /** -----------------------------------------------------------------
     *  Query builder helpers for buildAgentRow
     * ----------------------------------------------------------------- */
    protected function totalSubmittedQuery(int $agentId, ?string $dateFrom, ?string $dateTo)
    {
        return VisaSubmission::where('visa_agent_id', $agentId)
            ->whereHas('logs', function ($q) use ($dateFrom, $dateTo) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'submitted'");
                if ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                }
            });
    }

    protected function issuedQuery(int $agentId, ?string $dateFrom, ?string $dateTo)
    {
        $query = VisaSubmission::where('visa_agent_id', $agentId)
            ->where('status', 'issued');

        if ($dateFrom || $dateTo) {
            $query->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereHas('logs', function ($logQ) use ($dateFrom, $dateTo) {
                    $logQ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'");
                    if ($dateFrom) {
                        $logQ->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $logQ->whereDate('created_at', '<=', $dateTo);
                    }
                })->orWhere(function ($subQ) use ($dateFrom, $dateTo) {
                    $subQ->whereDoesntHave('logs', fn ($lh) => $lh->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.status')) = 'issued'"));
                    if ($dateFrom) {
                        $subQ->whereDate('visa_submissions.updated_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $subQ->whereDate('visa_submissions.updated_at', '<=', $dateTo);
                    }
                });
            });
        }

        return $query;
    }

    protected function issuedPayable(int $agentId, ?string $dateFrom, ?string $dateTo): mixed
    {
        return (clone $this->issuedQuery($agentId, $dateFrom, $dateTo))
            ->sum(DB::raw('COALESCE(net_visa_cost, 0) + COALESCE(additional_cost, 0)'));
    }

    protected function priceStats(int $agentId, ?string $dateFrom, ?string $dateTo): object
    {
        $price = (object) ['max' => 0, 'min' => 0, 'avg' => 0];

        $priceStats = $this->issuedQuery($agentId, $dateFrom, $dateTo)
            ->whereNotNull('visa_selling_price_id')
            ->join('visa_selling_prices', 'visa_submissions.visa_selling_price_id', '=', 'visa_selling_prices.id')
            ->selectRaw('MAX(visa_selling_prices.selling_price) as max_price')
            ->selectRaw('MIN(visa_selling_prices.selling_price) as min_price')
            ->selectRaw('AVG(visa_selling_prices.selling_price) as avg_price')
            ->first();

        if ($priceStats && $priceStats->max_price) {
            $price->max = (float) $priceStats->max_price;
            $price->min = (float) ($priceStats->min_price ?? 0);
            $price->avg = (float) ($priceStats->avg_price ?? 0);
        }

        return $price;
    }

    protected function cancelledQuery(int $agentId, ?string $dateFrom, ?string $dateTo)
    {
        return CancelledSubmission::where('visa_agent_id', $agentId)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo));
    }

    protected function paidQuery(int $agentId, ?string $dateFrom, ?string $dateTo)
    {
        return Payment::where('visa_agent_id', $agentId)
            ->whereHas('voucher.transactionType', fn ($q) => $q->where('name', 'Visa Agent Payment'))
            ->when($dateFrom, fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('payment_date', '<=', $dateTo));
    }

    /**
     * Build transaction log rows for an agent (used by the logs() endpoint).
     */
    public function transactions(VisaAgent $visaAgent): Collection
    {
        $agentId = $visaAgent->id;
        $transactions = collect();

        foreach ($this->issuedRows($agentId) as $row) {
            $transactions->push([
                'date' => $row['date'],
                'status' => 'issued',
                'payable' => $row['payable'],
                'paid' => 0,
                'balance' => 0,
                'cancellationFee' => 0,
            ]);
        }

        foreach ($this->cancelledRows($agentId) as $row) {
            $transactions->push([
                'date' => $row['date'],
                'status' => 'cancelled',
                'payable' => 0,
                'paid' => 0,
                'balance' => 0,
                'cancellationFee' => $row['cancellation_fee'],
            ]);
        }

        foreach ($this->paymentRows($agentId) as $row) {
            $transactions->push([
                'date' => $row['date'],
                'status' => 'payment',
                'payable' => 0,
                'paid' => $row['paid'],
                'balance' => 0,
                'cancellationFee' => 0,
            ]);
        }

        $transactions = $transactions->sortBy('date')->values();

        $transactions = $transactions->reject(fn ($item) => $item['payable'] == 0 && $item['paid'] == 0 && $item['cancellationFee'] == 0
        )->values();

        $runningPayable = 0;
        $runningPaid = 0;
        $runningFee = 0;

        return $transactions->map(function ($item) use (&$runningPayable, &$runningPaid, &$runningFee) {
            $runningPayable += $item['payable'];
            $runningPaid += $item['paid'];
            $runningFee += $item['cancellationFee'];
            $item['balance'] = $runningPaid - $runningPayable - $runningFee;

            return $item;
        });
    }
}
