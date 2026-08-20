<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTicketRequestAccess
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $booking = $this->resolveBooking($request);

        if (! $booking) {
            abort(403, 'Unauthorized action.');
        }

        $hasRole = $user->roles()->whereIn('name', $roles)->exists();
        $isBookingBranchUser = $user->branch_id && $user->branch_id === $booking->booking_branch_id;

        if (! $hasRole && ! $isBookingBranchUser) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }

    private function resolveBooking(Request $request): ?Booking
    {
        if (($booking = $request->route('booking')) instanceof Booking) {
            return $booking;
        }

        $bookingId = $request->route('id') ?? $request->input('booking_id');

        return $bookingId ? Booking::find($bookingId) : null;
    }
}
