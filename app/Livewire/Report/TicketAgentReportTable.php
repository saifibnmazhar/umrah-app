<?php

namespace App\Livewire\Report;

use App\Http\Controllers\TicketAgentReportController;
use App\Models\TicketAgent;
use Illuminate\Http\Request;
use Livewire\Component;

class TicketAgentReportTable extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $agentId = null;

    public $agents = [];

    public $summary = [
        'totalAgents' => 0,
        'agentsWithDue' => 0,
        'totalPayable' => 0,
        'totalPaid' => 0,
        'totalDue' => 0,
    ];

    public $allAgents = [];

    protected function loadData(): void
    {
        $this->allAgents = TicketAgent::orderBy('name')->get(['id', 'name'])->keyBy('id')->toArray();

        $params = array_filter([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'agent_id' => $this->agentId,
        ], fn ($v) => $v !== null && $v !== '');

        $request = Request::create('/api/reports/ticket-agent', 'GET', $params);

        $response = app(TicketAgentReportController::class)->data($request);
        $payload = $response->getData(true);

        $this->agents = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? $this->summary;
    }

    public function boot()
    {
        $this->loadData();
    }

    public function updatedDateFrom(): void
    {
        $this->loadData();
    }

    public function updatedDateTo(): void
    {
        $this->loadData();
    }

    public function updatedAgentId(): void
    {
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->agentId = null;
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.report.ticket-agent-report-table', [
            'agentsList' => $this->allAgents,
            'reportData' => $this->agents,
            'summary' => $this->summary,
        ]);
    }
}
