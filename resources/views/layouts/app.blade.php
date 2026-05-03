<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BM Umrah')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen px-4" x-data="{ mobileMenuOpen: false }">
    <nav class="bg-slate-800 text-white sticky top-0 z-40 shadow-lg -mx-4 pb-8">
        <div class="w-full mx-auto px-4">
            <div class="flex justify-center items-center h-16 space-x-5">
                <div class="flex-shrink-0">
                    <h1 class="text-xl font-bold">BM Umrah Booking</h1>
                </div>
                
                <div class="hidden md:flex md:justify-center md:items-center space-x-1">
                    <a href="{{ route('dashboard') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Dashboard</a>
                    <a href="{{ route('booking.index') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Booking</a>
                    <a href="{{ route('fingerprint.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Fingerprint Admin</a>
                    <a href="{{ route('fingerprint.staff') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Fingerprint Staff</a>
                    <a href="{{ route('visa.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Visa Admin</a>
                    <a href="{{ route('ticket.admin') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Ticket Admin</a>
                    
                    <div class="relative group">
                        <a href="#" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Reports ▾</a>
                        <div class="absolute hidden group-hover:block bg-slate-700 rounded-md mt-0 pt-2 py-1 shadow-lg z-50 min-w-[220px]">
                            <a href="{{ route('report.fingerprint') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Fingerprint Report</a>
                            <a href="{{ route('report.visa') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Report</a>
                            <a href="{{ route('report.visa-agent') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Visa Agent Report</a>
                            <a href="{{ route('report.statement') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Statement</a>
                            <a href="{{ route('report.pending-ticket') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Pending Outbound Ticket Report</a>
                            <a href="{{ route('report.reissue-refund') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Re-Issue & Refund Report</a>
                            <a href="{{ route('report.ticket-agent') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Ticket Agent Report</a>
                            <a href="{{ route('report.payment-receiving') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Payment Receiving Report</a>
                            <a href="{{ route('report.due') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Due Report</a>
                            <a href="{{ route('report.profit-loss') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">Profit/Loss Report</a>
                            <a href="{{ route('report.user-sales') }}" class="block px-4 py-2 text-sm hover:bg-slate-600 whitespace-nowrap">User-wise Sales Report</a>
                        </div>
                    </div>
                    
                    <a href="{{ route('settings') }}" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition">Settings</a>
                </div>
                
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 rounded-md hover:bg-slate-700" id="mobileMenuBtn">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="hidden md:hidden bg-slate-700" :class="{ 'hidden': !mobileMenuOpen }">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="{{ route('dashboard') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Dashboard</a>
                <a href="{{ route('booking.index') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Booking</a>
                <a href="{{ route('fingerprint.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Admin</a>
                <a href="{{ route('fingerprint.staff') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Staff</a>
                <a href="{{ route('visa.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Admin</a>
                <a href="{{ route('ticket.admin') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Admin</a>
                
                <div class="border-t border-slate-600 pt-2 mt-2">
                    <span class="block px-3 py-1 text-xs text-slate-400 font-medium">REPORTS</span>
                    <a href="{{ route('report.fingerprint') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Fingerprint Report</a>
                    <a href="{{ route('report.visa') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Report</a>
                    <a href="{{ route('report.visa-agent') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Visa Agent Report</a>
                    <a href="{{ route('report.statement') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Statement</a>
                    <a href="{{ route('report.pending-ticket') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Pending Outbound Ticket Report</a>
                    <a href="{{ route('report.reissue-refund') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Re-Issue & Refund Report</a>
                    <a href="{{ route('report.ticket-agent') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Ticket Agent Report</a>
                    <a href="{{ route('report.payment-receiving') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Payment Receiving Report</a>
                    <a href="{{ route('report.due') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Due Report</a>
                    <a href="{{ route('report.profit-loss') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">Profit/Loss Report</a>
                    <a href="{{ route('report.user-sales') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600">User-wise Sales Report</a>
                </div>
                
                <a href="{{ route('settings') }}" class="block w-full text-left px-3 py-2 rounded-md font-medium hover:bg-slate-600 border-t border-slate-600 mt-2 pt-3">Settings</a>
            </div>
        </div>
    </nav>
    
    <main class="py-6">
        @yield('content')
    </main>
    
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>
</body>
</html>