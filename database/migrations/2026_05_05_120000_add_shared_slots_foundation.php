<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\PageSlot;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_shared_slots', function (Blueprint $table) {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('name');
      $table->string('handle');
      $table->string('slot_name');
      $table->string('public_shell')->nullable();
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      $table->unique(['site_id', 'handle']);
      $table->index(['site_id', 'slot_name']);
    });

    Schema::create('wbcms_shared_slot_blocks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('shared_slot_id')->constrained('wbcms_shared_slots')->cascadeOnDelete();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_shared_slot_blocks')->nullOnDelete();
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();

      $table->unique(['shared_slot_id', 'block_id']);
      $table->index(['shared_slot_id', 'sort_order']);
    });

    Schema::table('wbcms_page_slots', function (Blueprint $table) {
      if (! Schema::hasColumn('wbcms_page_slots', 'source_type')) {
        $table->string('source_type')->default(PageSlot::SOURCE_TYPE_PAGE)->after('slot_type_id');
      }

      if (! Schema::hasColumn('wbcms_page_slots', 'shared_slot_id')) {
        $table->foreignId('shared_slot_id')->nullable()->after('source_type')->constrained('wbcms_shared_slots')->nullOnDelete();
      }
    });

    DB::table('wbcms_page_slots')->update([
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
    ]);
  }

  public function down(): void
  {
    Schema::table('wbcms_page_slots', function (Blueprint $table) {
      if (Schema::hasColumn('wbcms_page_slots', 'shared_slot_id')) {
        $table->dropConstrainedForeignId('shared_slot_id');
      }

      if (Schema::hasColumn('wbcms_page_slots', 'source_type')) {
        $table->dropColumn('source_type');
      }
    });

    Schema::dropIfExists('wbcms_shared_slot_blocks');
    Schema::dropIfExists('wbcms_shared_slots');
  }
};
