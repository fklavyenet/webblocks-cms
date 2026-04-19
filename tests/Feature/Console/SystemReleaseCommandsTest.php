<?php

namespace Tests\Feature\Console;

use App\Models\SystemRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemReleaseCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publish_command_creates_release(): void
    {
        $this->artisan('system-release:publish', [
            'version' => '0.2.0',
            'download_url' => 'https://updates.example.test/downloads/webblocks-cms-0.2.0.zip',
            '--description' => 'Stability update',
            '--changelog' => 'Compact changelog text',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('system_releases', [
            'product' => 'webblocks-cms',
            'channel' => 'stable',
            'version' => '0.2.0',
        ]);
    }

    #[Test]
    public function list_command_outputs_releases(): void
    {
        SystemRelease::factory()->create(['version' => '0.2.0']);

        $this->artisan('system-release:list')
            ->expectsOutputToContain('0.2.0')
            ->assertExitCode(0);
    }

    #[Test]
    public function cms_release_dry_run_prints_release_plan(): void
    {
        $this->artisan('cms:release', [
            'version' => '0.0.0-test',
            '--dry-run' => true,
        ])
            ->assertExitCode(0);
    }

    #[Test]
    public function cms_release_build_creates_artifacts(): void
    {
        $output = 'storage/app/test-release-command';
        $absoluteOutput = base_path($output);
        File::deleteDirectory($absoluteOutput);

        $this->artisan('cms:release', [
            'version' => '0.0.2-test',
            '--output' => $output,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($absoluteOutput.'/webblocks-cms-0.0.2-test.zip');
        $this->assertFileExists($absoluteOutput.'/webblocks-cms-0.0.2-test.json');
    }
}
