<?php

namespace Project\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;
use Project\Support\UiDocs\WebBlocksUiLocalResolver;
use Tests\TestCase;

class WebBlocksUiLocalResolverCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function resolver_writer_is_idempotent_and_preserves_unrelated_hosts(): void
    {
        $resolver = app(WebBlocksUiLocalResolver::class);
        $tempPath = sys_get_temp_dir().'/webblocksui-local-resolver-'.bin2hex(random_bytes(8)).'.yaml';

        try {
            file_put_contents($tempPath, implode(PHP_EOL, [
                '# Managed by ddev artisan project:webblocksui-local-resolver',
                'additional_hostnames:',
                '  - foo.example.test',
                '  - ui.docs.webblocksui.com',
                '',
            ]));

            $first = $resolver->ensureAtPath($tempPath);
            $second = $resolver->ensureAtPath($tempPath);

            $this->assertTrue($first['changed']);
            $this->assertFalse($second['changed']);
            $this->assertSame(
                ['foo.example.test', SetupWebBlocksUiDocsSite::canonicalDomain()],
                $resolver->configuredHosts($tempPath),
            );
        } finally {
            @unlink($tempPath);
        }
    }

    #[Test]
    public function resolver_writer_adds_required_host_once_and_preserves_existing_entries(): void
    {
        $resolver = app(WebBlocksUiLocalResolver::class);
        $tempPath = sys_get_temp_dir().'/webblocksui-local-resolver-'.bin2hex(random_bytes(8)).'.yaml';

        try {
            file_put_contents($tempPath, implode(PHP_EOL, [
                'additional_hostnames:',
                '  - docs.example.test',
                '  - foo.example.test',
                '',
            ]));

            $result = $resolver->ensureAtPath($tempPath);

            $this->assertTrue($result['changed']);
            $this->assertTrue($result['restart_required']);
            $this->assertSame(
                ['docs.example.test', 'foo.example.test', SetupWebBlocksUiDocsSite::canonicalDomain()],
                $resolver->configuredHosts($tempPath),
            );

            $resolver->ensureAtPath($tempPath);

            $this->assertSame(
                ['docs.example.test', 'foo.example.test', SetupWebBlocksUiDocsSite::canonicalDomain()],
                $resolver->configuredHosts($tempPath),
            );
        } finally {
            @unlink($tempPath);
        }
    }

    #[Test]
    public function local_resolver_command_reports_ddev_override_and_restart_requirement(): void
    {
        $mock = \Mockery::mock(WebBlocksUiLocalResolver::class);
        $mock->shouldReceive('ensure')->once()->andReturn([
            'changed' => true,
            'restart_required' => true,
            'config_path' => base_path(WebBlocksUiLocalResolver::CONFIG_PATH),
            'hosts' => [SetupWebBlocksUiDocsSite::canonicalDomain()],
        ]);
        $this->instance(WebBlocksUiLocalResolver::class, $mock);

        $command = $this->artisan('project:webblocksui-local-resolver');
        $command->expectsOutput('Local resolver approach: DDEV additional_hostnames override');
        $command->expectsOutput('Resolver config file: '.WebBlocksUiLocalResolver::CONFIG_PATH);
        $command->expectsOutput('Configured local host: '.SetupWebBlocksUiDocsSite::localDdevDomain());
        $command->expectsOutput('Resolver config updated.');
        $command->expectsOutput('Run ddev restart to apply the new local host alias.');
        $command->assertExitCode(0);
    }
}
