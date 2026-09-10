<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_submission_fingerprints')) {
      return;
    }

    Schema::create('wbcms_submission_fingerprints', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->char('exact_hash', 64);
      $table->char('simhash', 16);
      $table->unsignedInteger('occurrences')->default(0);
      $table->unsignedInteger('spam_count')->default(0);
      $table->unsignedInteger('ham_count')->default(0);
      $table->timestamp('last_seen_at');
      $table->timestamps();

      $table->unique(['site_id', 'exact_hash'], 'wbcms_sub_fp_site_exact_uq');
      $table->index(['site_id', 'last_seen_at'], 'wbcms_sub_fp_site_seen_idx');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_submission_fingerprints');
  }
};
