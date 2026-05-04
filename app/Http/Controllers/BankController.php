<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BankController extends Controller
{
    protected array $uniqueRules = [
        'banks' => ['name'],
    ];

    protected array $nullableRules = [
        'banks' => ['description'],
    ];

    public function index()
    {
        $banks = Bank::orderBy('name')->paginate(10)->withQueryString();
        return view('banks.index', compact('banks'));
    }

    public function create()
    {
        return view('banks.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->getValidationRules('banks'));

        try {
            Bank::create($validated);
            return redirect()->route('banks.index')->with('success', 'Bank created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create bank.')->withInput();
        }
    }

    public function edit(Bank $bank)
    {
        return view('banks.edit', compact('bank'));
    }

    public function update(Request $request, Bank $bank)
    {
        $validated = $request->validate($this->getValidationRules('banks', $bank->id));

        try {
            $bank->update($validated);
            return redirect()->route('banks.index')->with('success', 'Bank updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update bank.')->withInput();
        }
    }

    public function destroy(Bank $bank)
    {
        try {
            $bank->delete();
            return redirect()->route('banks.index')->with('success', 'Bank deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete bank.');
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