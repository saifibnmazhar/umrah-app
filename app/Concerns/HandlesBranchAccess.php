<?php

namespace App\Concerns;

use App\Models\Booking;
use App\Models\CancelledBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HandlesBranchAccess
{
    /**
     * Cache of user role names keyed by user ID, so repeated lookups
     * within the same request don't hit the DB.
     */
    protected ?array $userRoleNames = null;
    protected ?int $cachedUserId = null;

    public function userRoleNames(): array
    {
        $user = Auth::user();
        $userId = $user ? $user->id : null;

        if ($this->cachedUserId !== $userId) {
            $this->cachedUserId = $userId;
            $this->userRoleNames = $user ? $user->roles->pluck('name')->toArray() : [];
        }

        return $this->userRoleNames;
    }

    /**
     * True when the user is a Super Admin or Co Admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin']);
    }

    /**
     * True when the user is assigned to a branch AND is not a global admin.
     */
    public function isBranchScoped(): bool
    {
        $user = Auth::user();

        return $user->branch_id && ! $this->isAdmin();
    }

    /**
     * True when the user has no branch and is not a Super Admin / Co Admin.
     */
    public function isGlobalNonAdmin(): bool
    {
        return ! Auth::user()->branch_id && ! $this->isAdmin();
    }

    /**
     * Check if the current user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        if (empty($roles)) {
            return true;
        }

        $userRoleNames = $this->userRoleNames();
        if (empty($userRoleNames)) {
            // Fallback to hasRole() if roles relation hasn't been loaded
            return collect($roles)->contains(fn ($r) => Auth::user()->hasRole($r));
        }

        return count(array_intersect($userRoleNames, $roles)) > 0;
    }

    /**
     * Can the user edit visa submission data?
     * Super Admin, Co Admin, Visa Admin.
     */
    public function canEditVisa(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin', 'Visa Admin']);
    }

    /**
     * Can the user edit fingerprint data?
     * Super Admin, Co Admin, Fingerprint Admin, Delivery Staff.
     */
    public function canEditFingerprint(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin', 'Fingerprint Admin', 'Delivery Staff']);
    }

    /**
     * Can the user delete bookings? Super Admin only.
     */
    public function canDeleteBooking(): bool
    {
        return $this->hasAnyRole(['Super Admin']);
    }

    /**
     * Can the user filter by visa/ticket agent?
     * Super Admin, Co Admin, Visa Admin, Ticket Admin.
     */
    public function canFilterByAgent(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin', 'Visa Admin', 'Ticket Admin']);
    }

    /**
     * Can the user view dashboard summary cards?
     */
    public function canViewSummaryCards(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin', 'Auditor', 'Ticket Admin', 'Visa Admin', 'Branch Manager', 'Fingerprint Admin']);
    }

    /**
     * Can the user view dashboard profit cards?
     */
    public function canViewProfitCards(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Co Admin', 'Auditor']);
    }

    /**
     * Can the user view dashboard ticket requests?
     */
    public function canViewTicketRequests(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Ticket Admin']);
    }

    /**
     * Ensure the current user has access to the given booking.
     * Aborts with 403 if a branch-scoped user accesses a booking
     * from a different branch.
     */
    public function ensureBranchAccess(Booking $booking): void
    {
        $user = Auth::user();

        if ($user->branch_id
            && $user->branch_id !== $booking->booking_branch_id
            && $user->branch_id !== $booking->fingerprint_branch_id) {
            abort(403);
        }
    }

    /**
     * Ensure the current user has access to the given cancelled booking.
     * - Fingerprint Admins without a branch are blocked.
     * - Branch-scoped users can only access cancellations from their branch.
     * - Super Admin / Co Admin are always allowed.
     */
    public function ensureCancellationAccess(CancelledBooking $cancelledBooking): void
    {
        $this->ensureFingerprintAdminHasBranch();

        $user = Auth::user();

        if ($user->branch_id
            && $user->branch_id !== $cancelledBooking->cancellation_branch_id) {
            abort(403);
        }
    }

    /**
     * Prevents Fingerprint Admins without a branch assignment from
     * accessing cancellation features.
     */
    protected function ensureFingerprintAdminHasBranch(): void
    {
        if ($this->hasAnyRole(['Fingerprint Admin'])
            && ! Auth::user()->branch_id) {
            abort(403);
        }
    }

    /**
     * Resolve the effective booking branch for the current user.
     * - If the user has no branch and the request provides one, use the request value.
     * - If the user has a branch, use that.
     * - Otherwise abort with 422.
     */
    public function resolveBookingBranch(Request $request, bool $forUpdate): int
    {
        $user = Auth::user();

        if (! $user->branch_id && $request->filled('booking_branch_id')) {
            return (int) $request->input('booking_branch_id');
        }

        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        abort(422, 'Your account is not assigned to a branch. Contact an administrator.');
    }

    /**
     * Abort if the current user is not a global admin and the edit window
     * (12 hours from booking creation) has expired.
     */
    public function ensureEditWindow(Booking $booking): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if ($booking->created_at->diffInHours(now()) >= 12) {
            abort(403, 'Edit window has expired. Bookings can only be edited within 12 hours of creation.');
        }
    }
}
