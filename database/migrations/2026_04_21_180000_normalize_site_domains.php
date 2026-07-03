<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;

return new class extends Migration
{
  public function up(): void
  {
    $normalizer = app(SiteDomainNormalizer::class);

    Schema::table('wbcms_sites', function (Blueprint $table) {
      $table->string('domain')->nullable()->change();
    });

    DB::table('wbcms_sites')
      ->select(['id', 'domain'])
      ->orderBy('id')
      ->get()
      ->each(function (object $site) use ($normalizer): void {
        DB::table('wbcms_sites')
          ->where('id', $site->id)
          ->update(['domain' => $normalizer->normalize($site->domain)]);
      });
  }

  public function down(): void
  {
    // Domain normalization is intentionally irreversible.
  }
};
