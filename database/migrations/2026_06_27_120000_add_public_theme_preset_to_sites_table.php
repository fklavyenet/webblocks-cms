<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->string('public_theme_preset')->nullable()->after('contact_recipient_email');
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->dropColumn('public_theme_preset');
    });
  }
};
