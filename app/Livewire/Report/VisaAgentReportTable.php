<?php

namespace App\Livewire\Report;

use App\Http\Controllers\VisaAgentReportController;
use App\Models\VisaAgent;
use Illuminate\Http\Request;
use Livewire\Component;

class VisaAgentReportTable extends Component
{
    public ?string $search = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $visaAgentId = null;

    public $visaAgents = [];

    public $summary = [
        'totalAgents' => 0,
        'agentsWithDue' => 0,
        'totalPayable' => 0,
        'totalPaid' => 0,
        'totalBalance' => 0,
    ];

    public $allVisaAgents = [];

    protected function loadData(): void
    {
        $this->allVisaAgents = VisaAgent::orderBy('name')->get(['id', 'name'])->keyBy('id')->toArray();

        $request = Request::create('/api/reports/visa-agent', 'GET', array_filter([
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'visa_agent_id' => $this->visaAgentId,
        ]));

        $response = app(VisaAgentReportController::class)->data($request);
        $payload = $response->getData(true);

        $this->visaAgents = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? $this->summary;
    }

    public function updatedSearch(): void
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

    public function updatedVisaAgentId(): void
    {
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->visaAgentId = null;
        $this->loadData();
    }

    public function boot()
    {
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.report.visa-agent-report-table', [
            'visaAgents' => $this->allVisaAgents,
            'reportData' => $this->visaAgents,
            'summary' => $this->summary,
        ]);
    }
}
