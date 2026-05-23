<nav class="bg-slate-800 text-white sticky top-0 z-40 shadow-lg -mx-4 pb-8" x-data="{ mobileOpen: false }">
    @php
        $canAccessFingerprintAdmin = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Fingerprint Admin'])->isNotEmpty();
        $canAccessFingerprintStaff = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Fingerprint Staff'])->isNotEmpty();
        $canAccessVisa = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Visa Admin', 'Visa Staff'])->isNotEmpty();
        $canAccessTicket = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Ticket Admin', 'Ticket Staff'])->isNotEmpty();
        $canAccessAdmin = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin'])->isNotEmpty();
        $canAccessAdminReports = auth()->user()->roles->pluck('name')->intersect(['Super Admin', 'Co Admin', 'Auditor'])->isNotEmpty();
    @endphp
    <div class="w-full mx-auto px-4">
        <div class="flex justify-center items-center h-16 space-x-5">
            <div class="flex-shrink-0">
                <h1 class="text-xl font-bold">BM Umrah Booking</h1>
            </div>
            
            <div class="hidden md:flex md:justify-center md:items-center space-x-1" id="desktopNav">
                <a href="{{ route('dashboard') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="dashboard">Dashboard</a>
                <a href="{{ route('bookings.index') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="booking">Booking</a>
                @if($canAccessFingerprintAdmin)<a href="{{ route('fingerprint.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintAdmin">Fingerprint Admin</a>@endif
                @if($canAccessFingerprintStaff)<a href="{{ route('fingerprint.staff') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintStaff">Fingerprint Staff</a>@endif
                @if($canAccessVisa)<a href="{{ route('visa.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="visaAdmin">Visa Admin</a>@endif
                @if($canAccessTicket)<a href="{{ route('fare.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="ticketAdmin">Ticket Admin</a>@endif
                
                <div class="relative group">
                    <a href="#" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="reports">Reports ▾</a>
                    <div class="absolute hidden group-hover:block bg-slate-700 rounded-md mt-0 pt-2 py-1 shadow-lg z-50 min-w-[220px]">
                        @if($canAccessAdminReports)<a href="{{ route('report.fingerprint') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Fingerprint Report</a>@endif
                        @if($canAccessVisa)<a href="{{ route('report.visa') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Report</a>@endif
                        @if($canAccessVisa)<a href="{{ route('report.visa-agent') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Agent Report</a>@endif
                        @if($canAccessTicket)<a href="{{ route('report.statement') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Statement</a>@endif
                        @if($canAccessTicket)<a href="{{ route('report.pending-ticket') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Pending Outbound Ticket Report</a>@endif
                        @if($canAccessTicket)<a href="{{ route('report.reissue-refund') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Re-Issue & Refund Report</a>@endif
                        @if($canAccessTicket)<a href="{{ route('report.ticket-agent') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Agent Report</a>@endif
                        @if($canAccessAdminReports)<a href="{{ route('report.payment-receiving') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Payment Receiving Report</a>@endif
                        @if($canAccessAdminReports)<a href="{{ route('report.due') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Due Report</a>@endif
                        @if($canAccessAdminReports)<a href="{{ route('report.profit-loss') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Profit/Loss Report</a>@endif
                        @if($canAccessAdminReports)<a href="{{ route('report.user-sales') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">User-wise Sales Report</a>@endif
                    </div>
                </div>
                
                @if($canAccessAdmin)<a href="{{ route('settings') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="settings">Settings</a>@endif
                
                <div class="relative group">
                    <a href="#" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="appManagement">App Management ▾</a>
                    <div class="absolute hidden group-hover:block bg-slate-700 rounded-md mt-0 pt-2 py-1 shadow-lg z-50 min-w-[200px]">
                        @if($canAccessAdmin)<a href="{{ route('districts.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Districts</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('banks.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Banks</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('branches.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Branches</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('offices.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Offices</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('city-codes.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">City Codes</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('airlines.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Airlines</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('classes.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Travel Classes</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('airline-classes.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Airline Classes</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('airline-cities.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Airline Cities</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('customers.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Customers</a>@endif
                        @if($canAccessVisa)<a href="{{ route('visa-agents.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Agents</a>@endif
                        @if($canAccessTicket)<a href="{{ route('ticket-agents.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Agents</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('flight-date-gaps.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Flight Date Gap</a>@endif
                        @if($canAccessVisa)<a href="{{ route('visa-agent-costs.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Agent Costs</a>@endif
                        @if($canAccessVisa)<a href="{{ route('visa-selling-prices.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Selling Prices</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('currency-rates.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Currency Rates</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('transaction-types.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Transaction Types</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('routes.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Routes</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('invoices.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Invoices</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('payments.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Payments</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('vouchers.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Vouchers</a>@endif
                        @if($canAccessTicket)<a href="{{ route('ticket-fares.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Fares</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('packages.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Packages</a>@endif
                        @if($canAccessAdmin)<a href="{{ route('users.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Users</a>@endif
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">
                        Logout
                    </button>
                </form>
            </div>

            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-md hover:bg-slate-700" id="mobileMenuBtn">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
    
    <div class="hidden md:hidden bg-slate-700" :class="{ 'hidden': !mobileOpen }" id="mobileMenu">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Dashboard</a>
            <a href="{{ route('bookings.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Booking</a>
            @if($canAccessFingerprintAdmin)<a href="{{ route('fingerprint.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Admin</a>@endif
            @if($canAccessFingerprintStaff)<a href="{{ route('fingerprint.staff') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Staff</a>@endif
            @if($canAccessVisa)<a href="{{ route('visa.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Admin</a>@endif
            @if($canAccessTicket)<a href="{{ route('fare.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Admin</a>@endif
            
            <div class="border-t border-slate-600 pt-2 mt-2">
                <span class="block px-3 py-1 text-xs text-slate-400 font-medium">REPORTS</span>
                @if($canAccessAdminReports)<a href="{{ route('report.fingerprint') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Report</a>@endif
                @if($canAccessVisa)<a href="{{ route('report.visa') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Report</a>@endif
                @if($canAccessVisa)<a href="{{ route('report.visa-agent') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Agent Report</a>@endif
                @if($canAccessTicket)<a href="{{ route('report.statement') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Statement</a>@endif
                @if($canAccessTicket)<a href="{{ route('report.pending-ticket') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Pending Outbound Ticket Report</a>@endif
                @if($canAccessTicket)<a href="{{ route('report.reissue-refund') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Re-Issue & Refund Report</a>@endif
                @if($canAccessTicket)<a href="{{ route('report.ticket-agent') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Agent Report</a>@endif
                @if($canAccessAdminReports)<a href="{{ route('report.payment-receiving') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Payment Receiving Report</a>@endif
                @if($canAccessAdminReports)<a href="{{ route('report.due') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Due Report</a>@endif
                @if($canAccessAdminReports)<a href="{{ route('report.profit-loss') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Profit/Loss Report</a>@endif
                @if($canAccessAdminReports)<a href="{{ route('report.user-sales') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">User-wise Sales Report</a>@endif
            </div>
            
            @if($canAccessAdmin)<a href="{{ route('settings') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600 border-t border-slate-600 mt-2 pt-3">Settings</a>@endif
            
            <div class="border-t border-slate-600 pt-2 mt-2">
                <span class="block px-3 py-1 text-xs text-slate-400 font-medium">APP MANAGEMENT</span>
                @if($canAccessAdmin)<a href="{{ route('districts.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Districts</a>@endif
                @if($canAccessAdmin)<a href="{{ route('banks.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Banks</a>@endif
                @if($canAccessAdmin)<a href="{{ route('branches.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Branches</a>@endif
                @if($canAccessAdmin)<a href="{{ route('offices.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Offices</a>@endif
                @if($canAccessAdmin)<a href="{{ route('city-codes.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">City Codes</a>@endif
                @if($canAccessAdmin)<a href="{{ route('airlines.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Airlines</a>@endif
                @if($canAccessAdmin)<a href="{{ route('classes.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Travel Classes</a>@endif
                @if($canAccessAdmin)<a href="{{ route('airline-classes.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Airline Classes</a>@endif
                @if($canAccessAdmin)<a href="{{ route('airline-cities.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Airline Cities</a>@endif
                @if($canAccessAdmin)<a href="{{ route('customers.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Customers</a>@endif
                @if($canAccessVisa)<a href="{{ route('visa-agents.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Agents</a>@endif
                @if($canAccessTicket)<a href="{{ route('ticket-agents.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Agents</a>@endif
                @if($canAccessAdmin)<a href="{{ route('flight-date-gaps.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Flight Date Gap</a>@endif
                @if($canAccessVisa)<a href="{{ route('visa-agent-costs.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Agent Costs</a>@endif
                @if($canAccessVisa)<a href="{{ route('visa-selling-prices.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Selling Prices</a>@endif
                @if($canAccessAdmin)<a href="{{ route('currency-rates.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Currency Rates</a>@endif
                @if($canAccessAdmin)<a href="{{ route('transaction-types.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Transaction Types</a>@endif
                @if($canAccessAdmin)<a href="{{ route('routes.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Routes</a>@endif
                @if($canAccessAdmin)<a href="{{ route('invoices.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Invoices</a>@endif
                @if($canAccessAdmin)<a href="{{ route('payments.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Payments</a>@endif
                @if($canAccessAdmin)<a href="{{ route('vouchers.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Vouchers</a>@endif
                @if($canAccessTicket)<a href="{{ route('ticket-fares.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Fares</a>@endif
                @if($canAccessAdmin)<a href="{{ route('packages.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Packages</a>@endif
                @if($canAccessAdmin)<a href="{{ route('users.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Users</a>@endif
            </div>

            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-600 mt-2 pt-2">
                @csrf
                <button type="submit" class="block w-full text-left px-3 py-2 rounded-md font-medium text-red-400 hover:text-red-300 hover:bg-slate-600">
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>