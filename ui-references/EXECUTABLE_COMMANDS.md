# Laravel UI Integration - Executable Commands

This file contains step-by-step executable commands for integrating the UI reference into Laravel.

---

## Task 0: Setup UI Reference Folder (READ-ONLY)

```
Task 0: VERIFY Reference Folder
├── Type: VERIFY
├── Command: ls -la ./ui-references/
├── Expected: Directory with .html, .js files exists
└── Status: Pre-condition - folder already exists
```

---

## Task 1: Move UI Assets into Laravel

```bash
# Task 1.1: Merge Tailwind Config
# Compare ./ui-references/dashboard.html tailwind config with existing
# Merge custom slate colors (50-950) into theme.extend.colors

# Task 1.2: Extract Custom CSS
mkdir -p resources/css
cat > resources/css/app.css << 'EOF'
@tailwind base;
@tailwind components;
@tailwind utilities;

/* Custom styles from UI reference */
.nav-item { color: #94a3b8; transition: all 0.2s ease; }
.nav-item:hover { color: white; background-color: rgba(255,255,255,0.1); }
.nav-item.active { background-color: white; color: #1e293b; }
.modal-overlay { transition: opacity 0.2s ease; }
.modal-content { transition: transform 0.2s ease, opacity 0.2s ease; }
.toast { transition: transform 0.3s ease, opacity 0.3s ease; }
.search-input { background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%); border: 1px solid #d4d4d4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.075); }
.filter-btn { background: linear-gradient(to bottom, #fff 0%, #e9ecef 100%); border: 1px solid #d4d4d4; box-shadow: 0 1px 0 rgba(255,255,255,0.5); }
.date-input { background: linear-gradient(to bottom, #fff 0%, #f8f9fa 100%); border: 1px solid #d4d4d4; box-shadow: inset 0 1px 2px rgba(0,0,0,0.075); }
.table-header { background: linear-gradient(to bottom, #f3f3f3 0%, #e8e8e8 0%); border: 1px solid #d4d4d4; }
.table-row { background-color: #ffffff; border: 1px solid #d4d4d4; }
EOF

# Verify
ls -la resources/css/app.css
```

---

## Task 2: Create Main Layout

```bash
# Task 2: CREATE Main Layout
mkdir -p resources/views/layouts
touch resources/views/layouts/app.blade.php

# Content to add:
cat > resources/views/layouts/app.blade.php << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BM Umrah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen px-4" x-data>
    @include('partials.nav')
    
    <main class="py-6">
        @yield('content')
    </main>
    
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>
</body>
</html>
EOF

# Verify
ls -la resources/views/layouts/app.blade.php
```

---

## Task 3: Create Navigation Partial

```bash
# Task 3: CREATE Navigation Partial
mkdir -p resources/views/partials
touch resources/views/partials/nav.blade.php

# Extract from: ./ui-references/nav.html (lines 4-77)
# Add Alpine conversion for mobile menu
cat > resources/views/partials/nav.blade.php << 'EOF'
<nav class="bg-slate-800 text-white sticky top-0 z-40 shadow-lg -mx-4 pb-8" x-data="{ mobileOpen: false }">
    <div class="w-full mx-auto px-4">
        <div class="flex justify-center items-center h-16 space-x-5">
            <div class="flex-shrink-0">
                <h1 class="text-xl font-bold">BM Umrah Booking</h1>
            </div>
            
            <!-- Desktop Nav Links -->
            <div class="hidden md:flex md:justify-center md:items-center space-x-1" id="desktopNav">
                <a href="/dashboard" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="dashboard">Dashboard</a>
                <a href="/bookings" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="booking">Booking</a>
                <a href="/fingerprints/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintAdmin">Fingerprint Admin</a>
                <a href="/fingerprints/staff" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="fingerprintStaff">Fingerprint Staff</a>
                <a href="/visas/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="visaAdmin">Visa Admin</a>
                <a href="/fares/admin" class="nav-item px-4 py-2 rounded-md font-medium text-sm text-slate-400 hover:text-white transition" data-tab="ticketAdmin">Ticket Admin</a>
                
                <!-- Reports Dropdown -->
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
            
            <!-- Mobile Menu Button -->
            <button @click="mobileOpen = !mobileOpen" class="md:hidden p-2 rounded-md hover:bg-slate-700" id="mobileMenuBtn">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
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
EOF

# Verify
ls -la resources/views/partials/nav.blade.php
```

---

## Task 4: Convert HTML Pages to Blade Views

```bash
# Task 4.1: CREATE Dashboard View
mkdir -p resources/views/dashboard
touch resources/views/dashboard/index.blade.php

# Add content with @extends, @section, dummy data, Alpine components
# (Full content from dashboard.html, converted to Blade with dummy data)

# Task 4.2: CREATE Booking View
mkdir -p resources/views/bookings
touch resources/views/bookings/index.blade.php

# Task 4.3-4.7: CREATE Admin Views
mkdir -p resources/views/fares
mkdir -p resources/views/visas
mkdir -p resources/views/fingerprints
mkdir -p resources/views/settings
touch resources/views/fares/admin.blade.php
touch resources/views/visas/admin.blade.php
touch resources/views/fingerprints/admin.blade.php
touch resources/views/fingerprints/staff.blade.php
touch resources/views/settings/index.blade.php

# Task 4.8-4.18: CREATE Report Views
mkdir -p resources/views/reports
touch resources/views/reports/statement.blade.php
touch resources/views/reports/profit-loss.blade.php
touch resources/views/reports/fingerprint.blade.php
touch resources/views/reports/visa.blade.php
touch resources/views/reports/visa-agent.blade.php
touch resources/views/reports/ticket-agent.blade.php
touch resources/views/reports/due.blade.php
touch resources/views/reports/reissue-refund.blade.php
touch resources/views/reports/user-wise-sales.blade.php
touch resources/views/reports/pending-outbound.blade.php
touch resources/views/reports/payment-receiving.blade.php
touch resources/views/reports/branch-due-details.blade.php

# Task 4.19-4.28: CREATE Detail/Confirmation Views
mkdir -p resources/views/invoices
mkdir -p resources/views/passengers
mkdir -p resources/views/packages
mkdir -p resources/views/re-issues
mkdir -p resources/views/refunds
mkdir -p resources/views/tickets
touch resources/views/invoices/details.blade.php
touch resources/views/invoices/print.blade.php
touch resources/views/passengers/details.blade.php
touch resources/views/packages/details.blade.php
touch resources/views/re-issues/confirmation.blade.php
touch resources/views/refunds/confirmation.blade.php
touch resources/views/tickets/add-confirmation.blade.php
touch resources/views/visas/passenger-details.blade.php
touch resources/views/fares/passenger-details.blade.php

# Verify all 28 files created
ls -la resources/views/dashboard/
ls -la resources/views/bookings/
ls -la resources/views/fares/
ls -la resources/views/visas/
ls -la resources/views/fingerprints/
ls -la resources/views/settings/
ls -la resources/views/reports/
ls -la resources/views/invoices/
ls -la resources/views/passengers/
ls -la resources/views/packages/
ls -la resources/views/re-issues/
ls -la resources/views/refunds/
ls -la resources/views/tickets/
```

---

## Task 5: Setup Routes

```bash
# Task 5: EDIT Routes File
# Add all 28 routes to routes/web.php

# Edit routes/web.php and add:
# Dashboard
Route::get('/', fn() => redirect('/dashboard'));
Route::get('/dashboard', fn() => view('dashboard.index'));

# Main Pages
Route::get('/bookings', fn() => view('bookings.index'));
Route::get('/fares/admin', fn() => view('fares.admin'));
Route::get('/visas/admin', fn() => view('visas.admin'));
Route::get('/fingerprints/admin', fn() => view('fingerprints.admin'));
Route::get('/fingerprints/staff', fn() => view('fingerprints.staff'));
Route::get('/settings', fn() => view('settings.index'));

# Reports
Route::get('/reports/statement', fn() => view('reports.statement'));
Route::get('/reports/profit-loss', fn() => view('reports.profit-loss'));
Route::get('/reports/fingerprint', fn() => view('reports.fingerprint'));
Route::get('/reports/visa', fn() => view('reports.visa'));
Route::get('/reports/visa-agent', fn() => view('reports.visa-agent'));
Route::get('/reports/ticket-agent', fn() => view('reports.ticket-agent'));
Route::get('/reports/due', fn() => view('reports.due'));
Route::get('/reports/reissue-refund', fn() => view('reports.reissue-refund'));
Route::get('/reports/user-wise-sales', fn() => view('reports.user-wise-sales'));
Route::get('/reports/pending-outbound', fn() => view('reports.pending-outbound'));
Route::get('/reports/payment-receiving', fn() => view('reports.payment-receiving'));
Route::get('/reports/branch-due-details', fn() => view('reports.branch-due-details'));

# Detail Pages (with route parameters)
Route::get('/invoices/{id}', fn($id) => view('invoices.details', compact('id')))->name('invoices.details');
Route::get('/invoices/{id}/print', fn($id) => view('invoices.print', compact('id')))->name('invoices.print');
Route::get('/passengers/{id}', fn($id) => view('passengers.details', compact('id')))->name('passengers.details');
Route::get('/packages/{id}', fn($id) => view('packages.details', compact('id')))->name('packages.details');
Route::get('/re-issues/{id}/confirm', fn($id) => view('re-issues.confirmation', compact('id')))->name('re-issues.confirmation');
Route::get('/refunds/{id}/confirm', fn($id) => view('refunds.confirmation', compact('id')))->name('refunds.confirmation');
Route::get('/tickets/{id}/add-confirm', fn($id) => view('tickets.add-confirmation', compact('id')))->name('tickets.add-confirmation');

# Verify
php artisan route:list | grep "/dashboard"
```

---

## Task 6: Create Navigation Landing Page

```bash
# Task 6: CREATE Welcome Page
touch resources/views/welcome.blade.php

# Add content with links to all pages
cat > resources/views/welcome.blade.php << 'EOF'
@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-12">
    <h1 class="text-3xl font-bold text-slate-800 mb-8">BM Umrah Booking - Laravel UI</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-slate-700 mb-4">Main Pages</h2>
            <ul class="space-y-2">
                <li><a href="/dashboard" class="text-blue-600 hover:underline">Dashboard</a></li>
                <li><a href="/bookings" class="text-blue-600 hover:underline">Booking</a></li>
                <li><a href="/fares/admin" class="text-blue-600 hover:underline">Fare Admin</a></li>
                <li><a href="/visas/admin" class="text-blue-600 hover:underline">Visa Admin</a></li>
                <li><a href="/fingerprints/admin" class="text-blue-600 hover:underline">Fingerprint Admin</a></li>
                <li><a href="/fingerprints/staff" class="text-blue-600 hover:underline">Fingerprint Staff</a></li>
                <li><a href="/settings" class="text-blue-600 hover:underline">Settings</a></li>
            </ul>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-slate-700 mb-4">Reports</h2>
            <ul class="space-y-2">
                <li><a href="/reports/statement" class="text-blue-600 hover:underline">Statement</a></li>
                <li><a href="/reports/profit-loss" class="text-blue-600 hover:underline">Profit/Loss</a></li>
                <li><a href="/reports/visa" class="text-blue-600 hover:underline">Visa Report</a></li>
                <li><a href="/reports/due" class="text-blue-600 hover:underline">Due Report</a></li>
                <li><a href="/reports/fingerprint" class="text-blue-600 hover:underline">Fingerprint Report</a></li>
            </ul>
        </div>
    </div>
</div>
@endsection
EOF

# Verify
ls -la resources/views/welcome.blade.php
```

---

## Task 7: Setup Tailwind with Vite

```bash
# Task 7: VERIFY Tailwind Setup

# Install Tailwind if not present
npm install -D tailwindcss postcss autoprefixer

# Initialize Tailwind config
npx tailwindcss init -p

# Update tailwind.config.js with content paths
cat > tailwind.config.js << 'EOF'
export default {
  content: [
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        slate: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        }
      }
    },
  },
  plugins: [],
}
EOF

# Verify
npm run dev
```

---

## Task 8: Setup Alpine.js

```bash
# Task 8: CONFIGURE Alpine.js

# Install Alpine
npm install alpinejs

# Update resources/js/app.js
cat > resources/js/app.js << 'EOF'
import Alpine from 'alpinejs'

Alpine.start()

window.Alpine = Alpine
EOF

# Verify
# Check Alpine global available in browser console
```

---

## Task 9: Convert JavaScript to Alpine Components

```bash
# Task 9.1: CREATE Toast Component
mkdir -p resources/views/components
touch resources/views/components/toast.blade.php

cat > resources/views/components/toast.blade.php << 'EOF'
<div x-data="{ show: false, message: '', type: 'success' }"
     x-init="window.toast = (msg, t) => { message = msg; type = t; show = true; setTimeout(() => show = false, 3000) }"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-x-full opacity-0"
     x-transition:enter-end="translate-x-0 opacity-100"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="translate-x-0 opacity-100"
     x-transition:leave-end="translate-x-full opacity-0"
     class="fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white font-medium"
     :class="type === 'success' ? 'bg-slate-700' : 'bg-red-500'">
    <span x-text="message"></span>
</div>
EOF

# Task 9.2-9.5: CONVERT Other JS Functions to Alpine
# See Task 3 for Mobile Menu conversion
# See individual Blade views for Tab, Search, Modal conversions

# Verify
# Check Alpine functionality in browser console
```

---

## Task 10: Create Blade Components

```bash
# Task 10: CREATE 9 Blade Components
mkdir -p resources/views/components

# 10.1: Stat Card
touch resources/views/components/stat-card.blade.php
cat > resources/views/components/stat-card.blade.php << 'EOF'
@props(['title', 'value', 'subtitle' => '', 'icon' => '', 'color' => 'blue'])
<div class="bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 p-5">
    <div class="flex justify-between items-center mb-2">
        <h3 class="text-sm font-semibold text-slate-600">{{ $title }}</h3>
        @if($icon)
        <div class="w-10 h-10 rounded-full bg-{{ $color }}-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-{{ $color }}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {{ $icon }}
            </svg>
        </div>
        @endif
    </div>
    <div class="text-2xl font-bold text-slate-800">{{ $value }}</div>
    @if($subtitle)
    <div class="text-xs font-medium text-{{ $color }}-600 mt-1">{{ $subtitle }}</div>
    @endif
</div>
EOF

# 10.2: Status Badge
touch resources/views/components/status-badge.blade.php
cat > resources/views/components/status-badge.blade.php << 'EOF'
@props(['status' => 'pending'])
@php
$colorMap = [
    'pending' => 'yellow',
    'issued' => 'emerald',
    'done' => 'emerald',
    'processing' => 'blue',
    'submitted' => 'blue',
    'cancel' => 'red',
    'return' => 'red',
    'none' => 'slate',
    'hold' => 'slate',
];
$color = $colorMap[strtolower($status)] ?? 'slate';
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
    {{ $status }}
</span>
EOF

# 10.3: Page Header
touch resources/views/components/page-header.blade.php
cat > resources/views/components/page-header.blade.php << 'EOF'
@props(['title', 'subtitle' => ''])
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-slate-800">{{ $title }}</h2>
    @if($subtitle)
    <span class="text-sm text-slate-500">{{ $subtitle }}</span>
    @endif
</div>
EOF

# 10.4: Data Table
touch resources/views/components/data-table.blade.php
# Template for overflow-x-auto table with headers and rows

# 10.5: Search Input
touch resources/views/components/search-input.blade.php
cat > resources/views/components/search-input.blade.php << 'EOF'
@props(['placeholder' => 'Search...', 'model' => 'search'])
<input type="text" 
       {{ $attributes->merge(['class' => 'w-full md:w-64 px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition']) }}
       placeholder="{{ $placeholder }}"
       x-model="{{ $model }}">
EOF

# 10.6: Action Button
touch resources/views/components/action-button.blade.php
cat > resources/views/components/action-button.blade.php << 'EOF'
@props(['type' => 'primary', 'icon' => '', 'label' => ''])
<button type="button" {{ $attributes->class([
    'px-4 py-2 rounded-lg font-medium transition flex items-center gap-2',
    'bg-slate-700 text-white hover:bg-slate-800' => $type === 'primary',
    'bg-slate-100 text-slate-700 hover:bg-slate-200' => $type === 'secondary',
]) }}>
    @if($icon){{ $icon }}@endif
    {{ $label }}
    {{ $slot }}
</button>
EOF

# 10.7: Empty State
touch resources/views/components/empty-state.blade.php
cat > resources/views/components/empty-state.blade.php << 'EOF'
@props(['message' => 'No records found'])
<div class="text-center py-8 text-slate-500">
    {{ $message }}
</div>
EOF

# 10.8: Tab Button
touch resources/views/components/tab-button.blade.php
# Template for tab buttons with active/inactive states

# 10.9: Modal
touch resources/views/components/modal.blade.php
# Template for modal with x-show and backdrop

# Verify
ls -la resources/views/components/
```

---

## Task 11: Add Dummy Data

```bash
# Task 11: VERIFY All Pages Have Dummy Data
# Each Blade view should have @php block with dummy data

# Example for dashboard/index.blade.php:
@php
$stats = [
    'submittedVisa' => 120,
    'issuedVisa' => 80,
    'pendingVisa' => 68,
    'inboundTicket' => 1200,
    'outboundTicket' => 656,
    'pendingTicket' => 45,
    'totalInvoice' => 312,
    'totalDue' => '124,500 SAR',
    'totalProfit' => '89,750 SAR',
    'totalFingerprint' => 156,
    'totalPassengers' => 892,
    'totalReceived' => 86,
    'totalDueCollection' => '67,250 SAR',
    'departureDone' => 50,
    'departureStay' => 30
];

$bookings = [
    ['invoiceNo' => 'INV-2024-001', 'bookingDate' => '2024-04-01', 'customerName' => 'Ahmed Abdullah', 'mobile' => '+966501111111', 'route' => 'DAC-JED-DAC', 'status' => 'None', 'totalAmount' => 5500, 'paidAmount' => 3000, 'dueAmount' => 2500],
    ['invoiceNo' => 'INV-2024-002', 'bookingDate' => '2024-04-02', 'customerName' => 'Fatima Ali', 'mobile' => '+966559876543', 'route' => 'DAC-RUH-DAC', 'status' => 'Visa Application', 'totalAmount' => 6200, 'paidAmount' => 6200, 'dueAmount' => 0],
];
@endphp

# Verify each page has data
php artisan serve
# Visit each route to verify dummy data displays
```

---

## Task 12: Prepare Forms

```bash
# Task 12: VERIFY Form Fields

# Booking form fields:
# - customer_name
# - customer_mobile
# - passenger_name[]
# - passenger_passport[]
# - route
# - package_type
# - service_type

# Fare Admin form fields:
# - ticket_type
# - airline
# - from_city
# - to_city
# - selling_fare
# - net_fare

# Visa Admin form fields:
# - visa_price
# - effective_date
# - agent_name

# Settings form fields:
# - flight_date_gap
# - division
# - district
# - charge_amount

# Verify
# Check each form has correct name attributes
grep -r 'name="' resources/views/
```

---

## Task 13: Add UI States

```bash
# Task 13: IMPLEMENT UI States

# Empty State - Already in Task 10.7
# Usage: @forelse($items as $item) @empty <x-empty-state />

# Loading State - Add to pages:
# <div x-show="loading" class="animate-pulse">Loading...</div>
# x-data="{ loading: false }"
# x-init="setTimeout(() => loading = false, 1000)"

# Error State - Add to forms:
# <div x-show="error" class="text-red-500 text-sm">Error message</div>

# Verify
php artisan serve
# Check each state displays correctly
```

---

## Task 14: Cleanup Code

```bash
# Task 14: CLEANUP Code

# Remove duplicate nav HTML (should use @include)
# Remove inline <script> tags (converted to Alpine)
# Remove OLD NAVIGATION comments

# Verify no inline scripts remain
grep -r "<script>" resources/views/ | grep -v "x-script"

# Verify no duplicate nav
grep -r "nav class=\"bg-slate-800\"" resources/views/

# Verify old comments removed
grep -r "OLD NAVIGATION" resources/views/
```

---

## Task 15: Responsive Fixes

```bash
# Task 15: VERIFY Responsive Design

# Test on different breakpoints:
# - Mobile (<640px): Hamburger menu, stacked layouts
# - Tablet (640-1024px): 2-column grids
# - Desktop (>1024px): Full layout

# Verify no horizontal overflow
# Use browser devtools to test

# Verify mobile menu works
php artisan serve
# Resize browser to <640px and test hamburger menu
```

---

## Task 16: Documentation

```bash
# Task 16: CREATE Documentation
touch README.md

cat > README.md << 'EOF'
# BM Umrah Laravel UI Integration

## UI Reference Usage
Original HTML/JS files are located in `./ui-references/`
- DO NOT modify files in this folder
- Use as design reference only

## Laravel Structure
```
resources/
├── views/
│   ├── layouts/app.blade.php (main layout)
│   ├── partials/nav.blade.php (navigation)
│   ├── components/ (reusable components)
│   ├── dashboard/index.blade.php
│   ├── bookings/index.blade.php
│   ├── fares/admin.blade.php
│   ├── visas/admin.blade.php
│   ├── fingerprints/admin.blade.php
│   ├── fingerprints/staff.blade.php
│   ├── settings/index.blade.php
│   └── reports/ (11 report views)
```

## Blade Components
1. stat-card.blade.php - Dashboard statistics cards
2. status-badge.blade.php - Status indicators
3. page-header.blade.php - Page titles
4. data-table.blade.php - Data tables
5. search-input.blade.php - Search fields
6. action-button.blade.php - Buttons
7. empty-state.blade.php - Empty states
8. tab-button.blade.php - Tab navigation
9. modal.blade.php - Modal dialogs

## Alpine.js Patterns
- x-data: Component state
- x-show/x-hide: Conditional rendering
- x-model: Two-way binding
- x-transition: Animations
- @click: Event handlers

## Route Structure (28 routes)
- /dashboard - Dashboard
- /bookings - Booking Index
- /fares/admin - Fare Admin
- /visas/admin - Visa Admin
- /fingerprints/admin - Fingerprint Admin
- /fingerprints/staff - Fingerprint Staff
- /settings - Settings
- /reports/* - 11 Report routes
- /invoices/{id} - Invoice Details
- etc.

## Running the Project
```bash
npm install
npm run dev
php artisan serve
```
EOF

# Verify
ls -la README.md
```

---

## Summary: Quick Reference

| Task | Type | Key Commands |
|------|------|--------------|
| 0 | VERIFY | `ls -la ./ui-references/` |
| 1 | CREATE | `mkdir -p resources/css && cat > app.css` |
| 2 | CREATE | `mkdir -p resources/views/layouts && touch app.blade.php` |
| 3 | CREATE | `mkdir -p resources/views/partials && touch nav.blade.php` |
| 4 | CREATE | `mkdir -p resources/views/{dashboard,bookings,...} && touch *.blade.php` |
| 5 | EDIT | Edit `routes/web.php` |
| 6 | CREATE | `touch resources/views/welcome.blade.php` |
| 7 | VERIFY | `npm install && npm run dev` |
| 8 | EDIT | Edit `resources/js/app.js` |
| 9 | CREATE/EDIT | Create Alpine components |
| 10 | CREATE | `mkdir -p resources/views/components && touch *.blade.php` |
| 11 | VERIFY | Check each view has @php dummy data |
| 12 | VERIFY | Check form name attributes |
| 13 | ADD | Add loading/error states |
| 14 | CLEANUP | Remove inline scripts, duplicate nav |
| 15 | VERIFY | Browser responsive testing |
| 16 | CREATE | `touch README.md` |