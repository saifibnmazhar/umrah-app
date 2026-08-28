<?php

namespace Tests\Unit;

use Tests\TestCase;

class SecureSessionCookieTest extends TestCase
{
    /**
     * The production env sample must declare SESSION_SECURE_COOKIE=true so that
     * the session cookie is marked Secure. Behind the ISPConfig TLS-terminating
     * reverse proxy, a non-Secure session cookie is dropped on https://
     * redirects, causing "the page isn't redirecting properly" loops.
     */
    public function test_env_production_sample_has_secure_session_cookie(): void
    {
        $content = file_get_contents(base_path('.env.production.sample'));

        $this->assertStringContainsString(
            'SESSION_SECURE_COOKIE=true',
            $content,
            'Production .env.production.sample must have SESSION_SECURE_COOKIE=true '
            .'to prevent redirect loops behind TLS-terminating proxies.'
        );
    }
}
