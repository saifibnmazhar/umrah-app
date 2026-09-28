<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeployCacheGuardTest extends TestCase
{
    /**
     * Cached config/routes/views are what produced post-deploy 419 CSRF
     * mismatches, so the container boot must not build them. deploy-prod.sh
     * clears all three after a deploy instead.
     */
    public function test_entrypoint_does_not_build_caches(): void
    {
        $content = file_get_contents(base_path('docker/entrypoint.sh'));

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            $this->assertStringNotContainsString(
                $command,
                $content,
                "docker/entrypoint.sh must not run `php artisan {$command}` on boot: it leaves "
                .'deployments serving cached config/routes/views and breaks POSTs with 419'
            );
        }
    }

    /**
     * Every deploy must end with the caches cleared, and must not silently
     * swallow a failure to clear them.
     */
    public function test_deploy_script_clears_all_three_caches(): void
    {
        $content = file_get_contents(base_path('deploy-prod.sh'));

        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            $this->assertMatchesRegularExpression(
                '/php artisan '.preg_quote($command, '/').' --no-interaction/',
                $content,
                "deploy-prod.sh must run `php artisan {$command} --no-interaction` after a deploy"
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/php artisan (config|route|view):clear[^\n]*\|\| true/',
            $content,
            'a failed cache clear must abort the deploy, not be swallowed by `|| true`'
        );
    }

    /**
     * Watchtower restarts a container without ever running a deploy script,
     * so the entrypoint is the only place that can guarantee stale
     * config/routes/views never make it back into a request.
     */
    public function test_entrypoint_clears_all_three_caches(): void
    {
        $content = file_get_contents(base_path('docker/entrypoint.sh'));

        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            $this->assertMatchesRegularExpression(
                '/php artisan '.preg_quote($command, '/').' --no-interaction/',
                $content,
                "docker/entrypoint.sh must run `php artisan {$command} --no-interaction` on boot"
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/php artisan (config|route|view):clear[^\n]*\|\| true/',
            $content,
            'a failed cache clear must fail the boot, not be swallowed by `|| true`'
        );
    }

    /**
     * A CSRF-protected POST is verified after the clears so a broken deploy is
     * reported during the deploy instead of by users hitting ticket/visa forms.
     *
     * The probe lives in the image (one implementation shared by the deploy
     * scripts, a manual `docker exec`, and supervisord), so the request
     * assertions belong to docker/scripts/csrf-probe.sh.
     */
    public function test_deploy_script_probes_csrf_after_clearing_caches(): void
    {
        $content = file_get_contents(base_path('deploy-prod.sh'));
        $probe = file_get_contents(base_path('docker/scripts/csrf-probe.sh'));

        $this->assertStringContainsString(
            'csrf_probe',
            $content,
            'deploy-prod.sh must probe CSRF handling after clearing caches'
        );

        $this->assertStringContainsString(
            '/usr/local/bin/csrf-probe.sh',
            $content,
            'deploy-prod.sh must call the in-image probe instead of keeping its own copy of the logic'
        );

        $this->assertStringContainsString(
            'X-CSRF-TOKEN',
            $probe,
            'the CSRF probe must send the token header'
        );

        $this->assertStringContainsString(
            '419',
            $probe,
            'the CSRF probe must recognise 419 as a CSRF failure'
        );

        $clear = strpos($content, 'php artisan view:clear --no-interaction');
        // Last occurrence: the function definition comes first, the call site later.
        $call = strrpos($content, 'csrf_probe');

        $this->assertNotFalse($clear, 'deploy-prod.sh must clear the view cache');
        $this->assertNotFalse($call, 'deploy-prod.sh must invoke the CSRF probe');
        $this->assertLessThan($call, $clear, 'the CSRF probe must run after the caches are cleared');
    }

    /**
     * The probe must never take a container or a deployment down: it runs from
     * supervisord at every boot and from a deploy script that already
     * succeeded. It has to be shipped in the image and wired into supervisord,
     * otherwise Watchtower restarts get no coverage at all.
     */
    public function test_in_image_probe_is_shipped_and_started_at_boot(): void
    {
        $probe = file_get_contents(base_path('docker/scripts/csrf-probe.sh'));
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $supervisord = file_get_contents(base_path('docker/supervisord.conf'));

        $this->assertStringContainsString(
            'COPY docker/scripts/csrf-probe.sh /usr/local/bin/csrf-probe.sh',
            $dockerfile,
            'the Dockerfile must ship the CSRF probe'
        );

        $this->assertStringContainsString(
            'chmod +x /usr/local/bin/csrf-probe.sh',
            $dockerfile,
            'the shipped CSRF probe must be executable'
        );

        $this->assertStringContainsString(
            '[program:csrf-probe]',
            $supervisord,
            'supervisord must start the CSRF probe on every container start'
        );

        $this->assertMatchesRegularExpression(
            '/\[program:csrf-probe\][^[]*autorestart=false/',
            $supervisord,
            'the probe is a one-shot program: it must not be restarted after it exits'
        );

        $this->assertMatchesRegularExpression(
            '/\[program:csrf-probe\][^[]*startsecs=0/',
            $supervisord,
            'the probe must count as started immediately, without a run window'
        );

        $this->assertStringNotContainsString(
            'exit 1',
            $probe,
            'the probe must always exit 0 so it can never fail a boot or a deploy'
        );

        $this->assertStringContainsString(
            'exit 0',
            $probe,
            'the probe must exit explicitly with success'
        );
    }

    /**
     * The staging deploy must carry the same tripwire and the same forced
     * logout as production, and must not tear the stack down on every deploy.
     */
    public function test_staging_deploy_script_carries_the_guards(): void
    {
        $content = file_get_contents(base_path('deploy-staging.sh'));

        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            $this->assertStringContainsString(
                "php artisan {$command}",
                $content,
                "deploy-staging.sh must run `php artisan {$command}` after a deploy"
            );
        }

        // Staging keeps its own inline probe (deliberate, server-side decision);
        // production calls the shared in-image script. Both must actually send
        // the token and recognise a 419.
        $this->assertStringContainsString(
            'X-CSRF-TOKEN',
            $content,
            'deploy-staging.sh must probe CSRF handling with the token header'
        );

        $this->assertStringContainsString(
            '419',
            $content,
            'deploy-staging.sh must recognise 419 as a CSRF failure'
        );

        $this->assertStringContainsString(
            'sessions:flush --force',
            $content,
            'deploy-staging.sh must force all users to log in again after a successful deploy'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/compose\s+down\b/',
            $content,
            'deploy-staging.sh must `compose stop`, not `compose down`: down also recreates '
            .'the database this deploy did not change'
        );

        $this->assertMatchesRegularExpression(
            '/compose\s+stop\b/',
            $content,
            'deploy-staging.sh must stop the running containers'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/php artisan (config|route|view):clear[^\n]*\|\| true/',
            $content,
            'a failed cache clear must abort the staging deploy, not be swallowed by `|| true`'
        );
    }

    /**
     * A session flush that aborts the deploy would be worse than no flush at
     * all: it must warn and continue on images built before the command existed.
     */
    public function test_staging_flush_is_guarded(): void
    {
        $content = file_get_contents(base_path('deploy-staging.sh'));

        $this->assertMatchesRegularExpression(
            '/if .*FlushSessions\.php; then\n\s*compose exec -T app php artisan sessions:flush --force/',
            $content,
            'deploy-staging.sh must run sessions:flush only when the image ships it'
        );
    }

    /**
     * Every logged-in user must be logged out at the end of a successful deploy.
     * It has to run after migrations so a failed deploy never logs anyone out.
     */
    public function test_deploy_script_flushes_sessions_last(): void
    {
        $content = file_get_contents(base_path('deploy-prod.sh'));

        $this->assertStringContainsString(
            'php artisan sessions:flush --force --no-interaction',
            $content,
            'deploy-prod.sh must force all users to log in again after a successful deploy'
        );

        $this->assertLessThan(
            strpos($content, 'php artisan sessions:flush'),
            strpos($content, 'php artisan migrate --force'),
            'sessions must be flushed after migrations, not before'
        );

        $this->assertLessThan(
            strpos($content, 'php artisan sessions:flush'),
            strpos($content, 'php artisan view:clear --no-interaction'),
            'sessions must be flushed after the caches are cleared, not before'
        );
    }

    /**
     * The session flush must not rely on the cache database: with
     * SESSION_CONNECTION unset, redis sessions live in the "default" connection
     * (REDIS_DB) and not in REDIS_CACHE_DB, so `cache:clear` leaves them intact.
     */
    public function test_deploy_script_does_not_use_cache_clear_for_logout(): void
    {
        $content = file_get_contents(base_path('deploy-prod.sh'));

        // Comments may mention cache:clear to explain why it cannot log users out.
        $commands = preg_replace('/^\s*#.*$/m', '', $content);

        $this->assertStringNotContainsString(
            'cache:clear',
            $commands,
            'cache:clear flushes the cache database, not the session database, so it cannot log users out'
        );
    }

    /**
     * The deploy must stop the app, not tear the stack down: `compose down`
     * also recreates the database this deploy did not change and drops the
     * network for no reason.
     */
    public function test_deploy_script_does_not_teardown_the_stack(): void
    {
        $content = file_get_contents(base_path('deploy-prod.sh'));

        $this->assertMatchesRegularExpression(
            '/compose\s+stop\b/',
            $content,
            'deploy-prod.sh must stop the running containers'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/compose\s+down\b/',
            $content,
            'deploy-prod.sh must not run `compose down`: it recreates the database '
            .'and drops the network for no reason'
        );
    }

    /**
     * The production env sample must reflect the driver production actually runs.
     * There is no sessions table migration in this repo, so `database` would
     * break sessions outright.
     */
    public function test_env_production_sample_matches_redis_sessions(): void
    {
        $content = file_get_contents(base_path('.env.production.sample'));

        $this->assertStringContainsString(
            'SESSION_DRIVER=redis',
            $content,
            'production sessions run on redis; the sample must not claim the database driver'
        );
    }

    /**
     * The staging sample must agree with production: staging runs the same code,
     * and copying a sample that points sessions at a missing table would break
     * login on a fresh staging setup.
     */
    public function test_no_env_sample_points_sessions_at_the_database(): void
    {
        foreach (['.env.production.sample', '.env.staging.sample'] as $sample) {
            $content = file_get_contents(base_path($sample));

            $this->assertStringContainsString(
                'SESSION_DRIVER=redis',
                $content,
                "{$sample} must point sessions at redis"
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^SESSION_DRIVER=database\s*$/m',
                $content,
                "{$sample} must not use the database driver: this repo has no sessions table migration"
            );
        }
    }

    /**
     * Sessions live in redis, so redis has to survive a restart. An ephemeral
     * redis logged every user out on every restart, and a lost session on an
     * in-flight POST surfaces to the user as a 419.
     */
    public function test_compose_redis_persists_sessions(): void
    {
        foreach (['docker-compose.prod.yml', 'docker-compose.staging.yml'] as $file) {
            // Long command scalars may be wrapped by the YAML formatter, so
            // compare against the whitespace-normalised content.
            $content = preg_replace('/\s+/', ' ', file_get_contents(base_path($file)));

            $this->assertStringContainsString(
                '--appendonly yes',
                $content,
                "{$file} must enable redis append-only persistence"
            );

            $this->assertStringContainsString(
                'redis_data:/data',
                $content,
                "{$file} must put redis data on a named volume, not in the container"
            );

            $this->assertMatchesRegularExpression(
                '/^  redis_data:$/m',
                file_get_contents(base_path($file)),
                "{$file} must declare the redis_data volume at the top level"
            );
        }
    }
}
