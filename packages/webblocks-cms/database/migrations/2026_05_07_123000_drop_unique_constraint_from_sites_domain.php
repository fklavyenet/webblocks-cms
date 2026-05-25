<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('sites', function (Blueprint $table): void {
      $table->dropUnique('sites_domain_unique');
      $table->index('domain');
    });
  }

  public function down(): void
  {
    Schema::table('sites', function (Blueprint $table): void {
      $table->dropIndex(['domain']);
      $table->unique('domain');
    });
  }
};
