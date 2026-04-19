<?php

namespace Tests\Unit\Release;

use App\Support\Release\ReleaseBuilder;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class ReleaseBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_zip_and_metadata_file(): void
    {
        $output = storage_path('app/test-releases');
        File::deleteDirectory($output);

        $build = app(ReleaseBuilder::class)->build([
            'product' => 'webblocks-cms',
            'version' => '0.0.0-test',
            'channel' => 'stable',
            'notes' => 'Test notes',
            'minimum_supported_version' => '0.0.0',
            'output' => $output,
            'force' => true,
        ]);

        $this->assertFileExists($build['absolute_path']);
        $this->assertFileExists($build['metadata_path']);
        $this->assertSame(64, strlen($build['checksum_sha256']));
    }

    #[Test]
    public function excluded_runtime_and_git_files_are_not_packed(): void
    {
        $output = storage_path('app/test-releases');
        File::deleteDirectory($output);

        $build = app(ReleaseBuilder::class)->build([
            'product' => 'webblocks-cms',
            'version' => '0.0.1-test',
            'channel' => 'stable',
            'notes' => null,
            'minimum_supported_version' => null,
            'output' => $output,
            'force' => true,
        ]);

        $zip = new ZipArchive;
        $zip->open($build['absolute_path']);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }

        $zip->close();

        $this->assertFalse(collect($entries)->contains(fn (string $path): bool => str_starts_with($path, '.git/')));
        $this->assertFalse(collect($entries)->contains(fn (string $path): bool => $path === '.env'));
        $this->assertFalse(collect($entries)->contains(fn (string $path): bool => str_starts_with($path, 'vendor/')));
    }
}
