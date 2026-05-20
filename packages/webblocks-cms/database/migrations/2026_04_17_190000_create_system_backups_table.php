<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->default('manual');
            $table->string('status', 32)->default('running');
            $table->string('label')->nullable();
            $table->boolean('includes_database')->default(true);
            $table->boolean('includes_uploads')->default(true);
            $table->string('archive_disk')->default('backups');
            $table->string('archive_path')->nullable();
            $table->string('archive_filename')->nullable();
            $table->unsignedBigInteger('archive_size_bytes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('summary')->nullable();
            $table->longText('output')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['finished_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_backups');
    }
};
