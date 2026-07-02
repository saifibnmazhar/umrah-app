<?php

namespace App\Http\Controllers;

use App\Enums\FingerprintStatus;
use App\Enums\VisaStatus;
use App\Models\FingerprintDetail;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

        $packages = Package::where('is_active', true)
            ->with(['ticketFare.route.fromCity', 'ticketFare.route.toCity', 'ticketFare.route.returnCity', 'ticketFare.airline', 'ticketFare.airlineClass'])
            ->orderBy('id', 'desc')
            ->get();

        $visaSubmitted = Passenger::whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::SUBMITTED))->count();
        $visaIssued = Passenger::whereHas('visaSubmission', fn($q) => $q->where('status', VisaStatus::ISSUED))->count();
        $visaPending = Passenger::whereDoesntHave('visaSubmission', fn($q) => $q->whereIn('status', [VisaStatus::SUBMITTED, VisaStatus::ISSUED]))->count();

        $fingerprintApproved = FingerprintDetail::where('status', FingerprintStatus::APPROVED)->count();
        $fingerprintDone = FingerprintDetail::where('status', FingerprintStatus::DONE)->count();
        $fingerprintProcessing = FingerprintDetail::whereNotIn('status', [FingerprintStatus::APPROVED, FingerprintStatus::DONE])->count();

        $invoiceCount = Invoice::count();
        $invoiceTotalAmount = Invoice::sum('total_amount');

        return view('dashboard.index', compact('packages', 'visaSubmitted', 'visaIssued', 'visaPending', 'fingerprintApproved', 'fingerprintDone', 'fingerprintProcessing', 'invoiceCount', 'invoiceTotalAmount'));
    }
}