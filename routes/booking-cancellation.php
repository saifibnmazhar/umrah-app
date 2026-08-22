<?php

use App\Http\Controllers\BookingCancellationActionController;
use App\Http\Controllers\BookingCancellationViewController;
use App\Http\Controllers\PassengerCancellationActionController;
use App\Http\Controllers\PassengerCancellationViewController;
use Illuminate\Support\Facades\Route;

// ─── View Routes (Track B) ───
Route::get('/bookings/{booking}/cancellation/initiate', [BookingCancellationViewController::class, 'initiate'])
    ->name('bookings.cancellation.initiate')->middleware('role:Super Admin,Co Admin');
Route::get('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationViewController::class, 'confirm'])
    ->name('cancelled-bookings.confirm')->middleware('role:Branch Manager,Fingerprint Admin');
Route::get('/pending-refunds', [BookingCancellationViewController::class, 'pendingRefunds'])
    ->name('pending-refunds.index')->middleware('role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin');
Route::get('/reports/booking-cancellation', [BookingCancellationViewController::class, 'report'])
    ->name('report.booking-cancellation')->middleware('role:Super Admin,Co Admin,Auditor');

// ─── Action Routes (Track A) ───
Route::post('/bookings/{booking}/cancellation/initiate', [BookingCancellationActionController::class, 'store'])
    ->name('bookings.cancellation.store')->middleware('role:Super Admin,Co Admin');
Route::post('/cancelled-bookings/{cancelledBooking}/revert', [BookingCancellationActionController::class, 'revert'])
    ->name('cancelled-bookings.revert')->middleware('role:Branch Manager,Fingerprint Admin');
Route::post('/cancelled-bookings/{cancelledBooking}/confirm', [BookingCancellationActionController::class, 'confirmSubmit'])
    ->name('cancelled-bookings.confirm.submit')->middleware('role:Branch Manager,Fingerprint Admin');
Route::put('/api/cancelled-bookings/{cancelledBooking}/refund-amount', [BookingCancellationActionController::class, 'updateRefundAmount'])
    ->name('cancelled-bookings.refund-amount.update')->middleware('role:Branch Manager,Fingerprint Admin');
Route::get('/api/reports/booking-cancellation', [BookingCancellationActionController::class, 'reportData'])
    ->name('report.booking-cancellation.data')->middleware('role:Super Admin,Co Admin,Auditor');

// ─── Passenger Cancellation Routes ───

// View routes
Route::get('/passengers/{passenger}/cancellation/preview', [PassengerCancellationViewController::class, 'preview'])
    ->name('passengers.cancellation.preview')->middleware('role:Super Admin,Co Admin');
Route::get('/cancelled-passengers/{cancelledPassenger}/confirm', [PassengerCancellationViewController::class, 'confirmPage'])
    ->name('cancelled-passengers.confirm')->middleware('role:Branch Manager,Fingerprint Admin');
Route::get('/pending-refunds/passengers', [PassengerCancellationViewController::class, 'passengerIndex'])
    ->name('pending-refunds.passengers')->middleware('role:Super Admin,Co Admin,Branch Manager,Fingerprint Admin');

// Action routes
Route::post('/passengers/{passenger}/cancellation/initiate', [PassengerCancellationActionController::class, 'initiate'])
    ->name('passengers.cancellation.initiate')->middleware('role:Super Admin,Co Admin');
Route::post('/cancelled-passengers/{cancelledPassenger}/revert', [PassengerCancellationActionController::class, 'revert'])
    ->name('cancelled-passengers.revert')->middleware('role:Branch Manager,Fingerprint Admin');
Route::post('/cancelled-passengers/{cancelledPassenger}/confirm', [PassengerCancellationActionController::class, 'confirmSubmit'])
    ->name('cancelled-passengers.confirm.submit')->middleware('role:Branch Manager,Fingerprint Admin');
Route::put('/api/cancelled-passengers/{cancelledPassenger}/refund-amount', [PassengerCancellationActionController::class, 'updateRefundAmount'])
    ->name('cancelled-passengers.refund-amount.update')->middleware('role:Branch Manager,Fingerprint Admin');
