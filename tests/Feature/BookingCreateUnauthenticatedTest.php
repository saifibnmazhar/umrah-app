<?php

namespace Tests\Feature;

use Tests\TestCase;

class BookingCreateUnauthenticatedTest extends TestCase
{
    /**
     * When auth()->user() returns null (e.g., session lost behind Cloudflare
     * proxy), the controller should redirect to login instead of crashing
     * with a 500 error on $user->branch.
     */
    public function test_booking_create_redirects_unauthenticated_user(): void
    {
        $response = $this->get(route('bookings.create'));

        // Unauthenticated users should be redirected to login, not 500
        $response->assertRedirect(route('login'));
    }
}
