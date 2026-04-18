<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_releases', function (Blueprint $table) {
            $table->id();
            $table->string('product');
            $table->string('channel');
            $table->string('version');
            $table->string('version_normalized')->nullable();
            $table->string('release_name')->nullable();
            $table->text('description')->nullable();
            $table->longText('changelog')->nullable();
            $table->string('download_url');
            $table->string('checksum_sha256')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_security')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('supported_from_version')->nullable();
            $table->string('supported_until_version')->nullable();
            $table->string('min_php_version')->nullable();
            $table->string('min_laravel_version')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['product', 'channel', 'version']);
            $table->index(['product', 'channel', 'published_at']);
            $table->index(['product', 'channel', 'version_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_releases');
    }
};
