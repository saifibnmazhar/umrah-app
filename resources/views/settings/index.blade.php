@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-3xl mx-auto pt-6 container" x-data="{ activeTab: 'flight-date-gap' }">
    <h1 class="text-2xl font-bold mb-6">Admin Settings</h1>

    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
            <button
                @click="activeTab = 'flight-date-gap'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'flight-date-gap', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'flight-date-gap' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Flight Date Gap
            </button>
            <button
                @click="activeTab = 'fingerprint-charge'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'fingerprint-charge', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'fingerprint-charge' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Fingerprint Charge
            </button>
            <button
                @click="activeTab = 'package-configuration'"
                :class="{ 'border-blue-500 text-blue-600': activeTab === 'package-configuration', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'package-configuration' }"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
            >
                Package Configuration
            </button>
        </nav>
    </div>

    <div x-show="activeTab === 'flight-date-gap'" x-cloak>
        <form method="POST" action="{{ route('settings.flight-date-gap.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <label for="min_days" class="block text-sm font-medium text-gray-700 mb-1">Minimum Days</label>
                    <input type="number" id="min_days" name="min_days" value="{{ $settings['min_days'] ?? 3 }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                </div>

                <div>
                    <label for="max_days" class="block text-sm font-medium text-gray-700 mb-1">Maximum Days</label>
                    <input type="number" id="max_days" name="max_days" value="{{ $settings['max_days'] ?? 30 }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                </div>

                <div>
                    <label for="flight_gap_notice" class="block text-sm font-medium text-gray-700 mb-1">Flight Date Gap Notice (days)</label>
                    <input type="number" id="flight_gap_notice" name="flight_gap_notice" value="{{ $settings['flight_gap_notice'] ?? 7 }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                </div>

                <div>
                    <label for="default_airport" class="block text-sm font-medium text-gray-700 mb-1">Default Airport</label>
                    <select id="default_airport" name="default_airport" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                        <option value="JFK" {{ ($settings['default_airport'] ?? 'JFK') === 'JFK' ? 'selected' : '' }}>John F. Kennedy International Airport</option>
                        <option value="LAX" {{ ($settings['default_airport'] ?? 'JFK') === 'LAX' ? 'selected' : '' }}>Los Angeles International Airport</option>
                        <option value="ORD" {{ ($settings['default_airport'] ?? 'JFK') === 'ORD' ? 'selected' : '' }}>O'Hare International Airport</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Flight Date Gap Settings
                </button>
            </div>
        </form>
    </div>

    <div x-show="activeTab === 'fingerprint-charge'" x-cloak>
        <form method="POST" action="{{ route('settings.fingerprint-charge.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <label for="charge_central" class="block text-sm font-medium text-gray-700 mb-1">Central District Charge</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="charge_central" name="charge_central" value="{{ $settings['charge_central'] ?? 150 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>

                <div>
                    <label for="charge_north" class="block text-sm font-medium text-gray-700 mb-1">North District Charge</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="charge_north" name="charge_north" value="{{ $settings['charge_north'] ?? 200 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>

                <div>
                    <label for="charge_south" class="block text-sm font-medium text-gray-700 mb-1">South District Charge</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="charge_south" name="charge_south" value="{{ $settings['charge_south'] ?? 175 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>

                <div>
                    <label for="charge_east" class="block text-sm font-medium text-gray-700 mb-1">East District Charge</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="charge_east" name="charge_east" value="{{ $settings['charge_east'] ?? 180 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>

                <div>
                    <label for="charge_west" class="block text-sm font-medium text-gray-700 mb-1">West District Charge</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="charge_west" name="charge_west" value="{{ $settings['charge_west'] ?? 190 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Fingerprint Charges
                </button>
            </div>
        </form>
    </div>

    <div x-show="activeTab === 'package-configuration'" x-cloak>
        <form method="POST" action="{{ route('settings.package-configuration.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-6">
                <div>
                    <label for="package_name" class="block text-sm font-medium text-gray-700 mb-1">Package Name</label>
                    <input type="text" id="package_name" name="package_name" value="{{ $settings['package_name'] ?? 'Umrah Premium Package' }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                </div>

                <div>
                    <label for="package_price" class="block text-sm font-medium text-gray-700 mb-1">Package Price</label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input type="number" id="package_price" name="package_price" value="{{ $settings['package_price'] ?? 2500 }}" class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                    </div>
                </div>

                <div>
                    <label for="package_features" class="block text-sm font-medium text-gray-700 mb-1">Package Features</label>
                    <textarea id="package_features" name="package_features" rows="6" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">{{ $settings['package_features'] ?? '• 5-star accommodation\n• Round-trip flights\n• Visa processing\n• Airport transfers\n• Guided tours\n• 24/7 support' }}</textarea>
                    <p class="mt-1 text-sm text-gray-500">Enter each feature on a new line starting with •</p>
                </div>

                <div>
                    <label for="package_duration" class="block text-sm font-medium text-gray-700 mb-1">Package Duration (days)</label>
                    <input type="number" id="package_duration" name="package_duration" value="{{ $settings['package_duration'] ?? 10 }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                </div>

                <div>
                    <label for="package_status" class="block text-sm font-medium text-gray-700 mb-1">Package Status</label>
                    <select id="package_status" name="package_status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm px-3 py-2 border">
                        <option value="active" {{ ($settings['package_status'] ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ ($settings['package_status'] ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Package Configuration
                </button>
            </div>
        </form>
    </div>
</div>
@endsection