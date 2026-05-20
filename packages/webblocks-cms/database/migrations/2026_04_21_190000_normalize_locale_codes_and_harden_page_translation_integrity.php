<?php

use App\Models\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('locales')
            ->select(['id', 'code'])
            ->orderBy('id')
            ->get()
            ->each(function (object $locale): void {
                DB::table('locales')
                    ->where('id', $locale->id)
                    ->update(['code' => Locale::normalizeCode($locale->code)]);
            });

    }

    public function down(): void
    {
        // Locale-code normalization is intentionally irreversible.
    }
};
