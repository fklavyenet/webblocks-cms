<?php

namespace Tests\Unit\System\Updates;

use App\Support\System\Updates\UpdateCommandRunner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateCommandRunnerTest extends TestCase
{
    #[Test]
    public function cli_php_binary_is_preserved_when_it_is_a_normal_php_binary(): void
    {
        $runner = app(UpdateCommandRunner::class);

        $this->assertSame('/usr/bin/php8.4', $runner->resolveCliPhpBinary('/usr/bin/php8.4'));
    }

    #[Test]
    public function php_fpm_binary_is_rejected_from_cli_resolution(): void
    {
        $runner = app(UpdateCommandRunner::class);

        $this->assertNull($runner->resolveCliPhpBinary('/usr/sbin/php-fpm8.4'));
        $this->assertNull($runner->resolveCliPhpBinary('/opt/homebrew/sbin/php-fpm'));
    }

    #[Test]
    public function artisan_command_uses_php_fallback_when_binary_resolver_rejects_php_binary(): void
    {
        $runner = new class extends UpdateCommandRunner
        {
            public function phpBinary(): string
            {
                return 'php';
            }
        };

        $this->assertSame(
            ['php', 'artisan', 'down', '--render=errors::503'],
            $runner->artisanCommand(['down', '--render=errors::503']),
        );
    }
}
