<?php

use Illuminate\Support\Facades\Route;

// Dashboard
Route::get('/', fn() => redirect('/dashboard'))->name('home');
Route::get('/dashboard', fn() => view('dashboard.index'))->name('dashboard');

// Main Pages
Route::get('/bookings', fn() => view('bookings.index'))->name('booking.index');
Route::get('/fares/admin', fn() => view('fares.admin'))->name('fare.admin');
Route::get('/visas/admin', fn() => view('visas.admin'))->name('visa.admin');
Route::get('/fingerprints/admin', fn() => view('fingerprints.admin'))->name('fingerprint.admin');
Route::get('/fingerprints/staff', fn() => view('fingerprints.staff'))->name('fingerprint.staff');
Route::get('/settings', fn() => view('settings.index'))->name('settings');

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
Route::get('/invoices/{id}', fn($id) => view('invoices.details', compact('id')))->name('invoices.details');
Route::get('/invoices/{id}/print', fn($id) => view('invoices.print', compact('id')))->name('invoices.print');
Route::get('/passengers/{id}', fn($id) => view('passengers.details', compact('id')))->name('passengers.details');
Route::get('/packages/{id}', fn($id) => view('packages.details', compact('id')))->name('packages.details');
Route::get('/re-issues/{id}/confirm', fn($id) => view('re-issues.confirmation', compact('id')))->name('re-issues.confirmation');
Route::get('/refunds/{id}/confirm', fn($id) => view('refunds.confirmation', compact('id')))->name('refunds.confirmation');
Route::get('/tickets/{id}/add-confirm', fn($id) => view('tickets.add-confirmation', compact('id')))->name('tickets.add-confirmation');