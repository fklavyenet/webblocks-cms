<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_automatic_page_adoptions')) {
    return;
    }
    Schema::create('wbcms_automatic_page_adoptions', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('automatic_page_rule_id')->constrained('wbcms_automatic_page_rules')->restrictOnDelete();
      $table->string('record_key', 190);
      $table->string('slug', 190);
      $table->foreignId('page_id')->unique()->constrained('wbcms_pages')->restrictOnDelete();
      $table->foreignId('page_revision_id')->constrained('wbcms_page_revisions')->restrictOnDelete();
      $table->string('original_status', 24);
      $table->char('retained_hash', 64);
      $table->timestamps();
      $table->unique(['automatic_page_rule_id', 'record_key'], 'wbcms_ap_adoption_key');
      $table->unique(['automatic_page_rule_id', 'slug'], 'wbcms_ap_adoption_slug');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_automatic_page_adoptions');
  }
};
