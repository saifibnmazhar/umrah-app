<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingCancellationActionController;
use App\Http\Controllers\BookingCancellationViewController;

// ─── View Routes (Track B) ───
Route::get('/bookings/{booking}/cancellation/initiate', [BookingCancellationViewController::class, 'initiate'])
    ->name('bookings.cancellation.initiate')->middleware('role:Super Admin,Co Admin');
Route::get('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationViewController::class, 'confirm'])
    ->name('cancelled-bookings.confirm')->middleware('role:Branch Manager');
Route::get('/pending-refunds', [BookingCancellationViewController::class, 'pendingRefunds'])
    ->name('pending-refunds.index')->middleware('role:Super Admin,Co Admin,Branch Manager');
Route::get('/reports/booking-cancellation', [BookingCancellationViewController::class, 'report'])
    ->name('report.booking-cancellation')->middleware('role:Super Admin,Co Admin,Auditor');

// ─── Action Routes (Track A) ───
Route::post('/bookings/{booking}/cancellation/initiate', [BookingCancellationActionController::class, 'store'])
    ->name('bookings.cancellation.store')->middleware('role:Super Admin,Co Admin');
Route::post('/cancelled-bookings/{cancelledBooking}/revert', [BookingCancellationActionController::class, 'revert'])
    ->name('cancelled-bookings.revert')->middleware('role:Branch Manager');
Route::post('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationActionController::class, 'confirmSubmit'])
    ->name('cancelled-bookings.confirm.submit')->middleware('role:Branch Manager');
Route::get('/api/reports/booking-cancellation', [BookingCancellationActionController::class, 'reportData'])
    ->name('report.booking-cancellation.data')->middleware('role:Super Admin,Co Admin,Auditor');
