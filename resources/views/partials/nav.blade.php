<nav class="bg-slate-800 text-white sticky top-0 z-40 shadow-lg -mx-4 pb-8" x-data="{ mobileOpen: false }">
    <div class="w-full mx-auto px-4">
        <div class="flex justify-center items-center h-16 space-x-5">
            <div class="flex-shrink-0">
                <h1 class="text-xl font-bold">BM Umrah Booking</h1>
            </div>
            
            <div class="hidden md:flex md:justify-center md:items-center space-x-1" id="desktopNav">
                <a href="/dashboard" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="dashboard">Dashboard</a>
                <a href="/bookings" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="booking">Booking</a>
                <a href="/fingerprints/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintAdmin">Fingerprint Admin</a>
                <a href="/fingerprints/staff" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintStaff">Fingerprint Staff</a>
                <a href="/visas/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="visaAdmin">Visa Admin</a>
                <a href="/fares/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="ticketAdmin">Ticket Admin</a>
                
                <div class="relative group">
                    <a href="#" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="reports">Reports ▾</a>
                    <div class="absolute hidden group-hover:block bg-slate-700 rounded-md mt-0 pt-2 py-1 shadow-lg z-50 min-w-[220px]">
                        <a href="/reports/fingerprint" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Fingerprint Report</a>
                        <a href="/reports/visa" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Report</a>
                        <a href="/reports/visa-agent" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Agent Report</a>
                        <a href="/reports/statement" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Statement</a>
                        <a href="/reports/pending-outbound" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Pending Outbound Ticket Report</a>
                        <a href="/reports/reissue-refund" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Re-Issue & Refund Report</a>
                        <a href="/reports/ticket-agent" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Agent Report</a>
                        <a href="/reports/payment-receiving" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Payment Receiving Report</a>
                        <a href="/reports/due" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Due Report</a>
                        <a href="/reports/profit-loss" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Profit/Loss Report</a>
                        <a href="/reports/user-wise-sales" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">User-wise Sales Report</a>
                    </div>
                </div>
                
                <a href="/settings" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="settings">Settings</a>
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
            <a href="/dashboard" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Dashboard</a>
            <a href="/bookings" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Booking</a>
            <a href="/fingerprints/admin" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Admin</a>
            <a href="/fingerprints/staff" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Staff</a>
            <a href="/visas/admin" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Admin</a>
            <a href="/fares/admin" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Admin</a>
            
            <div class="border-t border-slate-600 pt-2 mt-2">
                <span class="block px-3 py-1 text-xs text-slate-400 font-medium">REPORTS</span>
                <a href="/reports/fingerprint" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Report</a>
                <a href="/reports/visa" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Report</a>
                <a href="/reports/visa-agent" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Agent Report</a>
                <a href="/reports/statement" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Statement</a>
                <a href="/reports/pending-outbound" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Pending Outbound Ticket Report</a>
                <a href="/reports/reissue-refund" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Re-Issue & Refund Report</a>
                <a href="/reports/ticket-agent" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Agent Report</a>
                <a href="/reports/payment-receiving" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Payment Receiving Report</a>
                <a href="/reports/due" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Due Report</a>
                <a href="/reports/profit-loss" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Profit/Loss Report</a>
                <a href="/reports/user-wise-sales" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">User-wise Sales Report</a>
            </div>
            
            <a href="/settings" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600 border-t border-slate-600 mt-2 pt-3">Settings</a>
        </div>
    </div>
</nav>