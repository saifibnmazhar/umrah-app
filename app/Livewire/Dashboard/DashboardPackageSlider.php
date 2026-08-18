<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Collection;
use Livewire\Component;

class DashboardPackageSlider extends Component
{
    public Collection $packages;

    public bool $showPackages = true;

    public function render()
    {
        return view('livewire.dashboard.package-slider');
    }
}
