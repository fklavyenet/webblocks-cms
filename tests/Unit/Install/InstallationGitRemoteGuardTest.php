<?php

namespace Tests\Unit\Install;

use App\Support\Install\InstallationGitRemoteGuard;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstallationGitRemoteGuardTest extends TestCase
{
    private array $temporaryDirectories = [];

    #[Test]
    public function canonical_install_origin_gets_a_disabled_push_url(): void
    {
        $repositoryPath = $this->makeGitRepository('canonical-origin');
        $this->runProcess(['git', 'remote', 'add', 'origin', 'git@github.com:fklavyenet/webblocks-cms.git'], $repositoryPath);

        $output = [];
        $protected = app(InstallationGitRemoteGuard::class)->protectCurrentInstall($repositoryPath, $output);

        $this->assertTrue($protected);
        $this->assertSame('DISABLED', $this->readGitConfig($repositoryPath, 'remote.origin.pushurl'));
        $this->assertContains('Disabled git push for origin while keeping fetch updates enabled.', $output);
    }

    #[Test]
    public function non_canonical_origin_is_left_untouched(): void
    {
        $repositoryPath = $this->makeGitRepository('non-canonical-origin');
        $this->runProcess(['git', 'remote', 'add', 'origin', 'git@github.com:someone/example.git'], $repositoryPath);

        $output = [];
        $protected = app(InstallationGitRemoteGuard::class)->protectCurrentInstall($repositoryPath, $output);

        $this->assertFalse($protected);
        $this->assertNull($this->readGitConfig($repositoryPath, 'remote.origin.pushurl'));
        $this->assertContains('Git push protection skipped: origin does not point at the canonical WebBlocks CMS upstream.', $output);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    private function makeGitRepository(string $prefix): string
    {
        $path = storage_path('app/testing-install-git-guard/'.$prefix.'-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($path);
        $this->temporaryDirectories[] = $path;

        $this->runProcess(['git', 'init'], $path);

        return $path;
    }

    private function readGitConfig(string $repositoryPath, string $key): ?string
    {
        $process = new Process(['git', 'config', '--get', $key], $repositoryPath);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return $output === '' ? null : $output;
    }

    private function runProcess(array $command, string $workingDirectory): void
    {
        $process = new Process($command, $workingDirectory);
        $process->mustRun();
    }
}
