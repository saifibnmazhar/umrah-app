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
}
