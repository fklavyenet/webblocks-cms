<?php

namespace Tests\Unit\System\Updates;

use App\Support\System\Updates\UpdateException;
use App\Support\System\Updates\UpdatePackageExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class UpdatePackageExtractorTest extends TestCase
{
    private array $temporaryDirectories = [];

    #[Test]
    public function extractor_rejects_archive_entries_with_path_traversal(): void
    {
        $archivePath = $this->makeTemporaryDirectory('archive').'/malicious.zip';
        $destinationPath = $this->makeTemporaryDirectory('extract');

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $archive->addFromString('../evil.php', 'malicious');
        $archive->close();

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Path traversal detected');

        app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
    }

    private function makeTemporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/testing-system-updates/'.$prefix.'-'.Str::uuid());
        File::ensureDirectoryExists($path);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }
}
