<?php

namespace Tests\Unit;

use Tests\TestCase;

class UploadSizeLimitTest extends TestCase
{
    /**
     * nginx client_max_body_size must be large enough for passenger documents
     * (passport scans, visa copies). The ISPConfig reverse proxy in front
     * also needs the same limit, or it returns 413 before the container.
     */
    public function test_nginx_config_allows_large_uploads(): void
    {
        $content = file_get_contents(base_path('docker/nginx/conf.d/default.conf'));

        $this->assertStringContainsString(
            'client_max_body_size',
            $content,
            'nginx config must define client_max_body_size for passenger document uploads'
        );
    }

    /**
     * PHP upload_max_filesize and post_max_size must be set explicitly.
     * PHP production images default to 2M/8M which is too small for
     * passport scans and visa documents.
     */
    public function test_php_config_allows_large_uploads(): void
    {
        $content = file_get_contents(base_path('docker/php/conf.d/zz-app.ini'));

        $this->assertStringContainsString(
            'upload_max_filesize',
            $content,
            'PHP config must set upload_max_filesize for passenger document uploads'
        );

        $this->assertStringContainsString(
            'post_max_size',
            $content,
            'PHP config must set post_max_size for passenger document uploads'
        );
    }

    /**
     * PHP max_file_uploads must be set high enough for multi-file uploads.
     * The booking form accepts multiple customer docs and passenger docs.
     */
    public function test_php_config_allows_multiple_file_uploads(): void
    {
        $content = file_get_contents(base_path('docker/php/conf.d/zz-app.ini'));

        $this->assertStringContainsString(
            'max_file_uploads',
            $content,
            'PHP config must set max_file_uploads for multi-file booking documents'
        );
    }

    /**
     * PHP upload limits must be >= nginx client_max_body_size so that
     * nginx doesn't accept a request that PHP rejects.
     */
    public function test_php_limits_match_nginx_body_size(): void
    {
        $nginxContent = file_get_contents(base_path('docker/nginx/conf.d/default.conf'));
        $phpContent = file_get_contents(base_path('docker/php/conf.d/zz-app.ini'));

        // Extract nginx client_max_body_size value
        preg_match('/client_max_body_size\s+(\d+)([MKm]?)\s*;/i', $nginxContent, $nginxMatches);
        preg_match('/upload_max_filesize\s*=\s*(\d+)([MKm]?)/i', $phpContent, $phpMatches);

        $this->assertNotEmpty($nginxMatches, 'nginx client_max_body_size not found');
        $this->assertNotEmpty($phpMatches, 'PHP upload_max_filesize not found');

        $nginxBytes = (int) $nginxMatches[1] * ($nginxMatches[2] === 'M' ? 1024 * 1024 : ($nginxMatches[2] === 'K' ? 1024 : 1));
        $phpBytes = (int) $phpMatches[1] * ($phpMatches[2] === 'M' ? 1024 * 1024 : ($phpMatches[2] === 'K' ? 1024 : 1));

        $this->assertGreaterThanOrEqual($nginxBytes, $phpBytes,
            'PHP upload_max_filesize must be >= nginx client_max_body_size to avoid 413/414 mismatches');
    }
}
