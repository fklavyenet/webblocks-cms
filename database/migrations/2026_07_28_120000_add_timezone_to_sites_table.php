<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site timezone. The install-wide `system.timezone` setting is the fallback and
 * stays authoritative for admin chrome; a site sets this when its own business hours
 * run on a different clock, which a multisite install with sites in more than one
 * region needs before anything time-bound (booking, scheduling) can be correct.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->string('timezone')->nullable()->after('contact_recipient_email');
    });
  }

  public function down(): void
  {
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->dropColumn('timezone');
    });
  }
};
