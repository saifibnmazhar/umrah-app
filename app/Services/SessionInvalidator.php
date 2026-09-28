<?php

namespace App\Services;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Invalidates every active session so all users must log in again.
 *
 * The handler behind the configured session driver is resolved at runtime, and
 * the store that actually holds the sessions is flushed. This matters for the
 * redis driver: it is cache-backed (SessionManager::createRedisDriver()), and
 * with SESSION_CONNECTION unset the store points at the redis "default"
 * connection (REDIS_DB), not the cache connection (REDIS_CACHE_DB). Flushing
 * Cache::flush() / `php artisan cache:clear` would leave the sessions intact.
 */
class SessionInvalidator
{
    /**
     * @return array{driver: string|null, store: string|null, invalidated: int|null}
     */
    public function invalidate(): array
    {
        $handler = app('session')->driver()->getHandler();

        if ($handler instanceof CacheBasedSessionHandler) {
            // redis / memcached / dynamodb / apc sessions live in the cache store
            // that backs the handler, so flushing that store drops them all.
            $store = $handler->getCache()->getStore();
            $store->flush();

            return [
                'driver' => config('session.driver'),
                'store' => $store::class,
                'invalidated' => null,
            ];
        }

        if ($handler instanceof DatabaseSessionHandler) {
            $connection = config('session.connection');
            $table = config('session.table');

            $deleted = DB::connection($connection)->table($table)->delete();

            return [
                'driver' => config('session.driver'),
                'store' => $connection ? $connection.'.'.$table : $table,
                'invalidated' => $deleted,
            ];
        }

        if ($handler instanceof FileSessionHandler) {
            $path = config('session.files');
            $deleted = 0;

            if ($path && File::isDirectory($path)) {
                foreach (File::files($path) as $file) {
                    if ($file->getFilename() === '.gitignore') {
                        continue;
                    }

                    File::delete($file->getPathname());

                    $deleted++;
                }
            }

            return [
                'driver' => config('session.driver'),
                'store' => $path,
                'invalidated' => $deleted,
            ];
        }

        // cookie / array / null drivers keep session data in the request itself,
        // so there is nothing server-side to invalidate.
        return [
            'driver' => config('session.driver'),
            'store' => $handler::class,
            'invalidated' => 0,
        ];
    }
}
