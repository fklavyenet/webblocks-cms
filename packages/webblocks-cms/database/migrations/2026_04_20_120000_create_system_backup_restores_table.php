<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backup_restores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_backup_id')->nullable()->constrained('system_backups')->nullOnDelete();
            $table->string('source_archive_disk')->default('backups');
            $table->string('source_archive_path');
            $table->string('source_archive_filename');
            $table->foreignId('safety_backup_id')->nullable()->constrained('system_backups')->nullOnDelete();
            $table->string('status', 32);
            $table->json('restored_parts')->nullable();
            $table->json('manifest')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('summary')->nullable();
            $table->longText('output')->nullable();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source_backup_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_backup_restores');
    }
};
