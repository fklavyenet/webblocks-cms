<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemBootstrapTest extends TestCase
{
    #[Test]
    public function first_request_recreates_required_runtime_directories(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            File::deleteDirectory($directory);
        }

        $this->refreshApplication();

        $response = $this->get('/login');

        $response->assertOk();

        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            $this->assertDirectoryExists($directory);
        }
    }
}
