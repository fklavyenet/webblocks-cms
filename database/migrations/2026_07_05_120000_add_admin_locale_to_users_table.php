<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('users') || Schema::hasColumn('users', 'admin_locale')) {
      return;
    }

    $afterColumn = Schema::hasColumn('users', 'last_login_at') ? 'last_login_at' : 'remember_token';

    Schema::table('users', function (Blueprint $table) use ($afterColumn): void {
      $table->string('admin_locale', 12)->nullable()->after($afterColumn);
    });
  }

  public function down(): void
  {
    if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'admin_locale')) {
      return;
    }

    Schema::table('users', function (Blueprint $table): void {
      $table->dropColumn('admin_locale');
    });
  }
};
