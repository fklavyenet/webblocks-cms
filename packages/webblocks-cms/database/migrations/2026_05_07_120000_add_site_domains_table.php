<?php

use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('redirect_to_primary')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        $normalizer = app(SiteDomainNormalizer::class);
        $now = now();

        DB::table('sites')
            ->select(['id', 'domain'])
            ->whereNotNull('domain')
            ->orderBy('id')
            ->get()
            ->each(function (object $site) use ($normalizer, $now): void {
                $domain = $normalizer->normalize($site->domain);

                if ($domain === null) {
                    return;
                }

                DB::table('site_domains')->updateOrInsert(
                    ['domain' => $domain],
                    [
                        'site_id' => $site->id,
                        'is_primary' => true,
                        'redirect_to_primary' => false,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_domains');
    }
};
