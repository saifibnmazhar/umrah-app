<?php

namespace App\Http\Controllers;

use App\Models\TicketAgent;
use App\Queries\TicketAgentReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketAgentReportController extends Controller
{
    public function index(): View
    {
        $agents = TicketAgent::orderBy('name')->get();

        return view('reports.ticket-agent', compact('agents'));
    }

    public function data(Request $request): JsonResponse
    {
        $agentId = $request->agent_id;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $result = (new TicketAgentReportQuery)->data($agentId, $dateFrom, $dateTo);

        return response()->json($result);
    }
}
