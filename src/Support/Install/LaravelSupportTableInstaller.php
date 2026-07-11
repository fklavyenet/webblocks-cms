<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LaravelSupportTableInstaller
{
  public function ensureRequiredTables(): void
  {
    $this->ensurePasswordResetTokensTable();
    $this->ensureSessionTable();
    $this->ensureCacheTables();
  }

  private function ensurePasswordResetTokensTable(): void
  {
    if (Schema::hasTable('password_reset_tokens')) {
      return;
    }

    Schema::create('password_reset_tokens', function (Blueprint $table): void {
      $table->string('email')->primary();
      $table->string('token');
      $table->timestamp('created_at')->nullable();
    });
  }

  private function ensureSessionTable(): void
  {
    if (config('session.driver') !== 'database') {
      return;
    }

    $table = (string) config('session.table', 'sessions');

    if ($table === '' || Schema::hasTable($table)) {
      return;
    }

    Schema::create($table, function (Blueprint $table): void {
      $table->string('id')->primary();
      $table->foreignId('user_id')->nullable()->index();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->longText('payload');
      $table->integer('last_activity')->index();
    });
  }

  private function ensureCacheTables(): void
  {
    $defaultStore = (string) config('cache.default');

    if ($defaultStore === '') {
      return;
    }

    $store = config("cache.stores.{$defaultStore}");

    if (! is_array($store) || ($store['driver'] ?? null) !== 'database') {
      return;
    }

    $cacheTable = (string) ($store['table'] ?? 'cache');
    $lockTable = (string) ($store['lock_table'] ?? 'cache_locks');

    if ($cacheTable !== '' && ! Schema::hasTable($cacheTable)) {
      Schema::create($cacheTable, function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->bigInteger('expiration')->index();
      });
    }

    if ($lockTable !== '' && ! Schema::hasTable($lockTable)) {
      Schema::create($lockTable, function (Blueprint $table): void {
        $table->string('key')->primary();
        $table->string('owner');
        $table->bigInteger('expiration')->index();
      });
    }
  }
}
