<?php

namespace App\Support\Install;

use App\Models\User;
use App\Support\System\InstalledVersionStore;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class Installer
{
    public function __construct(
        private readonly EnvironmentWriter $environmentWriter,
        private readonly InstallState $installState,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function saveDatabaseConfiguration(array $input): void
    {
        $driver = (string) ($input['db_connection'] ?? 'sqlite');

        if (! $this->databaseDriverSupported($driver)) {
            throw new RuntimeException('The selected database driver is not available on this server.');
        }

        $envValues = $this->databaseEnvironmentValues($input);
        $runtimeConfig = $this->runtimeDatabaseConfig($envValues);

        $this->testDatabaseConnection($driver, $runtimeConfig);
        $this->environmentWriter->write($envValues);
        $this->applyDatabaseConfiguration($envValues, $runtimeConfig);
    }

    public function installCore(): array
    {
        $results = [];

        if (! $this->installState->databaseReachable()) {
            return [$this->step('Database readiness', 'failed', 'The configured database connection could not be reached.')];
        }

        if ($this->installState->hasAppKey()) {
            $results[] = $this->step('Application key', 'pass', 'An application key is already configured.');
        } else {
            try {
                $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher', 'AES-256-CBC')));
                $this->environmentWriter->write(['APP_KEY' => $key, 'SESSION_DRIVER' => 'file']);
                $this->applyEnvironmentValue('APP_KEY', $key);
                Config::set('app.key', $key);
                $results[] = $this->step('Application key', 'pass', 'Generated and saved a new application key.');
            } catch (Throwable $throwable) {
                $results[] = $this->step('Application key', 'failed', 'The application key could not be generated: '.$throwable->getMessage());

                return $results;
            }
        }

        if (app()->environment('testing')) {
            $results[] = $this->step('Migrations', 'pass', 'Schema is already prepared for the testing environment.');
        } else {
            try {
                Artisan::call('migrate', ['--force' => true]);
                $results[] = $this->step('Migrations', 'pass', 'All database migrations ran successfully.');
            } catch (Throwable $throwable) {
                $results[] = $this->step('Migrations', 'failed', 'Database migrations failed: '.$throwable->getMessage());

                return $results;
            }
        }

        try {
            config()->set('cms.install.allow_installed_site_seed', true);

            Artisan::call('db:seed', ['--force' => true]);

            config()->set('cms.install.allow_installed_site_seed', false);
            $this->installedVersionStore->persist((string) config('app.version', 'dev'));
            $results[] = $this->step('Core seed', 'pass', 'Core CMS data was installed successfully.');
        } catch (Throwable $throwable) {
            config()->set('cms.install.allow_installed_site_seed', false);
            $results[] = $this->step('Core seed', 'failed', 'Core seed data could not be installed: '.$throwable->getMessage());

            return $results;
        }

        if (! (bool) config('cms.install.storage_link_enabled', ! app()->environment('testing'))) {
            $results[] = $this->step('Storage link', 'pass', 'Storage link was skipped for this environment.');

            return $results;
        }

        if (is_link(public_path('storage')) || is_dir(public_path('storage'))) {
            $results[] = $this->step('Storage link', 'pass', 'The public storage link is already ready.');

            return $results;
        }

        try {
            Artisan::call('storage:link');
            $results[] = $this->step('Storage link', 'pass', 'The public storage link was created.');
        } catch (Throwable $throwable) {
            $results[] = $this->step('Storage link', 'failed', 'The public storage link could not be created: '.$throwable->getMessage());
        }

        return $results;
    }

    public function createFirstSuperAdmin(array $input): User
    {
        if (! $this->installState->coreInstalled()) {
            throw new RuntimeException('Complete the core install step before creating the first admin user.');
        }

        if ($this->installState->firstSuperAdminExists()) {
            throw new RuntimeException('The first super admin has already been created.');
        }

        $user = DB::transaction(function () use ($input): User {
            $user = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'role' => User::ROLE_SUPER_ADMIN,
                'is_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->installState->markInstalled();

            return $user;
        });

        return $user;
    }

    private function databaseEnvironmentValues(array $input): array
    {
        $driver = (string) $input['db_connection'];

        if ($driver === 'sqlite') {
            $database = $this->prepareSqliteDatabase((string) $input['db_database']);

            return [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $database,
                'DB_HOST' => '',
                'DB_PORT' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
                'SESSION_DRIVER' => 'file',
            ];
        }

        return [
            'DB_CONNECTION' => $driver,
            'DB_HOST' => (string) $input['db_host'],
            'DB_PORT' => (string) $input['db_port'],
            'DB_DATABASE' => (string) $input['db_database'],
            'DB_USERNAME' => (string) $input['db_username'],
            'DB_PASSWORD' => (string) ($input['db_password'] ?? ''),
            'SESSION_DRIVER' => 'file',
        ];
    }

    private function runtimeDatabaseConfig(array $envValues): array
    {
        $driver = (string) $envValues['DB_CONNECTION'];
        $config = (array) config("database.connections.{$driver}", []);

        if ($driver === 'sqlite') {
            $config['database'] = $envValues['DB_DATABASE'];

            return $config;
        }

        $config['host'] = $envValues['DB_HOST'];
        $config['port'] = $envValues['DB_PORT'];
        $config['database'] = $envValues['DB_DATABASE'];
        $config['username'] = $envValues['DB_USERNAME'];
        $config['password'] = $envValues['DB_PASSWORD'];

        return $config;
    }

    private function testDatabaseConnection(string $driver, array $config): void
    {
        Config::set('database.connections.__install_probe', $config + ['driver' => $driver]);

        try {
            DB::purge('__install_probe');
            DB::connection('__install_probe')->getPdo();
        } catch (Throwable $throwable) {
            throw new RuntimeException('Database connection failed: '.$throwable->getMessage(), previous: $throwable);
        } finally {
            DB::disconnect('__install_probe');
            Config::offsetUnset('database.connections.__install_probe');
        }
    }

    private function applyDatabaseConfiguration(array $envValues, array $runtimeConfig): void
    {
        foreach ($envValues as $key => $value) {
            $this->applyEnvironmentValue($key, (string) $value);
        }

        $driver = (string) $envValues['DB_CONNECTION'];

        Config::set('database.default', $driver);
        Config::set("database.connections.{$driver}", $runtimeConfig);
        Config::set('session.driver', 'file');

        if (app()->environment('testing')) {
            return;
        }

        DB::purge($driver);
        DB::reconnect($driver);
    }

    private function prepareSqliteDatabase(string $database): string
    {
        $database = trim($database);

        if ($database === '') {
            throw new RuntimeException('The SQLite database path is required.');
        }

        if ($database === ':memory:') {
            return $database;
        }

        $database = str_starts_with($database, DIRECTORY_SEPARATOR)
            ? $database
            : base_path($database);

        $directory = dirname($database);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('The SQLite database directory is not writable.');
        }

        if (! is_file($database) && ! touch($database)) {
            throw new RuntimeException('The SQLite database file could not be created.');
        }

        return $database;
    }

    private function databaseDriverSupported(string $driver): bool
    {
        return match ($driver) {
            'sqlite' => extension_loaded('pdo_sqlite'),
            'mysql', 'mariadb' => extension_loaded('pdo_mysql'),
            'pgsql' => extension_loaded('pdo_pgsql'),
            'sqlsrv' => extension_loaded('sqlsrv') || extension_loaded('pdo_sqlsrv'),
            default => false,
        };
    }

    private function applyEnvironmentValue(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function step(string $label, string $status, string $message): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'badge_class' => $status === 'pass' ? 'wb-status-active' : 'wb-status-danger',
        ];
    }
}
