<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Contact-page starter/showcase upgrades now run through explicit install seeders.
    }

    public function down(): void
    {
        // No-op. Demo/starter content refreshes are intentionally outside core migrations.
    }
};
