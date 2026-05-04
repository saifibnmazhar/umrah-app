<?php

namespace App\Http\Controllers;

use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DistrictController extends Controller
{
    protected array $uniqueRules = [
        'districts' => ['name'],
    ];

    protected array $nullableRules = [
        'districts' => [],
    ];

    public function index()
    {
        $districts = District::orderBy('name')->paginate(10);
        return view('districts.index', compact('districts'));
    }

    public function create()
    {
        return view('districts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->getValidationRules('districts'));

        try {
            District::create($validated);
            return redirect()->route('districts.index')->with('success', 'District created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create district.')->withInput();
        }
    }

    public function edit(District $district)
    {
        return view('districts.edit', compact('district'));
    }

    public function update(Request $request, District $district)
    {
        $validated = $request->validate($this->getValidationRules('districts', $district->id));

        try {
            $district->update($validated);
            return redirect()->route('districts.index')->with('success', 'District updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update district.')->withInput();
        }
    }

    public function destroy(District $district)
    {
        try {
            $district->delete();
            return redirect()->route('districts.index')->with('success', 'District deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete district.');
        }
    }

    protected function getValidationRules(string $table, $id = null): array
    {
        $columns = Schema::getColumnListing($table);
        $rules = [];

        $skipColumns = ['id', 'created_at', 'updated_at'];
        $uniqueColumns = $this->uniqueRules[$table] ?? [];
        $nullableColumns = $this->nullableRules[$table] ?? [];

        foreach ($columns as $column) {
            if (in_array($column, $skipColumns)) {
                continue;
            }

            $columnType = Schema::getColumnType($table, $column);
            $rule = $this->mapColumnTypeToRule($columnType);

            if (in_array($column, $uniqueColumns)) {
                $rule .= '|unique:' . $table . ',' . $column . ($id ? ',' . $id : '');
            }

            if (in_array($column, $nullableColumns)) {
                $rule = str_replace('required', 'nullable', $rule);
            }

            $rules[$column] = $rule;
        }

        return $rules;
    }

    protected function mapColumnTypeToRule(string $type): string
    {
        return match (true) {
            str_contains($type, 'integer'), str_contains($type, 'bigint'), str_contains($type, 'smallint') => 'required|integer',
            str_contains($type, 'decimal'), str_contains($type, 'float'), str_contains($type, 'double') => 'required|numeric',
            str_contains($type, 'boolean') => 'required|boolean',
            str_contains($type, 'text') => 'required|string',
            default => 'required|string|max:255',
        };
    }
}