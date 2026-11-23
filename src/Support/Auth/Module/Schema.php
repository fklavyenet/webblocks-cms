<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as LaravelSchema;

final class Schema
{
  public function __construct(private readonly Storage $storage) {}

  public function migrate(): void
  {
    if (! LaravelSchema::hasTable($this->storage->tableName('credentials'))) {
      LaravelSchema::create($this->storage->tableName('credentials'), function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained($this->storage->userTable, $this->storage->userKey)->cascadeOnDelete();
        $table->string('email');
        $table->uuid('generation');
        $table->timestamp('confirmed_at')->nullable();
      });
    }
    if (! LaravelSchema::hasTable($this->storage->tableName('enrollments'))) {
      LaravelSchema::create($this->storage->tableName('enrollments'), function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained($this->storage->userTable, $this->storage->userKey)->cascadeOnDelete();
        $table->string('email');
        $table->uuid('generation');
        $table->timestamp('expires_at');
      });
    }
    if (! LaravelSchema::hasTable($this->storage->tableName('email_codes'))) {
      LaravelSchema::create($this->storage->tableName('email_codes'), function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignId('user_id')->constrained($this->storage->userTable, $this->storage->userKey)->cascadeOnDelete();
        $table->string('purpose', 16);
        $table->string('email');
        $table->char('binding', 64);
        $table->char('code_hash', 64);
        $table->unsignedTinyInteger('attempts')->default(0);
        $table->timestamp('sent_at');
        $table->timestamp('expires_at');
        $table->timestamp('consumed_at')->nullable();
        $table->index(['user_id', 'sent_at']);
      });
    }
    if (! LaravelSchema::hasTable($this->storage->tableName('recovery_codes'))) {
      LaravelSchema::create($this->storage->tableName('recovery_codes'), function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id')->constrained($this->storage->tableName('credentials'), 'user_id')->cascadeOnDelete();
        $table->char('code_hash', 64);
        $table->unique(['user_id', 'code_hash'], $this->storage->tableName('code_unique'));
      });
    }
    if (! LaravelSchema::hasTable($this->storage->tableName('account_states'))) {
      LaravelSchema::create($this->storage->tableName('account_states'), function (Blueprint $table): void {
        $table->foreignId('user_id')->primary()->constrained($this->storage->userTable, $this->storage->userKey)->cascadeOnDelete();
        $table->uuid('session_version');
        $table->boolean('enrollment_required')->default(true);
        $table->timestamp('reset_at');
      });
    }
  }
}
