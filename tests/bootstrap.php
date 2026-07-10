<?php

/*
|--------------------------------------------------------------------------
| Test Suite Bootstrap
|--------------------------------------------------------------------------
|
| The suite runs against a fresh file-based SQLite database rather than an
| in-memory one. In-memory SQLite is unstable under PHP 8.4 together with
| RefreshDatabase whenever a test exercises destructive database operations
| (backup, restore, migrate:fresh, purge/reconnect): a reconnect to :memory:
| yields a brand-new EMPTY database, which cascades into "no such table" and
| "table already exists" failures across every following test. A shared file
| database keeps the migrated schema across reconnects.
|
| The database file is recreated empty on every run and is git-ignored.
|
*/

$autoloader = require __DIR__.'/../vendor/autoload.php';

$databasePath = __DIR__.'/../database/testing.sqlite';

if (file_exists($databasePath)) {
    @unlink($databasePath);
}

touch($databasePath);

// Canonical, pollution-proof reference to the test database. Some flows (the
// install wizard, for instance) reconfigure the live connection and call
// putenv('DB_DATABASE=...'); tests restore from this constant afterwards.
define('WEBBLOCKS_TEST_DATABASE', $databasePath);

foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $databasePath] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

return $autoloader;
