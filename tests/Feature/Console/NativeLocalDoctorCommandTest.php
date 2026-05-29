<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalProbe;

class NativeLocalDoctorCommandTest extends TestCase
{
  #[Test]
  public function native_local_doctor_is_registered_and_reports_summary_after_all_checks(): void
  {
    $this->bindNativeLocalProbe();
    config(['app.url' => 'https://webblocks-cms.test']);

    $this->artisan('list')
      ->expectsOutputToContain('webblocks:doctor-native-local')
      ->assertExitCode(0);

    $this->artisan('webblocks:doctor-native-local')
      ->expectsOutputToContain('WebBlocks CMS native local doctor')
      ->expectsOutputToContain('[PASS] APP_URL scheme: APP_URL uses HTTPS.')
      ->expectsOutputToContain('[PASS] APP_URL domain: APP_URL host uses the .test local domain standard.')
      ->expectsOutputToContain('Summary')
      ->expectsOutputToContain('Passed:')
      ->expectsOutputToContain('Warnings: 0')
      ->expectsOutputToContain('Failed: 0')
      ->assertExitCode(0);
  }

  #[Test]
  public function native_local_doctor_fails_when_app_url_is_not_https(): void
  {
    $this->bindNativeLocalProbe();
    config(['app.url' => 'http://webblocks-cms.test']);

    $this->artisan('webblocks:doctor-native-local')
      ->expectsOutputToContain('[FAIL] APP_URL scheme: APP_URL must start with https:// for native local development.')
      ->expectsOutputToContain('[PASS] APP_URL domain: APP_URL host uses the .test local domain standard.')
      ->expectsOutputToContain('Summary')
      ->expectsOutputToContain('Failed: 1')
      ->assertExitCode(1);
  }

  #[Test]
  public function native_local_doctor_fails_when_app_url_is_not_test_domain(): void
  {
    $this->bindNativeLocalProbe(hosts: ['webblocks-cms.example']);
    config(['app.url' => 'https://webblocks-cms.example']);

    $this->artisan('webblocks:doctor-native-local')
      ->expectsOutputToContain('[PASS] APP_URL scheme: APP_URL uses HTTPS.')
      ->expectsOutputToContain('[FAIL] APP_URL domain: APP_URL host must end with .test for native local development.')
      ->expectsOutputToContain('Summary')
      ->assertExitCode(1);
  }

  #[Test]
  public function native_local_doctor_output_is_secret_safe(): void
  {
    $this->bindNativeLocalProbe();
    config([
      'app.key' => 'base64:super-secret-app-key',
      'app.url' => 'https://webblocks-cms.test',
      'database.connections.mysql.password' => 'super-secret-db-password',
      'mail.mailers.smtp.password' => 'super-secret-mail-password',
      'services.example.token' => 'super-secret-token',
    ]);

    $this->artisan('webblocks:doctor-native-local')
      ->doesntExpectOutputToContain('super-secret-app-key')
      ->doesntExpectOutputToContain('super-secret-db-password')
      ->doesntExpectOutputToContain('super-secret-mail-password')
      ->doesntExpectOutputToContain('super-secret-token')
      ->expectsOutputToContain('Summary')
      ->assertExitCode(0);
  }

  /**
   * @param  array<int, string>  $hosts
   */
  private function bindNativeLocalProbe(array $hosts = ['webblocks-cms.test']): void
  {
    $this->app->instance(NativeLocalProbe::class, new FakeNativeLocalProbe($hosts));

    config([
      'app.url' => 'https://webblocks-cms.test',
      'database.default' => 'mysql',
      'database.redis.client' => 'phpredis',
      'database.redis.default.host' => '127.0.0.1',
      'database.redis.default.port' => 6379,
    ]);
  }
}

class FakeNativeLocalProbe implements NativeLocalProbe
{
  /**
   * @param  array<int, string>  $hosts
   */
  public function __construct(private readonly array $hosts) {}

  public function phpVersion(): string
  {
    return '8.3.9';
  }

  public function loadedExtensions(): array
  {
    return [
      'bcmath',
      'ctype',
      'curl',
      'dom',
      'fileinfo',
      'filter',
      'hash',
      'intl',
      'json',
      'mbstring',
      'openssl',
      'pdo',
      'pdo_mysql',
      'redis',
      'session',
      'tokenizer',
      'xml',
      'zip',
    ];
  }

  public function binaryPath(string $binary): ?string
  {
    return '/usr/local/bin/'.$binary;
  }

  public function databaseAccessible(): bool
  {
    return true;
  }

  public function databaseFailureMessage(): ?string
  {
    return null;
  }

  public function redisAccessible(string $host, int $port): bool
  {
    return true;
  }

  public function hostsFileContains(string $host): bool
  {
    return in_array($host, $this->hosts, true);
  }

  public function fileExists(string $path): bool
  {
    return str_contains($path, '/certs/webblocks-cms.test');
  }

  public function isWritable(string $path): bool
  {
    return true;
  }
}
