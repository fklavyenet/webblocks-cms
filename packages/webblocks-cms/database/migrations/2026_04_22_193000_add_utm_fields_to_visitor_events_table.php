<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitor_events')) {
            return;
        }

        Schema::table('visitor_events', function (Blueprint $table) {
            if (! Schema::hasColumn('visitor_events', 'utm_source')) {
                $table->string('utm_source')->nullable()->after('referrer');
            }

            if (! Schema::hasColumn('visitor_events', 'utm_medium')) {
                $table->string('utm_medium')->nullable()->after('utm_source');
            }

            if (! Schema::hasColumn('visitor_events', 'utm_campaign')) {
                $table->string('utm_campaign')->nullable()->after('utm_medium');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visitor_events')) {
            return;
        }

        Schema::table('visitor_events', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('visitor_events', 'utm_source') ? 'utm_source' : null,
                Schema::hasColumn('visitor_events', 'utm_medium') ? 'utm_medium' : null,
                Schema::hasColumn('visitor_events', 'utm_campaign') ? 'utm_campaign' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
