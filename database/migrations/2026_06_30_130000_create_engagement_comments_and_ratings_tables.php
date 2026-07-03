<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_comment_entries', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('block_id')->nullable()->constrained('wbcms_blocks')->nullOnDelete();
      $table->string('author_name')->nullable();
      $table->longText('body');
      $table->string('status')->default('pending');
      $table->text('source_url')->nullable();
      $table->string('visitor_hash', 64)->nullable();
      $table->string('ip_hash', 64)->nullable();
      $table->text('user_agent')->nullable();
      $table->unsignedSmallInteger('spam_score')->default(0);
      $table->json('spam_reasons')->nullable();
      $table->timestamp('approved_at')->nullable();
      $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->index(['status', 'created_at']);
      $table->index(['site_id', 'created_at']);
      $table->index(['page_id', 'created_at']);
      $table->index(['block_id', 'status', 'created_at']);
      $table->index(['visitor_hash', 'created_at']);
      $table->index(['ip_hash', 'created_at']);
      $table->index(['spam_score', 'created_at']);
    });

    Schema::create('wbcms_content_ratings', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('block_id')->nullable()->constrained('wbcms_blocks')->nullOnDelete();
      $table->unsignedTinyInteger('rating_value');
      $table->unsignedTinyInteger('rating_max')->default(5);
      $table->string('status')->default('active');
      $table->text('source_url')->nullable();
      $table->string('visitor_hash', 64)->nullable();
      $table->string('ip_hash', 64)->nullable();
      $table->text('user_agent')->nullable();
      $table->timestamps();

      $table->unique(['block_id', 'visitor_hash'], 'content_ratings_block_visitor_unique');
      $table->index(['status', 'created_at']);
      $table->index(['site_id', 'created_at']);
      $table->index(['page_id', 'created_at']);
      $table->index(['block_id', 'status', 'rating_value']);
      $table->index(['ip_hash', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_content_ratings');
    Schema::dropIfExists('wbcms_comment_entries');
  }
};
