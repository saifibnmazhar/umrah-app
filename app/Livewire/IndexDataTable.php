<?php

namespace App\Livewire;

use App\Http\Controllers\BookingController;
use App\Models\Branch;
use Illuminate\Http\Request;
use Livewire\Component;

abstract class IndexDataTable extends Component
{
    public mixed $search = null;

    public mixed $perPage = null;

    public array $pagination = [
        'current_page' => 1,
        'last_page' => 1,
        'per_page' => 15,
        'total' => 0,
    ];

    public array $summary = [];

    public array $filterOptions = [];

    public array $branches = [];

    public bool $loading = false;

    abstract protected function endpoint(): string;

    abstract protected function controllerMethod(): string;

    abstract protected function filterParams(): array;

    abstract protected function dataTableProperty(): string;

    protected function loadData(): void
    {
        $this->loading = true;

        $params = array_filter(
            array_merge(
                ['search' => $this->search],
                $this->filterParams(),
                ['per_page' => $this->perPage],
            ),
            fn ($v) => $v !== null && $v !== '',
        );

        $params['page'] = $this->pagination['current_page'];

        $request = Request::create($this->endpoint(), 'GET', $params);

        $response = app(BookingController::class)->{$this->controllerMethod()}($request);
        $payload = $response->getData(true);

        $this->{$this->dataTableProperty()} = $payload['data'] ?? [];
        $this->summary = $payload['summary'] ?? [];
        $this->pagination = array_merge($this->pagination, $payload['pagination'] ?? []);
        $this->filterOptions = $payload['filterOptions'] ?? [];

        $this->loading = false;
    }

    public function updatedSearch(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function updatedPerPage(): void
    {
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function resetFilters(): void
    {
        $this->search = null;
        $this->pagination['current_page'] = 1;
        $this->loadData();
    }

    public function goToPage(int $page): void
    {
        $this->pagination['current_page'] = $page;
        $this->loadData();
    }

    protected function branchOptions(): array
    {
        if (auth()->user()->branch_id) {
            return [];
        }

        return Branch::orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id')
            ->toArray();
    }
}
