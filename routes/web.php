<?php

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
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\FlightDateGapController;
use App\Http\Controllers\FingerprintChargeController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\TicketAgentController;
use App\Http\Controllers\TravelClassController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisaAgentController;
use App\Http\Controllers\VisaAgentCostController;
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
    Route::resource('districts', DistrictController::class);
    Route::resource('banks', BankController::class);
    Route::resource('branches', BranchController::class);
    Route::resource('offices', OfficeController::class);
    Route::resource('city-codes', CityCodeController::class);
    Route::resource('airlines', AirlineController::class);
    Route::resource('classes', TravelClassController::class);
    Route::resource('airline-classes', AirlineClassController::class);
    Route::resource('airline-cities', AirlineCityController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('visa-agents', VisaAgentController::class);
    Route::resource('ticket-agents', TicketAgentController::class);
    Route::resource('fingerprint-charges', FingerprintChargeController::class);
    Route::resource('flight-date-gaps', FlightDateGapController::class);
    Route::resource('routes', RouteController::class);
    Route::resource('ticket-fares', \App\Http\Controllers\TicketFareController::class);
    Route::resource('visa-agent-costs', VisaAgentCostController::class);
    Route::resource('visa-selling-prices', VisaSellingPriceController::class);
    Route::resource('currency-rates', CurrencyRateController::class);
    Route::resource('packages', PackageController::class);
    Route::resource('users', UserController::class);
    Route::resource('transaction-types', TransactionTypeController::class);
    Route::resource('passenger-statuses', PassengerStatusController::class);
    Route::get('/bookings', fn() => view('bookings.index'))->name('booking.index');
    Route::post('/bookings', function () {
        return redirect()->route('booking.index')->with('success', 'Booking created successfully!');
    })->name('booking.store');
    Route::resource('bookings', BookingController::class);
    Route::resource('passengers', PassengerController::class);

    // Booking-specific routes
    Route::post('/bookings/{booking}/passengers', [BookingController::class, 'addPassenger'])->name('bookings.passengers.store');
    Route::delete('/bookings/{booking}/passengers/{passenger}', [BookingController::class, 'removePassenger'])->name('bookings.passengers.destroy');

    // API routes
    Route::post('/api/bookings/calculate-type', [BookingController::class, 'calculatePassengerType'])->name('api.bookings.calculate-type');
    Route::get('/api/bookings/fingerprint-charge', [BookingController::class, 'getFingerprintCharge'])->name('api.bookings.fingerprint-charge');
    Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
    Route::get('/fares/admin', [FareAdminController::class, 'index'])->name('fare.admin');
    Route::post('/fares/admin/agent', [FareAdminController::class, 'storeAgent'])->name('fare.admin.agent.store');
    Route::put('/fares/admin/agent/{ticketAgent}', [FareAdminController::class, 'updateAgent'])->name('fare.admin.agent.update');
    Route::delete('/fares/admin/agent/{ticketAgent}', [FareAdminController::class, 'destroyAgent'])->name('fare.admin.agent.destroy');
    Route::post('/fares/admin/fare', [FareAdminController::class, 'storeFare'])->name('fare.admin.fare.store');
    Route::put('/fares/admin/fare/{ticketFare}', [FareAdminController::class, 'updateFare'])->name('fare.admin.fare.update');
    Route::delete('/fares/admin/fare/{ticketFare}', [FareAdminController::class, 'destroyFare'])->name('fare.admin.fare.destroy');
    Route::get('/visas/admin', [VisaAdminController::class, 'index'])->name('visa.admin');
    Route::get('/fingerprints/admin', fn() => view('fingerprints.admin'))->name('fingerprint.admin');
    Route::get('/fingerprints/staff', fn() => view('fingerprints.staff'))->name('fingerprint.staff');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/flight-date-gap', [SettingsController::class, 'updateFlightDateGap'])->name('settings.flight-date-gap.update');
    Route::put('/settings/fingerprint-charge', [SettingsController::class, 'updateFingerprintCharge'])->name('settings.fingerprint-charge.update');
    Route::put('/settings/package-configuration', [SettingsController::class, 'updatePackageConfiguration'])->name('settings.package-configuration.update');
    Route::post('/settings/package', [SettingsController::class, 'storePackage'])->name('settings.package.store');
    Route::get('/settings/package/{package}', [SettingsController::class, 'showPackage'])->name('settings.package.show');
    Route::put('/settings/package/{package}', [SettingsController::class, 'updatePackage'])->name('settings.package.update');
    Route::delete('/settings/package/{package}', [SettingsController::class, 'destroyPackage'])->name('settings.package.destroy');

    // Reports
    Route::get('/reports/statement', fn() => view('reports.statement'))->name('report.statement');
    Route::get('/reports/profit-loss', fn() => view('reports.profit-loss'))->name('report.profit-loss');
    Route::get('/reports/fingerprint', fn() => view('reports.fingerprint'))->name('report.fingerprint');
    Route::get('/reports/visa', fn() => view('reports.visa'))->name('report.visa');
    Route::get('/reports/visa-agent', fn() => view('reports.visa-agent'))->name('report.visa-agent');
    Route::get('/reports/ticket-agent', fn() => view('reports.ticket-agent'))->name('report.ticket-agent');
    Route::get('/reports/due', fn() => view('reports.due'))->name('report.due');
    Route::get('/reports/reissue-refund', fn() => view('reports.reissue-refund'))->name('report.reissue-refund');
    Route::get('/reports/user-wise-sales', fn() => view('reports.user-wise-sales'))->name('report.user-sales');
    Route::get('/reports/pending-outbound', fn() => view('reports.pending-outbound'))->name('report.pending-ticket');
    Route::get('/reports/payment-receiving', fn() => view('reports.payment-receiving'))->name('report.payment-receiving');
    Route::get('/reports/branch-due-details', fn() => view('reports.branch-due-details'))->name('report.branch-due-details');

    // Detail Pages with parameters
    Route::resource('invoices', InvoiceController::class);
    Route::resource('payments', PaymentController::class);
    Route::resource('vouchers', VoucherController::class);
    Route::get('/invoices/{id}/print', fn($id) => view('invoices.print', compact('id')))->name('invoices.print');
    Route::resource('passengers', PassengerController::class);
    Route::get('/re-issues/{id}/confirm', fn($id) => view('re-issues.confirmation', compact('id')))->name('re-issues.confirmation');
    Route::get('/refunds/{id}/confirm', fn($id) => view('refunds.confirmation', compact('id')))->name('refunds.confirmation');
    Route::get('/tickets', fn() => view('tickets.index'))->name('tickets.index');
    Route::get('/tickets/{id}/print', fn($id) => view('tickets.print', compact('id')))->name('tickets.print');
    Route::get('/tickets/{id}/add-confirm', fn($id) => view('tickets.add-confirmation', compact('id')))->name('tickets.add-confirmation');
});
