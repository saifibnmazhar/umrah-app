<?php

namespace Tests\Unit;

use App\Console\Commands\GenerateErd;
use Tests\TestCase;

class GenerateErdAbsolutePathTest extends TestCase
{
    /**
     * `erd:generate` re-roots anything that is not absolute at base_path().
     * Getting that predicate wrong on Windows does not fail on Linux CI, so
     * both platforms have to be asserted here explicitly.
     */
    public function test_absolute_paths_are_left_untouched(): void
    {
        $absolute = [
            '/tmp/erd-out',                                   // POSIX root
            'C:\\Users\\me\\AppData\\Local\\Temp\\erd-out',   // Windows, backslashes
            'C:/Users/me/AppData/Local/Temp/erd-out',         // Windows, forward slashes
            'D:\\deploy\\erd-out',                            // any drive letter
            '\\\\server\\share\\erd-out',                     // UNC
        ];

        foreach ($absolute as $path) {
            $this->assertTrue(
                GenerateErd::isAbsolutePath($path),
                "Expected [{$path}] to be treated as an absolute path"
            );
        }
    }

    public function test_relative_paths_are_prefixed_with_the_base_path(): void
    {
        $relative = [
            'docs/erd',
            './docs/erd',
            'erd-out',
            '',
        ];

        foreach ($relative as $path) {
            $this->assertFalse(
                GenerateErd::isAbsolutePath($path),
                "Expected [{$path}] to be treated as a relative path"
            );
        }
    }
}
