<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_type_id')->constrained('slot_types')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['page_id', 'sort_order']);
        });

        $this->seedCanonicalSlotTypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('page_slots');
    }

    private function seedCanonicalSlotTypes(): void
    {
        $now = now();

        foreach ([
            ['name' => 'Header', 'slug' => 'header', 'description' => 'Header slot', 'axis' => 'horizontal', 'sort_order' => 1],
            ['name' => 'Main', 'slug' => 'main', 'description' => 'Main slot', 'axis' => 'vertical', 'sort_order' => 2],
            ['name' => 'Sidebar', 'slug' => 'sidebar', 'description' => 'Sidebar slot', 'axis' => 'vertical', 'sort_order' => 3],
            ['name' => 'Footer', 'slug' => 'footer', 'description' => 'Footer slot', 'axis' => 'horizontal', 'sort_order' => 4],
        ] as $slot) {
            DB::table('slot_types')->updateOrInsert(
                ['slug' => $slot['slug']],
                $slot + ['is_system' => true, 'status' => 'published', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::table('slot_types')->whereNotIn('slug', ['header', 'main', 'sidebar', 'footer'])->delete();
    }
};
