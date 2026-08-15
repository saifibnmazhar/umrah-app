<?php

namespace Tests\Unit;

use App\Traits\ConvertsDocumentsToPdf;
use Tests\TestCase;

class ConvertsDocumentsToPdfTest extends TestCase
{
    use ConvertsDocumentsToPdf;

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanUpTestDir();
    }

    private function cleanUpTestDir(): void
    {
        $tmpDir = storage_path('app/tmp/test_convert');
        if (is_dir($tmpDir)) {
            $this->removeDirectory($tmpDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function createTestJpg(): string
    {
        $path = storage_path('app/tmp/test_convert/test_image.jpg');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $image = imagecreate(10, 10);
        $bg = imagecolorallocate($image, 255, 255, 255);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 10, 10, $bg);
        imagejpeg($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * convertToPdf must create the output directory if it doesn't exist.
     * This prevents "Failed to open stream: No such file or directory" errors
     * in production where the tmp directory may not be pre-created.
     */
    public function test_convert_to_pdf_creates_missing_output_directory(): void
    {
        $sourceImage = $this->createTestJpg();

        $nestedDir = storage_path('app/tmp/test_convert/nested/deep');
        $outputPath = $nestedDir.'/output.pdf';

        $this->assertDirectoryDoesNotExist($nestedDir);

        $result = $this->convertToPdf($sourceImage, $outputPath);
        $this->assertEquals($outputPath, $result);
        $this->assertFileExists($outputPath);
        $this->assertDirectoryExists($nestedDir);
    }

    /**
     * convertToPdf should still work when the output directory already exists.
     */
    public function test_convert_to_pdf_works_with_existing_directory(): void
    {
        $sourceImage = $this->createTestJpg();
        $outputPath = storage_path('app/tmp/test_convert/existing.pdf');

        $result = $this->convertToPdf($sourceImage, $outputPath);
        $this->assertEquals($outputPath, $result);
        $this->assertFileExists($outputPath);
    }
}
