<?php

namespace Tests\Unit;

use Tests\TestCase;

class SessionKeepAliveTest extends TestCase
{
    public function test_the_keep_alive_route_is_declared(): void
    {
        $content = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("'/_session/ping'", $content);
        $this->assertStringContainsString("name('session.ping')", $content);
    }

    public function test_the_frontend_pings_on_an_interval_and_on_wake_up(): void
    {
        $content = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString("'/_session/ping'", $content);
        $this->assertStringContainsString('setInterval(pingSession', $content);

        // Timers stop while the machine sleeps or the tab is discarded, so the
        // wake-up handlers are what actually cover the dangerous case.
        $this->assertStringContainsString('visibilitychange', $content);
        $this->assertStringContainsString('pageshow', $content);
        $this->assertStringContainsString('event.persisted', $content);
        $this->assertStringContainsString("'focus'", $content);
    }

    public function test_the_frontend_writes_the_refreshed_token_back_into_the_page(): void
    {
        $content = file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('X-CSRF-TOKEN', $content);
        $this->assertStringContainsString('meta[name="csrf-token"]', $content);
        $this->assertStringContainsString('input[name="_token"]', $content);
    }

    public function test_the_layout_still_exposes_the_csrf_meta_tag_and_the_bundle(): void
    {
        $content = file_get_contents(base_path('resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString('meta name="csrf-token"', $content);
        $this->assertStringContainsString('resources/js/app.js', $content);
    }

    public function test_no_ajax_call_bakes_the_csrf_token_at_render_time(): void
    {
        // A literal '{{ csrf_token() }}' inside a request header is frozen at
        // render time and can never be refreshed by the keep-alive.
        $matches = [];
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'))
        ) as $file) {
            if ($file->getExtension() !== 'blade.php') {
                continue;
            }

            preg_match_all("/'X-CSRF-TOKEN':\s*'\{\{\s*csrf_token\(\)\s*\}\}'/", file_get_contents($file), $m);
            if ($m[0]) {
                $matches[] = $file->getFilename().': '.implode(', ', $m[0]);
            }
        }

        $this->assertSame([], $matches, 'these must read the meta tag instead: '.implode(' | ', $matches));
    }
}
