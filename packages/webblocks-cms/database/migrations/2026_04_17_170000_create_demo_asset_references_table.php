<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('wbcms_demo_asset_references', function (Blueprint $table) {
      $table->id();
      $table->string('source_key')->unique();
      $table->foreignId('asset_id')->constrained('wbcms_assets')->cascadeOnDelete();
      $table->timestamps();
    });

    if (! Schema::hasColumn('wbcms_assets', 'demo_source_key')) {
      return;
    }

    $now = now();

    foreach (DB::table('wbcms_assets')
      ->select('id', 'demo_source_key')
      ->whereNotNull('demo_source_key')
      ->where('demo_source_key', '!=', '')
      ->get() as $asset) {
      DB::table('wbcms_demo_asset_references')->updateOrInsert(
        ['source_key' => $asset->demo_source_key],
        [
          'asset_id' => $asset->id,
          'created_at' => $now,
          'updated_at' => $now,
        ],
      );
    }

    Schema::table('wbcms_assets', function (Blueprint $table) {
      $table->dropUnique(['demo_source_key']);
      $table->dropColumn('demo_source_key');
    });
  }

  public function down(): void
  {
    if (! Schema::hasColumn('wbcms_assets', 'demo_source_key')) {
      Schema::table('wbcms_assets', function (Blueprint $table) {
        $table->string('demo_source_key')->nullable()->after('description');
        $table->unique('demo_source_key');
      });
    }

    $references = DB::table('wbcms_demo_asset_references')->select('source_key', 'asset_id')->get();

    foreach ($references as $reference) {
      DB::table('wbcms_assets')
        ->where('id', $reference->asset_id)
        ->update(['demo_source_key' => $reference->source_key]);
    }

    Schema::dropIfExists('wbcms_demo_asset_references');
  }
};
