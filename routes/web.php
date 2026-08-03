<?php

use App\Http\Controllers\BookingConditionController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchWiseReportController;
use App\Http\Controllers\PassengerController;
use App\Http\Controllers\ReIssueController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AirlineCityController;
use App\Http\Controllers\AirlineClassController;
use App\Http\Controllers\AirlineController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CityCodeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\FlightDateGapController;
use App\Http\Controllers\FingerprintChargeController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\TicketAgentController;
use App\Http\Controllers\TravelClassController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisaAgentController;
use App\Http\Controllers\VisaAgentCostController;
use App\Http\Controllers\CommissionAgentController;
use App\Http\Controllers\VisaSellingPriceController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\PassengerStatusController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VisaAdminController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\FareAdminController;
use App\Http\Controllers\TicketFareController;
use App\Http\Controllers\TicketIssueController;
use App\Http\Controllers\PendingOutboundReportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\FingerprintReportController;
use App\Http\Controllers\VisaSubmissionController;
use App\Http\Controllers\VisaReportController;
use App\Enums\Location;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use Carbon\Carbon;
use App\Http\Controllers\VisaAgentReportController;
use App\Http\Controllers\TicketAgentReportController;
use App\Http\Controllers\UserWiseSalesReportController;
use App\Http\Controllers\ProfitLossReportController;
use Illuminate\Support\Facades\Route;

// Guest routes (accessible without authentication)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    // Home / Dashboard
    Route::get('/', fn() => redirect('/dashboard'))->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Main Pages
    Route::resource('districts', DistrictController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('banks', BankController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('booking-conditions', BookingConditionController::class)->middleware('role:Super Admin,Co Admin');
    Route::patch('/booking-conditions/{bookingCondition}/toggle-active', [BookingConditionController::class, 'toggleActive'])->name('booking-conditions.toggle-active')->middleware('role:Super Admin,Co Admin');
    Route::resource('branches', BranchController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('city-codes', CityCodeController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::resource('airlines', AirlineController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('classes', TravelClassController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::resource('airline-classes', AirlineClassController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('airline-cities', AirlineCityController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('role:Super Admin,Co Admin,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create')->middleware('role:Super Admin,Co Admin');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff,Delivery Staff');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->middleware('role:Super Admin,Co Admin');
    Route::match(['PUT', 'PATCH'], '/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('role:Super Admin,Co Admin');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('role:Super Admin,Co Admin');
    Route::resource('visa-agents', VisaAgentController::class)->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::resource('ticket-agents', TicketAgentController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/fingerprint-charges', [FingerprintChargeController::class, 'index'])->name('fingerprint-charges.index');
    Route::get('/fingerprint-charges/create', [FingerprintChargeController::class, 'create'])->name('fingerprint-charges.create')->middleware('role:Super Admin,Co Admin');
    Route::post('/fingerprint-charges', [FingerprintChargeController::class, 'store'])->name('fingerprint-charges.store')->middleware('role:Super Admin,Co Admin');
    Route::get('/fingerprint-charges/{fingerprint_charge}/edit', [FingerprintChargeController::class, 'edit'])->name('fingerprint-charges.edit')->middleware('role:Super Admin,Co Admin');
    Route::match(['PUT', 'PATCH'], '/fingerprint-charges/{fingerprint_charge}', [FingerprintChargeController::class, 'update'])->name('fingerprint-charges.update')->middleware('role:Super Admin,Co Admin');
    Route::delete('/fingerprint-charges/{fingerprint_charge}', [FingerprintChargeController::class, 'destroy'])->name('fingerprint-charges.destroy')->middleware('role:Super Admin,Co Admin');
    Route::resource('flight-date-gaps', FlightDateGapController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('routes', RouteController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::get('/api/ticket-fares/baggage', [\App\Http\Controllers\TicketFareController::class, 'getBaggageAllowance'])->name('api.ticket-fares.baggage');
    Route::get('/api/ticket-fares/flight-date-gap', [\App\Http\Controllers\TicketFareController::class, 'getFlightDateGap'])->name('api.ticket-fares.flight-date-gap');
    Route::patch('/ticket-fares/{ticketFare}/toggle-active', [\App\Http\Controllers\TicketFareController::class, 'toggleActive'])->name('ticket-fares.toggle-active')->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::resource('ticket-fares', \App\Http\Controllers\TicketFareController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('commission-agents', CommissionAgentController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('visa-agent-costs', VisaAgentCostController::class)->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::resource('visa-selling-prices', VisaSellingPriceController::class)->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::resource('currency-rates', CurrencyRateController::class)->middleware('role:Super Admin,Co Admin');
    Route::patch('/packages/{package}/toggle-active', [PackageController::class, 'toggleActive'])->name('packages.toggle-active')->middleware('role:Super Admin,Co Admin');
    Route::resource('packages', PackageController::class)->middleware('role:Super Admin,Co Admin');
    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('role:Super Admin,Co Admin');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('role:Super Admin');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('role:Super Admin');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('role:Super Admin');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('role:Super Admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('role:Super Admin');
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active')->middleware('role:Super Admin');
    Route::resource('transaction-types', TransactionTypeController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('passenger-statuses', PassengerStatusController::class);
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::resource('bookings', BookingController::class)->except(['create', 'store', 'edit', 'update', 'destroy']);
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
    Route::match(['PUT', 'PATCH'], '/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy')->middleware('role:Super Admin');
    Route::resource('passengers', PassengerController::class)->except(['destroy']);
    Route::delete('/passengers/{passenger}', [PassengerController::class, 'destroy'])->name('passengers.destroy')->middleware('role:Super Admin,Co Admin');
    Route::patch('/passengers/{passenger}/status', [PassengerController::class, 'updateStatus'])->name('passengers.update-status')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Delivery Staff');
    Route::post('/passengers/{passenger}/documents', [PassengerController::class, 'uploadDocument'])->name('passengers.documents.store');
    Route::get('/passengers/{passenger}/documents/{document}/download', [PassengerController::class, 'downloadDocument'])->name('passengers.documents.download');
    Route::delete('/passengers/{passenger}/documents/{document}', [PassengerController::class, 'destroyDocument'])->name('passengers.documents.destroy')->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::get('/passengers/{passenger}/download-all-docs', [PassengerController::class, 'downloadAllDocuments'])->name('passengers.download-all-docs');
    Route::patch('/passengers/{passenger}/toggle-ticket-hold', [PassengerController::class, 'toggleTicketHold'])->name('passengers.toggle-ticket-hold')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::patch('/passengers/{passenger}/ticket-remarks', [PassengerController::class, 'updateTicketRemarks'])->name('passengers.ticket-remarks')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');

    // Booking-specific routes
    Route::post('/bookings/{booking}/passengers', [BookingController::class, 'addPassenger'])->name('bookings.passengers.store');
    Route::delete('/bookings/{booking}/passengers/{passenger}', [BookingController::class, 'removePassenger'])->name('bookings.passengers.destroy')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::get('/bookings/{booking}/print', [BookingController::class, 'print'])->name('bookings.print');
    Route::patch('/bookings/{booking}/passengers/{passenger}/recalculate', [BookingController::class, 'recalculatePassengerValue'])->name('bookings.passengers.recalculate')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff');
    Route::patch('/bookings/{booking}/fingerprint-location', [BookingController::class, 'updateFingerprintLocation'])->name('bookings.fingerprint-location.update')->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::post('/bookings/{booking}/payment', [BookingController::class, 'storePayment'])->name('bookings.payment.store');
    Route::put('/bookings/{booking}/payment/{payment}', [BookingController::class, 'updatePayment'])->name('bookings.payment.update')->middleware('role:Super Admin');

    // Visa submission routes
    Route::post('/bookings/{booking}/passengers/{passenger}/visa-submit', [VisaSubmissionController::class, 'submit'])
        ->name('bookings.passengers.visa-submit')
        ->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::post('/bookings/{booking}/passengers/{passenger}/visa-issue', [VisaSubmissionController::class, 'issue'])
        ->name('bookings.passengers.visa-issue')
        ->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::put('/bookings/{booking}/passengers/{passenger}/visa-edit', [VisaSubmissionController::class, 'edit'])
        ->name('bookings.passengers.visa-edit')
        ->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::post('/bookings/{booking}/passengers/{passenger}/visa-cancel', [VisaSubmissionController::class, 'cancel'])
        ->name('bookings.passengers.visa-cancel')
        ->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::post('/bookings/{booking}/passengers/{passenger}/visa-resubmit', [VisaSubmissionController::class, 'reSubmit'])
        ->name('bookings.passengers.visa-resubmit')
        ->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');

    // Document routes
    Route::get('/bookings/{booking}/download-all-docs', [BookingController::class, 'downloadAllDocs'])->name('bookings.download-all-docs');
    Route::post('/documents/upload', [DocumentController::class, 'upload'])->name('documents.upload');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/passenger/upload', [DocumentController::class, 'uploadPassenger'])->name('documents.passenger.upload');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // API routes
    Route::post('/api/bookings/calculate-type', [BookingController::class, 'calculatePassengerType'])->name('api.bookings.calculate-type');
    Route::get('/api/bookings/fingerprint-charge', [BookingController::class, 'getFingerprintCharge'])->name('api.bookings.fingerprint-charge');
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
    Route::get('/api/bookings/search-invoice', [BookingController::class, 'searchInvoice'])->name('api.bookings.search-invoice');
    Route::get('/api/ticket-fares/filter', [TicketFareController::class, 'filter'])->name('api.ticket-fares.filter');
    Route::get('/fares/admin', [FareAdminController::class, 'index'])->name('fare.admin')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::post('/fares/admin/agent', [FareAdminController::class, 'storeAgent'])->name('fare.admin.agent.store')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::put('/fares/admin/agent/{ticketAgent}', [FareAdminController::class, 'updateAgent'])->name('fare.admin.agent.update')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::delete('/fares/admin/agent/{ticketAgent}', [FareAdminController::class, 'destroyAgent'])->name('fare.admin.agent.destroy')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::post('/fares/admin/fare', [FareAdminController::class, 'storeFare'])->name('fare.admin.fare.store')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::put('/fares/admin/fare/{ticketFare}', [FareAdminController::class, 'updateFare'])->name('fare.admin.fare.update')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::delete('/fares/admin/fare/{ticketFare}', [FareAdminController::class, 'destroyFare'])->name('fare.admin.fare.destroy')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/visas/admin', [VisaAdminController::class, 'index'])->name('visa.admin')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/fingerprints/admin', function () {
        $canAssignStaff = auth()->user()->roles->whereIn('name', ['Super Admin', 'Co Admin', 'Fingerprint Admin'])->isNotEmpty();
        $divisions = \App\Models\District::distinct()->pluck('division')->sort()->values();
        $districts = \App\Models\District::orderBy('division')->orderBy('name')->get(['id', 'name', 'division']);

        $fingerprintStatuses = \App\Enums\FingerprintStatus::cases();

        $flightDateRanges = [];
        $today = (int) now()->format('d');
        $currentThird = $today <= 10 ? 1 : ($today <= 20 ? 2 : 3);
        $months = [
            ['offset' => 0, 'startPart' => $currentThird],
            ['offset' => 1, 'startPart' => 1],
            ['offset' => 2, 'startPart' => 1],
            ['offset' => 3, 'startPart' => 1],
        ];
        foreach ($months as $m) {
            if (count($flightDateRanges) >= 9) break;
            $month = now()->copy()->addMonths($m['offset'])->startOfMonth();
            $lastDay = (int) $month->copy()->endOfMonth()->format('d');
            $label = $month->format('M');
            $parts = [
                1 => ['start' => $month->format('Y-m-01'), 'end' => $month->format('Y-m-10'), 'label' => "{$label} 1–10"],
                2 => ['start' => $month->format('Y-m-11'), 'end' => $month->format('Y-m-20'), 'label' => "{$label} 11–20"],
                3 => ['start' => $month->format('Y-m-21'), 'end' => $month->copy()->endOfMonth()->format('Y-m-d'), 'label' => "{$label} 21–{$lastDay}"],
            ];
            for ($p = $m['startPart']; $p <= 3; $p++) {
                if (count($flightDateRanges) >= 9) break;
                $flightDateRanges[] = array_merge(['id' => count($flightDateRanges) + 1], $parts[$p]);
            }
        }

        return view('fingerprints.admin', compact('canAssignStaff', 'divisions', 'districts', 'fingerprintStatuses', 'flightDateRanges'));
    })->name('fingerprint.admin')->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::get('/fingerprints/staff', function () {
        $isFingerprintStaff = auth()->user()->hasRole('Fingerprint Staff');

        $fingerprintStatuses = \App\Enums\FingerprintStatus::cases();

        $flightDateRanges = [];
        $today = (int) now()->format('d');
        $currentThird = $today <= 10 ? 1 : ($today <= 20 ? 2 : 3);
        $months = [
            ['offset' => 0, 'startPart' => $currentThird],
            ['offset' => 1, 'startPart' => 1],
            ['offset' => 2, 'startPart' => 1],
            ['offset' => 3, 'startPart' => 1],
        ];
        foreach ($months as $m) {
            if (count($flightDateRanges) >= 9) break;
            $month = now()->copy()->addMonths($m['offset'])->startOfMonth();
            $lastDay = (int) $month->copy()->endOfMonth()->format('d');
            $label = $month->format('M');
            $parts = [
                1 => ['start' => $month->format('Y-m-01'), 'end' => $month->format('Y-m-10'), 'label' => "{$label} 1–10"],
                2 => ['start' => $month->format('Y-m-11'), 'end' => $month->format('Y-m-20'), 'label' => "{$label} 11–20"],
                3 => ['start' => $month->format('Y-m-21'), 'end' => $month->copy()->endOfMonth()->format('Y-m-d'), 'label' => "{$label} 21–{$lastDay}"],
            ];
            for ($p = $m['startPart']; $p <= 3; $p++) {
                if (count($flightDateRanges) >= 9) break;
                $flightDateRanges[] = array_merge(['id' => count($flightDateRanges) + 1], $parts[$p]);
            }
        }

        return view('fingerprints.staff', compact('isFingerprintStaff', 'fingerprintStatuses', 'flightDateRanges'));
    })->name('fingerprint.staff')->middleware('role:Super Admin,Co Admin,Fingerprint Staff');

    Route::get('/api/fingerprints/admin', [FingerprintController::class, 'adminIndex'])
        ->name('api.fingerprints.admin')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::get('/api/fingerprints/staff', [FingerprintController::class, 'staffIndex'])
        ->name('api.fingerprints.staff')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Staff');
    Route::get('/api/fingerprints/staff-list', [FingerprintController::class, 'staffList'])
        ->name('api.fingerprints.staff-list')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Admin,Fingerprint Staff');
    Route::put('/api/fingerprints/{fingerprint}/staff', [FingerprintController::class, 'assignStaff'])
        ->name('api.fingerprints.assign-staff')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::put('/api/fingerprints/{fingerprint}/cost', [FingerprintController::class, 'updateCost'])
        ->name('api.fingerprints.update-cost')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Staff');
    Route::put('/api/fingerprints/detail/{fingerprintDetail}/status', [FingerprintController::class, 'updateStatus'])
        ->name('api.fingerprints.update-status')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Admin,Fingerprint Staff');
    Route::post('/api/fingerprints/detail/{fingerprintDetail}/hold', [FingerprintController::class, 'hold'])
        ->name('api.fingerprints.hold')
        ->middleware('role:Super Admin,Co Admin,Fingerprint Admin,Fingerprint Staff');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/flight-date-gap', [SettingsController::class, 'updateFlightDateGap'])->name('settings.flight-date-gap.update')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/fingerprint-charge', [SettingsController::class, 'updateFingerprintCharge'])->name('settings.fingerprint-charge.update')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/package-configuration', [SettingsController::class, 'updatePackageConfiguration'])->name('settings.package-configuration.update')->middleware('role:Super Admin,Co Admin');
    Route::post('/settings/package', [SettingsController::class, 'storePackage'])->name('settings.package.store')->middleware('role:Super Admin,Co Admin');
    Route::get('/settings/package/{package}', [SettingsController::class, 'showPackage'])->name('settings.package.show');
    Route::put('/settings/package/{package}', [SettingsController::class, 'updatePackage'])->name('settings.package.update')->middleware('role:Super Admin,Co Admin');
    Route::post('/settings/package/{package}', [SettingsController::class, 'updatePackage'])->middleware('role:Super Admin,Co Admin');
    Route::delete('/settings/package/{package}', [SettingsController::class, 'destroyPackage'])->name('settings.package.destroy')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/stay-duration-limit', [SettingsController::class, 'updateStayDurationLimit'])->name('settings.stay-duration-limit.update')->middleware('role:Super Admin,Co Admin');

    // Reports
    Route::get('/reports/statement', fn() => view('reports.statement'))->name('report.statement');
    Route::get('/reports/profit-loss', fn() => view('reports.profit-loss'))->name('report.profit-loss')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/profit-loss', [ProfitLossReportController::class, 'data'])->name('api.reports.profit-loss')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/profit-loss/print', [ProfitLossReportController::class, 'print'])->name('report.profit-loss.print')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/fingerprint', [FingerprintReportController::class, 'index'])->name('report.fingerprint')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/reports/fingerprint/print', [FingerprintReportController::class, 'print'])->name('report.fingerprint.print')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/api/reports/fingerprint', [FingerprintReportController::class, 'data'])->name('api.reports.fingerprint')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/api/reports/fingerprint/details/{fingerprintDetail}', [FingerprintReportController::class, 'details'])->name('api.reports.fingerprint.details')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/reports/visa', [VisaReportController::class, 'index'])->name('report.visa')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/api/reports/visa', [VisaReportController::class, 'data'])->name('api.reports.visa')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/reports/visa-agent', [VisaAgentReportController::class, 'index'])->name('report.visa-agent')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/api/reports/visa-agent', [VisaAgentReportController::class, 'data'])->name('api.reports.visa-agent')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/api/reports/visa-agent/{visaAgent}/logs', [VisaAgentReportController::class, 'logs'])->name('api.reports.visa-agent.logs')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/api/reports/visa-agent/{visaAgent}/submissions', [VisaAgentReportController::class, 'submissions'])->name('api.reports.visa-agent.submissions')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/api/reports/visa-agent/{visaAgent}/issued', [VisaAgentReportController::class, 'issued'])->name('api.reports.visa-agent.issued')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/api/reports/visa-agent/{visaAgent}/combined', [VisaAgentReportController::class, 'combined'])->name('api.reports.visa-agent.combined')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/reports/visa-agent/{visaAgent}/print', [VisaAgentReportController::class, 'printReport'])->name('report.visa-agent.print')->middleware('role:Super Admin,Co Admin,Visa Admin');
    Route::get('/reports/ticket-agent', [TicketAgentReportController::class, 'index'])->name('report.ticket-agent')->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::get('/api/reports/ticket-agent', [TicketAgentReportController::class, 'data'])->name('api.reports.ticket-agent')->middleware('role:Super Admin,Co Admin,Ticket Admin');
    Route::get('/reports/due', [\App\Http\Controllers\DueReportController::class, 'index'])->name('report.due')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/due', [\App\Http\Controllers\DueReportController::class, 'data'])->name('api.reports.due')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/due/branch/{branchId}/details', [\App\Http\Controllers\DueReportController::class, 'branchDetails'])->name('api.reports.due.branch-details')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/due/customer/{invoiceId}/transactions', [\App\Http\Controllers\DueReportController::class, 'customerTransactions'])->name('api.reports.due.customer-transactions')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/due/branch/{branchId}/print-customers', [\App\Http\Controllers\DueReportController::class, 'printCustomers'])->name('report.due.print-customers')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/due/branch/{branchId}/print-datewise', [\App\Http\Controllers\DueReportController::class, 'printDateWise'])->name('report.due.print-datewise')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/reissue-refund', fn() => view('reports.reissue-refund'))->name('report.reissue-refund')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/reports/user-wise-sales', fn() => view('reports.user-wise-sales'))->name('report.user-sales')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/user-wise-sales/filters', [UserWiseSalesReportController::class, 'filters'])->name('api.reports.user-wise-sales.filters')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/api/reports/user-wise-sales', [UserWiseSalesReportController::class, 'data'])->name('api.reports.user-wise-sales.data')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/pending-outbound', [\App\Http\Controllers\PendingOutboundReportController::class, 'index'])->name('report.pending-ticket')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/api/reports/pending-outbound', [\App\Http\Controllers\PendingOutboundReportController::class, 'data'])->name('api.reports.pending-outbound')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/reports/payment-receiving', function () {
        $branchId = auth()->user()->branch_id;
        $dateFrom = request('date_from') ? Carbon::parse(request('date_from')) : now()->subDays(30);
        $dateTo = request('date_to') ? Carbon::parse(request('date_to')) : now();

        $totalCashPayment = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::CASH)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $totalBankPayment = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_method', PaymentMethod::BANK)
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $totalBdOfficeCollection = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('branch', fn($q) => $q->where('location', Location::BD))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $totalKsaOfficeCollection = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('branch', fn($q) => $q->where('location', Location::KSA))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->sum('amount');

        $payments = Payment::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereHas('vouchers.transactionType', fn($q) => $q->whereIn('name', ['Initial Payment', 'Due Collection']))
            ->with([
                'branch',
                'vouchers.transactionType',
                'vouchers.user.branch',
                'vouchers.invoice.booking',
            ])
            ->get();

        $dailyPayments = $payments->groupBy(fn($p) => $p->created_at->format('Y-m-d'))
            ->map(function ($dayPayments, $date) {
                return [
                    'date' => $date,
                    'cash' => $dayPayments->where('payment_method', PaymentMethod::CASH)->sum('amount'),
                    'bank' => $dayPayments->where('payment_method', PaymentMethod::BANK)->sum('amount'),
                    'bd_office' => $dayPayments->filter(fn($p) => $p->branch?->location === Location::BD)->sum('amount'),
                    'ksa_office' => $dayPayments->filter(fn($p) => $p->branch?->location === Location::KSA)->sum('amount'),
                ];
            })
            ->sortKeys();

        $vouchersByDate = [];
        foreach ($payments as $payment) {
            $dateKey = $payment->created_at->format('Y-m-d');
            foreach ($payment->vouchers as $v) {
                $vouchersByDate[$dateKey][] = [
                    'invoice_id' => $v->invoice?->booking?->invoice_id ?? 'N/A',
                    'voucher_no' => $v->voucher_id ?? $v->id,
                    'method' => ucfirst($v->payment_method?->value ?? ''),
                    'transaction_type' => $v->transactionType?->name ?? '',
                    'trx_id' => $v->transaction_id ?? '-',
                    'receive_by' => $v->user?->name ?? '',
                    'receive_at' => $v->user?->branch?->name ?? 'Central',
                    'amount' => (float) $v->amount,
                    'receive_branch_id' => $v->user?->branch_id,
                ];
            }
        }
        $vouchersByDateJson = json_encode($vouchersByDate);
        $branchesJson = json_encode(\App\Models\Branch::orderBy('name')->get(['id', 'name']));

        return view('reports.payment-receiving', compact('totalCashPayment', 'totalBankPayment', 'totalBdOfficeCollection', 'totalKsaOfficeCollection', 'dailyPayments', 'vouchersByDateJson', 'branchesJson'));
    })->name('report.payment-receiving')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/branch-due-details', fn() => view('reports.branch-due-details'))->name('report.branch-due-details')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/branch-wise', [BranchWiseReportController::class, 'index'])->name('report.branch-wise');
    Route::get('/reports/branch-wise/payment-history/print', [BranchWiseReportController::class, 'paymentHistoryPrint'])->name('report.branch-wise.payment-history-print')->middleware('role:Super Admin,Co Admin,Auditor');

    // Detail Pages with parameters
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show'])->middleware('role:Super Admin,Co Admin');
    Route::get('/payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit')->middleware('role:Super Admin');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update')->middleware('role:Super Admin');
    Route::get('/payments/{payment}/print-voucher', [PaymentController::class, 'printVoucher'])->name('payments.print-voucher')->middleware('role:Super Admin,Co Admin');

    /* Temporarily disabled
    Route::resource('invoices', InvoiceController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('vouchers', VoucherController::class)->middleware('role:Super Admin,Co Admin');
    */
    Route::get('/invoices/{id}/print', fn($id) => view('invoices.print', compact('id')))->name('invoices.print');
    Route::get('/re-issues/{id}/confirm', fn($id) => view('re-issues.confirmation', compact('id')))->name('re-issues.confirmation');
    Route::get('/refunds/{id}/confirm', fn($id) => view('refunds.confirmation', compact('id')))->name('refunds.confirmation');
    Route::get('/tickets', fn() => view('tickets.index'))->name('tickets.index')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/tickets/{id}/print', fn($id) => view('tickets.print', compact('id')))->name('tickets.print')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/tickets/{id}/add-confirm', fn($id) => view('tickets.add-confirmation', compact('id')))->name('tickets.add-confirmation')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');

    Route::middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff')->group(function () {
        Route::post('/bookings/{booking}/passengers/{passenger}/ticket-issue', [TicketIssueController::class, 'issue'])
            ->name('bookings.passengers.ticket-issue');
        Route::put('/bookings/{booking}/passengers/{passenger}/ticket-edit', [TicketIssueController::class, 'edit'])
            ->name('bookings.passengers.ticket-edit');
        Route::put('/passengers/{passenger}/confirm-group', [TicketIssueController::class, 'confirmGroup'])
            ->name('passengers.confirm-group');
        Route::post('/passengers/{passenger}/create-outbound-pending', [TicketIssueController::class, 'createPendingOutbound'])
            ->name('passengers.create-outbound-pending');
        Route::post('/bookings/{booking}/passengers/{passenger}/re-issue', [ReIssueController::class, 'store'])
            ->name('bookings.passengers.re-issue');
        Route::post('/bookings/{booking}/passengers/{passenger}/refund', [RefundController::class, 'store'])
            ->name('bookings.passengers.refund');
        Route::put('/passengers/{passenger}/confirm-group', [TicketIssueController::class, 'confirmGroup'])
            ->name('passengers.confirm-group');
    });

    Route::post('/api/banks/quick-create', [BankController::class, 'quickStore']);
    Route::post('/api/ticket-fares/quick-create', [TicketFareController::class, 'quickStore'])
        ->middleware('auth');
});

require __DIR__.'/booking-cancellation.php';
