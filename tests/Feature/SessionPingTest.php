<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class SessionPingTest extends TestCase
{
    public function test_ping_answers_guests_with_no_content(): void
    {
        // Deliberately public: /login is where most of the historical 419s came
        // from, and a keep-alive that only runs for logged-in users cannot help
        // an abandoned login page.
        $this->get('/_session/ping')->assertStatus(204);
        $this->assertGuest();
    }

    public function test_ping_returns_the_live_session_token(): void
    {
        $response = $this->get('/_session/ping');

        $response->assertStatus(204);
        $this->assertNotEmpty($response->headers->get('X-CSRF-TOKEN'));
        $this->assertSame(
            session()->token(),
            $response->headers->get('X-CSRF-TOKEN'),
            'the ping must return the token the browser should write into the page'
        );
    }

    public function test_ping_sets_a_fresh_session_cookie(): void
    {
        // Both the Redis TTL and the browser cookie expiry are rewritten by any
        // request through the web group - the cookie is how we can see it.
        $this->get('/_session/ping')
            ->assertStatus(204)
            ->assertCookie(config('session.cookie'));
    }

    public function test_ping_is_not_cacheable(): void
    {
        $response = $this->get('/_session/ping');

        $response->assertStatus(204);
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
            'a cached ping would hand back a stale CSRF token'
        );
    }

    public function test_ping_route_is_not_behind_the_auth_middleware(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/_session/ping', 'GET'));

        $this->assertNotContains(
            'auth',
            $route->gatherMiddleware(),
            'the keep-alive must answer unauthenticated requests'
        );
        $this->assertContains('web', $route->gatherMiddleware());
    }
}
