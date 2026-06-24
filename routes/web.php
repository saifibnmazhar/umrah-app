<?php

use App\Http\Controllers\BookingConditionController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PassengerController;
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
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\FingerprintReportController;
use App\Http\Controllers\VisaSubmissionController;
use App\Http\Controllers\VisaReportController;
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
    Route::resource('city-codes', CityCodeController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('airlines', AirlineController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('classes', TravelClassController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('airline-classes', AirlineClassController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('airline-cities', AirlineCityController::class)->middleware('role:Super Admin,Co Admin');
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('role:Super Admin,Co Admin,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create')->middleware('role:Super Admin,Co Admin');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->middleware('role:Super Admin,Co Admin');
    Route::match(['PUT', 'PATCH'], '/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('role:Super Admin,Co Admin');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('role:Super Admin,Co Admin');
    Route::resource('visa-agents', VisaAgentController::class)->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::resource('ticket-agents', TicketAgentController::class)->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::resource('fingerprint-charges', FingerprintChargeController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('flight-date-gaps', FlightDateGapController::class)->middleware('role:Super Admin,Co Admin');
    Route::resource('routes', RouteController::class)->middleware('role:Super Admin,Co Admin');
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
    Route::patch('/passengers/{passenger}/status', [PassengerController::class, 'updateStatus'])->name('passengers.update-status')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff');
    Route::post('/passengers/{passenger}/documents', [PassengerController::class, 'uploadDocument'])->name('passengers.documents.store');
    Route::get('/passengers/{passenger}/documents/{document}/download', [PassengerController::class, 'downloadDocument'])->name('passengers.documents.download');
    Route::delete('/passengers/{passenger}/documents/{document}', [PassengerController::class, 'destroyDocument'])->name('passengers.documents.destroy')->middleware('role:Super Admin,Co Admin');
    Route::get('/passengers/{passenger}/download-all-docs', [PassengerController::class, 'downloadAllDocuments'])->name('passengers.download-all-docs');

    // Booking-specific routes
    Route::post('/bookings/{booking}/passengers', [BookingController::class, 'addPassenger'])->name('bookings.passengers.store');
    Route::delete('/bookings/{booking}/passengers/{passenger}', [BookingController::class, 'removePassenger'])->name('bookings.passengers.destroy')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::get('/bookings/{booking}/print', [BookingController::class, 'print'])->name('bookings.print');
    Route::patch('/bookings/{booking}/passengers/{passenger}/recalculate', [BookingController::class, 'recalculatePassengerValue'])->name('bookings.passengers.recalculate')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff');
    Route::patch('/bookings/{booking}/fingerprint-location', [BookingController::class, 'updateFingerprintLocation'])->name('bookings.fingerprint-location.update')->middleware('role:Super Admin,Co Admin');
    Route::post('/bookings/{booking}/payment', [BookingController::class, 'storePayment'])->name('bookings.payment.store');

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
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy')->middleware('role:Super Admin,Co Admin');

    // API routes
    Route::post('/api/bookings/calculate-type', [BookingController::class, 'calculatePassengerType'])->name('api.bookings.calculate-type');
    Route::get('/api/bookings/fingerprint-charge', [BookingController::class, 'getFingerprintCharge'])->name('api.bookings.fingerprint-charge');
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
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
        return view('fingerprints.admin', compact('canAssignStaff', 'divisions', 'districts'));
    })->name('fingerprint.admin')->middleware('role:Super Admin,Co Admin,Fingerprint Admin');
    Route::get('/fingerprints/staff', function () {
        $user = auth()->user();
        $isFingerprintStaff = $user->hasRole('Fingerprint Staff');
        $canEditCost = $user->hasRole('Super Admin') || $user->hasRole('Fingerprint Staff');
        return view('fingerprints.staff', compact('isFingerprintStaff', 'canEditCost'));
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
        ->middleware('role:Super Admin,Fingerprint Staff');
    Route::put('/api/fingerprints/detail/{fingerprintDetail}/status', [FingerprintController::class, 'updateStatus'])
        ->name('api.fingerprints.update-status')
        ->middleware('role:Fingerprint Admin,Fingerprint Staff');
    Route::post('/api/fingerprints/detail/{fingerprintDetail}/hold', [FingerprintController::class, 'hold'])
        ->name('api.fingerprints.hold')
        ->middleware('role:Fingerprint Admin,Fingerprint Staff');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/flight-date-gap', [SettingsController::class, 'updateFlightDateGap'])->name('settings.flight-date-gap.update')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/fingerprint-charge', [SettingsController::class, 'updateFingerprintCharge'])->name('settings.fingerprint-charge.update')->middleware('role:Super Admin,Co Admin');
    Route::put('/settings/package-configuration', [SettingsController::class, 'updatePackageConfiguration'])->name('settings.package-configuration.update')->middleware('role:Super Admin,Co Admin');
    Route::post('/settings/package', [SettingsController::class, 'storePackage'])->name('settings.package.store')->middleware('role:Super Admin,Co Admin');
    Route::get('/settings/package/{package}', [SettingsController::class, 'showPackage'])->name('settings.package.show')->middleware('role:Super Admin,Co Admin,Branch Manager,Branch Staff,Auditor,Visa Admin,Visa Staff,Ticket Admin,Ticket Staff,Fingerprint Admin,Fingerprint Staff');
    Route::put('/settings/package/{package}', [SettingsController::class, 'updatePackage'])->name('settings.package.update')->middleware('role:Super Admin,Co Admin');
    Route::post('/settings/package/{package}', [SettingsController::class, 'updatePackage'])->middleware('role:Super Admin,Co Admin');
    Route::delete('/settings/package/{package}', [SettingsController::class, 'destroyPackage'])->name('settings.package.destroy')->middleware('role:Super Admin,Co Admin');

    // Reports
    Route::get('/reports/statement', fn() => view('reports.statement'))->name('report.statement');
    Route::get('/reports/profit-loss', fn() => view('reports.profit-loss'))->name('report.profit-loss')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/fingerprint', [FingerprintReportController::class, 'index'])->name('report.fingerprint')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/reports/fingerprint/print', [FingerprintReportController::class, 'print'])->name('report.fingerprint.print')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/api/reports/fingerprint', [FingerprintReportController::class, 'data'])->name('api.reports.fingerprint')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/api/reports/fingerprint/details/{fingerprintDetail}', [FingerprintReportController::class, 'details'])->name('api.reports.fingerprint.details')->middleware('role:Super Admin,Co Admin,Auditor,Fingerprint Admin');
    Route::get('/reports/visa', [VisaReportController::class, 'index'])->name('report.visa')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/api/reports/visa', [VisaReportController::class, 'data'])->name('api.reports.visa')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/reports/visa-agent', fn() => view('reports.visa-agent'))->name('report.visa-agent')->middleware('role:Super Admin,Co Admin,Visa Admin,Visa Staff');
    Route::get('/reports/ticket-agent', fn() => view('reports.ticket-agent'))->name('report.ticket-agent')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/reports/due', fn() => view('reports.due'))->name('report.due')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/reissue-refund', fn() => view('reports.reissue-refund'))->name('report.reissue-refund')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/reports/user-wise-sales', fn() => view('reports.user-wise-sales'))->name('report.user-sales')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/pending-outbound', fn() => view('reports.pending-outbound'))->name('report.pending-ticket')->middleware('role:Super Admin,Co Admin,Ticket Admin,Ticket Staff');
    Route::get('/reports/payment-receiving', fn() => view('reports.payment-receiving'))->name('report.payment-receiving')->middleware('role:Super Admin,Co Admin,Auditor');
    Route::get('/reports/branch-due-details', fn() => view('reports.branch-due-details'))->name('report.branch-due-details')->middleware('role:Super Admin,Co Admin,Auditor');

    // Detail Pages with parameters
    Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show'])->middleware('role:Super Admin,Co Admin');

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
    });

    Route::post('/api/banks/quick-create', [BankController::class, 'quickStore']);
    Route::post('/api/ticket-fares/quick-create', [TicketFareController::class, 'quickStore'])
        ->middleware('auth');
});
