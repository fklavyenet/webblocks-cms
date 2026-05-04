<?php

use App\Models\PageSlot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_slots')
            ->select(['id', 'settings'])
            ->orderBy('id')
            ->chunkById(100, function ($slots): void {
                foreach ($slots as $slot) {
                    $decoded = json_decode((string) $slot->settings, true);
                    $sanitized = PageSlot::sanitizeSettings($decoded);

                    if ($decoded === $sanitized) {
                        continue;
                    }

                    DB::table('page_slots')
                        ->where('id', $slot->id)
                        ->update([
                            'settings' => $sanitized === null ? null : json_encode($sanitized, JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
    }
};
