<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index()
    {
        $users = User::with(['branch', 'roles'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $this->ensureSuperAdmin();
        $allBranches = Branch::orderBy('name')->get();
        $fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        return view('users.create', compact('allBranches', 'fingerprintBranches', 'roles'));
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'branch_id' => 'nullable|exists:branches,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $roleName = Str::lower($role->name);

        if (Str::contains($roleName, 'fingerprint')) {
            $request->validate(['branch_id' => 'required|exists:branches,id']);
            $branch = Branch::find($request->branch_id);
            if (!$branch || !$branch->fingerprint_operation) {
                return back()->withErrors(['branch_id' => 'Fingerprint roles require a branch with fingerprint operations enabled.'])->withInput();
            }
        }

        $validated['password'] = bcrypt($validated['password']);
        $user = User::create($validated);
        $user->roles()->sync([$validated['role_id']]);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->ensureSuperAdmin();
        $allBranches = Branch::orderBy('name')->get();
        $fingerprintBranches = Branch::where('fingerprint_operation', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $user->load('roles');
        return view('users.edit', compact('user', 'allBranches', 'fingerprintBranches', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureSuperAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8|confirmed',
            'branch_id' => 'nullable|exists:branches,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $roleName = Str::lower($role->name);

        if (Str::contains($roleName, 'fingerprint')) {
            $request->validate(['branch_id' => 'required|exists:branches,id']);
            $branch = Branch::find($request->branch_id);
            if (!$branch || !$branch->fingerprint_operation) {
                return back()->withErrors(['branch_id' => 'Fingerprint roles require a branch with fingerprint operations enabled.'])->withInput();
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->roles()->sync([$validated['role_id']]);

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->ensureSuperAdmin();
        $user->delete();
        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleActive(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return back()->with('error', 'Super Admin cannot be deactivated.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$status} successfully.");
    }
}
