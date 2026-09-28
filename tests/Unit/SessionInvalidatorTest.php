<?php

namespace Tests\Unit;

use App\Services\SessionInvalidator;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionInvalidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FlushSpyStore::$flushes = 0;
    }

    public function test_cache_backed_session_driver_flushes_the_session_store(): void
    {
        Cache::extend('flush-spy', fn () => new Repository(new FlushSpyStore));

        config(['cache.stores.flush-spy' => ['driver' => 'flush-spy']]);

        // The redis session driver is cache-backed and honours session.store,
        // so pointing it at the spy store exercises the real production path.
        config([
            'session.driver' => 'redis',
            'session.store' => 'flush-spy',
            'session.connection' => null,
        ]);

        $result = (new SessionInvalidator)->invalidate();

        $this->assertSame('redis', $result['driver']);
        $this->assertSame(FlushSpyStore::class, $result['store']);
        $this->assertSame(1, FlushSpyStore::$flushes, 'the session store must be flushed exactly once');
    }

    public function test_database_session_driver_deletes_stored_sessions(): void
    {
        config([
            'session.driver' => 'database',
            'session.connection' => null,
            'session.table' => 'sessions',
        ]);

        Schema::dropIfExists('sessions');
        Schema::create('sessions', function ($table) {
            $table->string('id')->primary();
            $table->text('payload');
        });

        try {
            DB::table('sessions')->insert([
                ['id' => 'one', 'payload' => '_token|abc'],
                ['id' => 'two', 'payload' => '_token|def'],
            ]);

            $result = (new SessionInvalidator)->invalidate();

            $this->assertSame('database', $result['driver']);
            $this->assertSame(2, $result['invalidated']);
            $this->assertSame(0, DB::table('sessions')->count());
        } finally {
            Schema::dropIfExists('sessions');
        }
    }

    public function test_file_session_driver_removes_session_files_but_keeps_gitignore(): void
    {
        $path = storage_path('framework/testing-sessions');

        File::ensureDirectoryExists($path);
        File::put($path.'/sess_one', 'payload');
        File::put($path.'/sess_two', 'payload');
        File::put($path.'/.gitignore', '*');

        config([
            'session.driver' => 'file',
            'session.files' => $path,
        ]);

        try {
            $result = (new SessionInvalidator)->invalidate();

            $this->assertSame('file', $result['driver']);
            $this->assertSame(2, $result['invalidated']);
            $this->assertFileDoesNotExist($path.'/sess_one');
            $this->assertFileDoesNotExist($path.'/sess_two');
            $this->assertFileExists($path.'/.gitignore');
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_non_persistent_driver_is_a_no_op(): void
    {
        config(['session.driver' => 'array']);

        $result = (new SessionInvalidator)->invalidate();

        $this->assertSame('array', $result['driver']);
        $this->assertSame(0, $result['invalidated']);
    }
}

class FlushSpyStore extends ArrayStore
{
    public static int $flushes = 0;

    public function flush(): bool
    {
        static::$flushes++;

        return parent::flush();
    }

    /**
     * SessionManager::createRedisDriver() calls setConnection() on the store
     * that backs the handler, so the spy has to accept it.
     */
    public function setConnection($connection)
    {
        return $this;
    }

    public function setLockConnection($connection)
    {
        return $this;
    }
}
