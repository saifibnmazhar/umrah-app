<?php

namespace Tests\Unit;

use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    private const SAMPLES = ['.env.production.sample', '.env.staging.sample'];

    public function test_both_env_samples_pin_an_idle_session_lifetime(): void
    {
        foreach (self::SAMPLES as $file) {
            $content = file_get_contents(base_path($file));

            $this->assertMatchesRegularExpression(
                '/^SESSION_LIFETIME=720$/m',
                $content,
                $file.' must pin SESSION_LIFETIME=720 so a form left open longer than that does not submit a dead CSRF token'
            );
        }
    }

    public function test_both_env_samples_keep_the_redis_session_driver(): void
    {
        foreach (self::SAMPLES as $file) {
            $content = file_get_contents(base_path($file));

            $this->assertMatchesRegularExpression(
                '/^SESSION_DRIVER=redis$/m',
                $content,
                $file.' must keep sessions on redis'
            );
        }
    }

    public function test_no_env_sample_claims_the_sessions_table_is_missing(): void
    {
        foreach (self::SAMPLES as $file) {
            $content = file_get_contents(base_path($file));

            $this->assertStringNotContainsString(
                'which this app does not have',
                $content,
                "$file is wrong: the sessions table exists in 0001_01_01_000000_create_users_table.php"
            );
        }
    }

    public function test_the_sessions_table_migration_exists(): void
    {
        $content = file_get_contents(
            base_path('database/migrations/0001_01_01_000000_create_users_table.php')
        );

        $this->assertStringContainsString("Schema::create('sessions'", $content);
    }
}
