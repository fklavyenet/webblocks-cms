<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->longText('message');
            $table->string('status')->default('new');
            $table->text('source_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->boolean('notification_enabled')->default(true);
            $table->string('notification_recipient')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->text('notification_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('block_id');
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
