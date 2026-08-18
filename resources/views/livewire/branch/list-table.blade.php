<div>
    <div class="mb-4">
        <input type="text"
               wire:model.live="search"
               placeholder="Search branches by name, code, or location..."
               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition">
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs font-medium uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Address</th>
                        <th class="px-4 py-3 text-left">Contacts</th>
                        <th class="px-4 py-3 text-left">Location</th>
                        <th class="px-4 py-3 text-left">Branch Code</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($branches as $branch)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700">{{ $branch->id }}</td>
                            <td class="px-4 py-3 text-slate-700 font-medium">{{ $branch->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $branch->address }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $branch->contacts }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $branch->location }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $branch->branch_code }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('branches.edit', $branch->id) }}" class="text-slate-600 hover:text-slate-800 font-medium" aria-label="Edit {{ $branch->name }}">Edit</a>
                                    <form method="POST" action="{{ route('branches.destroy', $branch->id) }}" onsubmit="return confirm('Are you sure you want to delete this branch?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium" aria-label="Delete {{ $branch->name }}">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                No branches found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-center">
            {{ $branches->links() }}
        </div>
    </div>
</div>
