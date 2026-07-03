<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_visitor_events', function (Blueprint $table) {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('locale_id')->nullable()->constrained('wbcms_locales')->nullOnDelete();
      $table->string('path');
      $table->text('referrer')->nullable();
      $table->string('utm_source')->nullable();
      $table->string('utm_medium')->nullable();
      $table->string('utm_campaign')->nullable();
      $table->string('device_type', 32)->nullable();
      $table->string('browser_family', 64)->nullable();
      $table->string('os_family', 64)->nullable();
      $table->string('session_key', 64);
      $table->string('ip_hash', 64)->nullable();
      $table->timestamp('visited_at');
      $table->timestamps();

      $table->index(['site_id', 'visited_at']);
      $table->index(['page_id', 'visited_at']);
      $table->index(['locale_id', 'visited_at']);
      $table->index(['session_key', 'visited_at']);
      $table->index(['ip_hash', 'visited_at']);
      $table->index(['path', 'visited_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_visitor_events');
  }
};
