<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_page_revision_candidates')) {
      return;
    }

    Schema::create('wbcms_page_revision_candidates', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('page_revision_id')->constrained('wbcms_page_revisions')->cascadeOnDelete();
      $table->foreignId('candidate_page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('status', 24)->default('ready');
      $table->timestamp('source_updated_at')->nullable();
      $table->timestamp('applied_at')->nullable();
      $table->timestamp('discarded_at')->nullable();
      $table->timestamps();
      $table->index(['page_id', 'status']);
      $table->index(['page_revision_id', 'status']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_page_revision_candidates');
  }
};
