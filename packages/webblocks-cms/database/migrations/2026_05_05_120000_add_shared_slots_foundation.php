<?php

use App\Models\PageSlot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('name');
            $table->string('handle');
            $table->string('slot_name');
            $table->string('public_shell')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'handle']);
            $table->index(['site_id', 'slot_name']);
        });

        Schema::create('shared_slot_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_slot_id')->constrained('shared_slots')->cascadeOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('shared_slot_blocks')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shared_slot_id', 'block_id']);
            $table->index(['shared_slot_id', 'sort_order']);
        });

        Schema::table('page_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('page_slots', 'source_type')) {
                $table->string('source_type')->default(PageSlot::SOURCE_TYPE_PAGE)->after('slot_type_id');
            }

            if (! Schema::hasColumn('page_slots', 'shared_slot_id')) {
                $table->foreignId('shared_slot_id')->nullable()->after('source_type')->constrained('shared_slots')->nullOnDelete();
            }
        });

        DB::table('page_slots')->update([
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
        ]);
    }

    public function down(): void
    {
        Schema::table('page_slots', function (Blueprint $table) {
            if (Schema::hasColumn('page_slots', 'shared_slot_id')) {
                $table->dropConstrainedForeignId('shared_slot_id');
            }

            if (Schema::hasColumn('page_slots', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });

        Schema::dropIfExists('shared_slot_blocks');
        Schema::dropIfExists('shared_slots');
    }
};
