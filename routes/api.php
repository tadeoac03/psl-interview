<?php

use App\Booking\Infrastructure\Http\Controllers\BookingController;
use App\Booking\Infrastructure\Http\Controllers\ClassController;
use App\Booking\Infrastructure\Http\Controllers\MemberBookingController;
use Illuminate\Support\Facades\Route;

/** Browsing the schedule does not depend on who is asking, so it is public. */
Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');

Route::middleware(['member', 'idempotent'])->group(function () {
    Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
    Route::post('/classes/{classId}/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{bookingId}', [BookingController::class, 'destroy'])->name('bookings.destroy');
    Route::get('/members/{memberId}/bookings', [MemberBookingController::class, 'index'])->name('members.bookings.index');
    Route::get('/members/{memberId}/bookings/cancelled', [MemberBookingController::class, 'cancelled'])->name('members.bookings.cancelled');
});
