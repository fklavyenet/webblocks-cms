<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->boolean('includes_media')->default(false);
            $table->string('archive_disk')->default('site-transfers');
            $table->string('archive_path')->nullable();
            $table->string('archive_name')->nullable();
            $table->unsignedBigInteger('archive_size_bytes')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('manifest_json')->nullable();
            $table->longText('output_log')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('site_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('source_archive_name')->nullable();
            $table->string('archive_disk')->default('site-transfers');
            $table->string('archive_path')->nullable();
            $table->foreignId('target_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('imported_site_handle')->nullable();
            $table->string('imported_site_domain')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('manifest_json')->nullable();
            $table->longText('output_log')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_imports');
        Schema::dropIfExists('site_exports');
    }
};
