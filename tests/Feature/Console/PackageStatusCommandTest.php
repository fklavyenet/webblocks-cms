<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageStatusCommandTest extends TestCase
{
    #[Test]
    public function package_status_command_is_registered_and_reports_package_resource_boundary_state_read_only(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('webblocks:package-status')
            ->assertExitCode(0);

        $this->artisan('webblocks:package-status')
            ->expectsOutputToContain('Package: fklavyenet/webblocks-cms')
            ->expectsOutputToContain('Mode: read-only diagnostic only')
            ->expectsOutputToContain('Package resource boundary status')
            ->expectsOutputToContain('Package base path:')
            ->expectsOutputToContain('Package src path present: yes')
            ->expectsOutputToContain('Package config path present: yes')
            ->expectsOutputToContain('Package config files present: cms.php, contact.php, demo_media.php, webblocks-updates.php')
            ->expectsOutputToContain('Package routes path present: yes')
            ->expectsOutputToContain('Package resources/views path present: yes')
            ->expectsOutputToContain('Package database/migrations path present: yes')
            ->expectsOutputToContain('Package public path present: yes')
            ->expectsOutputToContain('Package stubs path present: yes')
            ->expectsOutputToContain('Package service provider loaded: yes')
            ->expectsOutputToContain('Root override config present: 4/4 (cms.php, contact.php, demo_media.php, webblocks-updates.php)')
            ->expectsOutputToContain('Transition note: root runtime remains authoritative unless a resource has been intentionally moved and wired.')
            ->expectsOutputToContain('This command does not publish files, run migrations, clear cache, or mutate install state.')
            ->assertExitCode(0);
    }
}
