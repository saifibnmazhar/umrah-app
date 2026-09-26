<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenerateErdCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = sys_get_temp_dir().'/erd-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            File::deleteDirectory($this->outputDir);
        }

        parent::tearDown();
    }

    public function test_command_generates_overview_with_tables_and_relationships(): void
    {
        $this->artisan('erd:generate', ['--output' => $this->outputDir])
            ->assertExitCode(0);

        $overview = $this->outputDir.'/overview.mmd';
        $this->assertFileExists($overview);

        $content = File::get($overview);

        $this->assertStringContainsString('erDiagram', $content);
        $this->assertStringContainsString('bookings {', $content);
        $this->assertStringContainsString('passengers {', $content);

        // passengers.booking_id -> bookings relationship must be present
        $this->assertMatchesRegularExpression(
            '/bookings\s+\|\|--[o|]\{\s+passengers/',
            $content
        );

        // Column-level attributes: primary key and foreign keys are marked
        $this->assertMatchesRegularExpression('/\b(bigint|int|integer)\s+id\s+PK/', $content);
        $this->assertMatchesRegularExpression('/\bbooking_id\s+FK/', $content);

        // Framework/noise tables are excluded
        $this->assertStringNotContainsString('cache {', $content);
        $this->assertStringNotContainsString('jobs {', $content);
        $this->assertStringNotContainsString('password_reset_tokens {', $content);
        $this->assertStringNotContainsString('failed_jobs {', $content);
    }

    public function test_command_generates_all_domain_diagrams(): void
    {
        $this->artisan('erd:generate', ['--output' => $this->outputDir])
            ->assertExitCode(0);

        $domains = [
            'users-auth',
            'booking-core',
            'travel-fares',
            'ticketing',
            'visa',
            'fingerprint',
            'finance',
            'logs',
        ];

        foreach ($domains as $domain) {
            $path = $this->outputDir.'/'.$domain.'.mmd';
            $this->assertFileExists($path, "Missing domain diagram: {$domain}.mmd");

            $content = File::get($path);
            $this->assertStringContainsString('erDiagram', $content);
            $this->assertNotSame('', trim($content));
        }
    }

    public function test_domain_diagrams_only_reference_defined_entities(): void
    {
        $this->artisan('erd:generate', ['--output' => $this->outputDir])
            ->assertExitCode(0);

        foreach (File::files($this->outputDir) as $file) {
            if ($file->getExtension() !== 'mmd') {
                continue;
            }

            $content = File::get($file->getRealPath());
            preg_match_all('/^\s*([a-z0-9_]+)\s+\{/m', $content, $defined);
            preg_match_all('/\|\|--[o|]\{\s+([a-z0-9_]+)/', $content, $related);

            $definedEntities = $defined[1];
            $relatedEntities = $related[1];

            $this->assertNotEmpty($definedEntities, "{$file->getFilename()} has no entities");

            // Every relationship endpoint that appears on the "many" side must be defined
            foreach ($relatedEntities as $entity) {
                $this->assertContains(
                    $entity,
                    $definedEntities,
                    "{$file->getFilename()} references undefined entity: {$entity}"
                );
            }
        }
    }
}
