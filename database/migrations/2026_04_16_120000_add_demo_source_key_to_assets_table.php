<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Demo asset source tracking now lives in a dedicated demo-only table.
        // Keep this migration as a no-op for fresh installs so core assets remain neutral.
    }

    public function down(): void
    {
        // No-op. Existing installs are migrated forward by the dedicated demo asset reference migration.
    }
};
