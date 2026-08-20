<?php

namespace App\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseListTable extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * Reset the search query and return to the first page.
     */
    public function resetSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Apply the search term to the query if non-empty.
     *
     * @param  Builder  $query
     * @param  array<string|int, string|array>  $columns  Column names to search (LIKE %search%).
     *                                                    For custom operator/value, pass [column => [operator, value]].
     * @return Builder
     */
    protected function applySearch($query, array $columns)
    {
        if (! $this->search) {
            return $query;
        }

        return $query->where(function ($q) use ($columns) {
            foreach ($columns as $key => $config) {
                // Sequential array: value is the column name (string).
                if (is_int($key) && is_string($config)) {
                    $q->orWhere($config, 'like', '%'.$this->search.'%');

                    continue;
                }

                // Associative array: key is the column name.
                if (is_array($config)) {
                    $operator = $config[0] ?? 'like';
                    $value = $config[1] ?? $this->search;
                    $pattern = $operator === 'like' ? '%'.$value.'%' : $value;
                    $q->orWhere($key, $operator, $pattern);
                } else {
                    $q->orWhere($key, 'like', '%'.$this->search.'%');
                }
            }
        });
    }
}
