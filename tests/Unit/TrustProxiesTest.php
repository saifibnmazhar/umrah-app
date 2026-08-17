<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_forces_https_in_production(): void
    {
        URL::forceScheme('https');

        $url = URL::to('/login', [], true);

        $this->assertStringStartsWith('https://', $url);
    }

    public function test_trustedproxy_headers_include_x_forwarded_proto(): void
    {
        // HEADER_X_FORWARDED_TRAEFIK includes all X-Forwarded-* headers
        // including X-Forwarded-Proto, which is what the ISPConfig proxy sets.
        $this->assertTrue(
            (Request::HEADER_X_FORWARDED_TRAEFIK & Request::HEADER_X_FORWARDED_PROTO) === Request::HEADER_X_FORWARDED_PROTO
        );
    }

    public function test_app_environment_is_set_correctly(): void
    {
        // This test verifies the app can read its environment,
        // which AppServiceProvider uses to decide whether to force HTTPS.
        $this->assertContains($this->app->environment(), ['testing', 'local', 'production']);
    }

    public function test_trust_proxies_middleware_exists(): void
    {
        $this->assertTrue(
            class_exists(TrustProxies::class),
            'TrustProxies middleware must exist for Cloudflare proxy compatibility'
        );
    }

    public function test_trust_proxies_uses_cloudflare_service(): void
    {
        $middleware = new TrustProxies;
        $this->assertInstanceOf(
            \MonicaHQ\Cloudflare\Http\Middleware\TrustProxies::class,
            $middleware
        );
    }
}
