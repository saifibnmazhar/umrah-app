<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class DashboardSummary extends Component
{
    public array $stats = [];

    public bool $showSummaryCards = true;

    public bool $showProfitCards = false;

    public bool $showRequests = false;

    public array $totals = [];

    public function render()
    {
        return view('livewire.dashboard.summary');
    }
}
