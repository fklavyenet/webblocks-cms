<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageStatusCommandTest extends TestCase
{
    #[Test]
    public function package_status_command_is_registered_and_reports_package_bootstrap_state(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('webblocks:package-status')
            ->assertExitCode(0);

        $this->artisan('webblocks:package-status')
            ->expectsOutputToContain('Package: fklavyenet/webblocks-cms')
            ->expectsOutputToContain('Config files present: yes')
            ->expectsOutputToContain('Config file count: 4')
            ->expectsOutputToContain('Config files: cms.php, contact.php, demo_media.php, webblocks-updates.php')
            ->expectsOutputToContain('Routes contain real files: no')
            ->expectsOutputToContain('Views contain real files: no')
            ->expectsOutputToContain('Migrations contain real files: no')
            ->expectsOutputToContain('Root override config present: 4/4 (cms.php, contact.php, demo_media.php, webblocks-updates.php)')
            ->assertExitCode(0);
    }
}
