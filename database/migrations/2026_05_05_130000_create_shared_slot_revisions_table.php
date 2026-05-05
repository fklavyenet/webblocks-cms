<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_slot_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_slot_id')->constrained('shared_slots')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_event', 100);
            $table->string('label')->nullable();
            $table->string('summary')->nullable();
            $table->json('snapshot');
            $table->foreignId('restored_from_shared_slot_revision_id')->nullable()->constrained('shared_slot_revisions')->nullOnDelete();
            $table->timestamps();

            $table->index(['shared_slot_id', 'created_at']);
            $table->index(['site_id', 'created_at']);
            $table->index('source_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_slot_revisions');
    }
};
