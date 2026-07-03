<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_contact_messages', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->nullable()->constrained('wbcms_blocks')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->string('name');
      $table->string('email');
      $table->string('subject')->nullable();
      $table->longText('message');
      $table->string('status')->default('new');
      $table->text('source_url')->nullable();
      $table->string('ip_address', 45)->nullable();
      $table->text('user_agent')->nullable();
      $table->text('referer')->nullable();
      $table->unsignedSmallInteger('spam_score')->default(0);
      $table->json('spam_reasons')->nullable();
      $table->boolean('notification_enabled')->default(true);
      $table->string('notification_recipient')->nullable();
      $table->string('notification_recipient_source')->nullable();
      $table->string('notification_status')->nullable();
      $table->timestamp('notification_sent_at')->nullable();
      $table->text('notification_error')->nullable();
      $table->text('notification_reason')->nullable();
      $table->timestamps();

      $table->index(['status', 'created_at']);
      $table->index(['spam_score', 'created_at']);
      $table->index('block_id');
      $table->index('page_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_contact_messages');
  }
};
