<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Collection;
use Livewire\Component;

class DashboardRequestTabs extends Component
{
    public Collection $reissueRequests;

    public Collection $addTicketRequests;

    public Collection $refundRequests;

    public bool $showRequests = false;

    public function render()
    {
        return view('livewire.dashboard.request-tabs');
    }
}
