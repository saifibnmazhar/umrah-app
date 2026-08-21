<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_https_is_forced_on_production(): void
    {
        $this->assertTrue(AppServiceProvider::shouldForceHttps('production'));
    }

    public function test_https_is_forced_on_staging(): void
    {
        $this->assertTrue(AppServiceProvider::shouldForceHttps('staging'));
    }

    public function test_https_is_not_forced_on_local(): void
    {
        $this->assertFalse(AppServiceProvider::shouldForceHttps('local'));
    }

    public function test_https_is_not_forced_on_testing(): void
    {
        $this->assertFalse(AppServiceProvider::shouldForceHttps('testing'));
    }

    public function test_forcing_https_generates_https_route_urls(): void
    {
        URL::forceScheme('https');

        $this->assertStringStartsWith('https://', route('ticket-requests.store'));
    }
}
