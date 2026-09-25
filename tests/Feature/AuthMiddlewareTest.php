<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActive;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    public function test_auth_middleware_is_resolved_and_not_shadowed_by_group(): void
    {
        // Middleware config (aliases/groups) is only applied to the router once routes
        // have been handled, so warm up with a public request before inspecting the stack.
        $this->get('/login');

        $router = app('router');
        $route = $router->getRoutes()->match(Request::create('/dashboard', 'GET'));
        $resolved = $router->gatherRouteMiddleware($route);

        $this->assertContains(Authenticate::class, $resolved);
        $this->assertContains(CheckActive::class, $resolved);
    }

    public function test_guest_is_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
        $this->get('/fingerprint-charges')->assertRedirect(route('login'));
    }
}
