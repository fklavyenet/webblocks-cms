<?php

namespace WebBlocks\Cms\Support\NativeLocal;

use Illuminate\Support\Str;

class NativeLocalDoctor
{
  /**
   * @var array<int, string>
   */
  private array $requiredExtensions = [
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
    'session',
    'tokenizer',
    'xml',
    'zip',
  ];

  public function __construct(private readonly NativeLocalProbe $probe) {}

  /**
   * @return array<int, NativeLocalCheckResult>
   */
  public function checks(): array
  {
    $results = [];

    $results[] = $this->checkPhpVersion();
    array_push($results, ...$this->checkPhpExtensions());
    $results[] = $this->checkComposer();
    $results[] = $this->checkDatabaseDriver();
    $results[] = $this->checkDatabaseConnection();
    $results[] = $this->checkRedisExtension();
    $results[] = $this->checkRedisConnection();
    $results[] = $this->checkBinary('nginx', 'Nginx binary', true, 'Install or expose Homebrew Nginx for native HTTPS local serving.');
    $results[] = $this->checkBinary('mkcert', 'mkcert binary', true, 'Install mkcert and run mkcert -install for trusted local TLS.');
    $results[] = $this->checkAppUrlScheme();
    $results[] = $this->checkAppUrlTestDomain();
    $results[] = $this->checkHostsEntry();
    $results[] = $this->checkCertificateFiles();
    $results[] = $this->checkWritablePath(storage_path());
    $results[] = $this->checkWritablePath(base_path('bootstrap/cache'));

    return $results;
  }

  public function summary(array $checks): array
  {
    return [
      'passed' => count(array_filter($checks, fn (NativeLocalCheckResult $check): bool => $check->status === 'pass')),
      'warnings' => count(array_filter($checks, fn (NativeLocalCheckResult $check): bool => $check->status === 'warn')),
      'failed' => count(array_filter($checks, fn (NativeLocalCheckResult $check): bool => $check->status === 'fail')),
      'critical_failed' => count(array_filter($checks, fn (NativeLocalCheckResult $check): bool => $check->status === 'fail' && $check->critical)),
    ];
  }

  public function hasCriticalFailures(array $checks): bool
  {
    return $this->summary($checks)['critical_failed'] > 0;
  }

  private function checkPhpVersion(): NativeLocalCheckResult
  {
    $version = $this->probe->phpVersion();

    if (version_compare($version, '8.3.0', '>=')) {
      return NativeLocalCheckResult::pass('PHP version', 'PHP '.$version.' satisfies the CMS PHP requirement.');
    }

    return NativeLocalCheckResult::fail(
      'PHP version',
      'PHP '.$version.' is below the required 8.3 baseline.',
      'Use a native PHP version compatible with the live server and WebBlocks CMS.'
    );
  }

  /**
   * @return array<int, NativeLocalCheckResult>
   */
  private function checkPhpExtensions(): array
  {
    $loaded = array_map('strtolower', $this->probe->loadedExtensions());
    $results = [];

    foreach ($this->requiredExtensions as $extension) {
      if (in_array(strtolower($extension), $loaded, true)) {
        $results[] = NativeLocalCheckResult::pass('PHP extension '.$extension, 'Extension is loaded.');

        continue;
      }

      $results[] = NativeLocalCheckResult::fail(
        'PHP extension '.$extension,
        'Extension is missing.',
        'Install or enable the extension in the native PHP configuration.'
      );
    }

    return $results;
  }

  private function checkComposer(): NativeLocalCheckResult
  {
    return $this->checkBinary('composer', 'Composer binary', true, 'Install Composer or add it to PATH for native Laravel commands.');
  }

  private function checkDatabaseDriver(): NativeLocalCheckResult
  {
    $driver = (string) config('database.default');

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
      return NativeLocalCheckResult::pass('Database driver', 'Configured driver is '.$driver.'.');
    }

    return NativeLocalCheckResult::warn(
      'Database driver',
      'Configured driver is '.$driver.'; native local target expects MySQL or MariaDB.',
      'Set DB_CONNECTION=mysql for the native .test environment.'
    );
  }

  private function checkDatabaseConnection(): NativeLocalCheckResult
  {
    if ($this->probe->databaseAccessible()) {
      return NativeLocalCheckResult::pass('Database connection', 'Configured database connection is reachable.');
    }

    return NativeLocalCheckResult::fail(
      'Database connection',
      'Configured database connection is not reachable.',
      'Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and local MySQL/MariaDB service state.'
    );
  }

  private function checkRedisExtension(): NativeLocalCheckResult
  {
    $client = (string) config('database.redis.client', 'phpredis');
    $loaded = array_map('strtolower', $this->probe->loadedExtensions());

    if ($client !== 'phpredis') {
      return NativeLocalCheckResult::warn(
        'Redis PHP extension',
        'Redis client is configured as '.$client.'; phpredis extension is not required by current config.',
        'Use the same Redis client strategy as the live server when preparing native local.'
      );
    }

    if (in_array('redis', $loaded, true)) {
      return NativeLocalCheckResult::pass('Redis PHP extension', 'phpredis extension is loaded.');
    }

    return NativeLocalCheckResult::fail(
      'Redis PHP extension',
      'REDIS_CLIENT is phpredis but the redis extension is missing.',
      'Install or enable phpredis for native Redis-backed cache/session checks.'
    );
  }

  private function checkRedisConnection(): NativeLocalCheckResult
  {
    $host = (string) config('database.redis.default.host', '127.0.0.1');
    $port = (int) config('database.redis.default.port', 6379);

    if ($this->probe->redisAccessible($host, $port)) {
      return NativeLocalCheckResult::pass('Redis connection', 'Redis is reachable at configured host and port.');
    }

    return NativeLocalCheckResult::fail(
      'Redis connection',
      'Redis is not reachable at configured host and port.',
      'Start native Redis or adjust REDIS_HOST and REDIS_PORT for the .test environment.'
    );
  }

  private function checkBinary(string $binary, string $label, bool $critical, string $recommendation): NativeLocalCheckResult
  {
    $path = $this->probe->binaryPath($binary);

    if ($path !== null) {
      return NativeLocalCheckResult::pass($label, $binary.' is available on PATH.');
    }

    if ($critical) {
      return NativeLocalCheckResult::fail($label, $binary.' is not available on PATH.', $recommendation);
    }

    return NativeLocalCheckResult::warn($label, $binary.' is not available on PATH.', $recommendation);
  }

  private function checkAppUrlScheme(): NativeLocalCheckResult
  {
    $appUrl = $this->appUrl();

    if (Str::startsWith(Str::lower($appUrl), 'https://')) {
      return NativeLocalCheckResult::pass('APP_URL scheme', 'APP_URL uses HTTPS.');
    }

    return NativeLocalCheckResult::fail(
      'APP_URL scheme',
      'APP_URL must start with https:// for native local development.',
      'Set APP_URL=https://webblocks-cms.test in the native local environment.'
    );
  }

  private function checkAppUrlTestDomain(): NativeLocalCheckResult
  {
    $host = $this->appHost();

    if ($host !== null && Str::endsWith($host, '.test')) {
      return NativeLocalCheckResult::pass('APP_URL domain', 'APP_URL host uses the .test local domain standard.');
    }

    return NativeLocalCheckResult::fail(
      'APP_URL domain',
      'APP_URL host must end with .test for native local development.',
      'Use a .test host such as webblocks-cms.test; do not use .local.'
    );
  }

  private function checkHostsEntry(): NativeLocalCheckResult
  {
    $host = $this->appHost();

    if ($host === null) {
      return NativeLocalCheckResult::fail(
        '/etc/hosts entry',
        'APP_URL host could not be parsed.',
        'Set APP_URL to a valid https://*.test URL.'
      );
    }

    if ($this->probe->hostsFileContains($host)) {
      return NativeLocalCheckResult::pass('/etc/hosts entry', 'APP_URL host is present in /etc/hosts.');
    }

    return NativeLocalCheckResult::fail(
      '/etc/hosts entry',
      'APP_URL host is missing from /etc/hosts.',
      'Add a 127.0.0.1 hosts entry for '.$host.' without removing unrelated existing entries.'
    );
  }

  private function checkCertificateFiles(): NativeLocalCheckResult
  {
    $host = $this->appHost() ?? 'webblocks-cms.test';
    $certificateNames = array_values(array_unique([$host, 'webblocks-cms.test']));
    $pairs = [];

    foreach ($certificateNames as $certificateName) {
      $pairs[] = [
        '/opt/homebrew/etc/nginx/certs/'.$certificateName.'.pem',
        '/opt/homebrew/etc/nginx/certs/'.$certificateName.'-key.pem',
      ];
      $pairs[] = [
        '/usr/local/etc/nginx/certs/'.$certificateName.'.pem',
        '/usr/local/etc/nginx/certs/'.$certificateName.'-key.pem',
      ];
    }

    foreach ($pairs as [$certificate, $key]) {
      if ($this->probe->fileExists($certificate) && $this->probe->fileExists($key)) {
        return NativeLocalCheckResult::pass('Local HTTPS certificate files', 'mkcert certificate and key are present in a documented Nginx cert path.');
      }
    }

    return NativeLocalCheckResult::fail(
      'Local HTTPS certificate files',
      'mkcert certificate and key were not found in the documented Nginx cert paths.',
      'Create webblocks-cms.test.pem and webblocks-cms.test-key.pem under /opt/homebrew/etc/nginx/certs or /usr/local/etc/nginx/certs.'
    );
  }

  private function checkWritablePath(string $path): NativeLocalCheckResult
  {
    if ($this->probe->isWritable($path)) {
      return NativeLocalCheckResult::pass('Writable path '.$path, 'Path is writable by the current PHP process.');
    }

    return NativeLocalCheckResult::fail(
      'Writable path '.$path,
      'Path is not writable by the current PHP process.',
      'Adjust local ownership or permissions without using broad 777 modes.'
    );
  }

  private function appUrl(): string
  {
    return (string) config('app.url', env('APP_URL', ''));
  }

  private function appHost(): ?string
  {
    $host = parse_url($this->appUrl(), PHP_URL_HOST);

    return is_string($host) && $host !== '' ? Str::lower($host) : null;
  }
}
