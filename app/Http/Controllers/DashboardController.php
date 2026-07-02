<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\IssuedTicket;
use App\Models\Package;
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

        $inboundTicket = IssuedTicket::whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('inbound_date')
            ->count();

        $outboundTicket = IssuedTicket::whereIn('status', [TicketStatus::ISSUED, TicketStatus::RE_ISSUED])
            ->whereNotNull('outbound_date')
            ->count();

        $pendingTicket = IssuedTicket::where('status', TicketStatus::PENDING)
            ->count();

        return view('dashboard.index', compact('packages', 'inboundTicket', 'outboundTicket', 'pendingTicket'));
    }
}