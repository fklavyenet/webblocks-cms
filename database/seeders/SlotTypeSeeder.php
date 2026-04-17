<?php

namespace Database\Seeders;

use App\Models\SlotType;
use Illuminate\Database\Seeder;

class SlotTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Header', 'slug' => 'header', 'description' => 'Header slot', 'axis' => 'horizontal', 'is_system' => true, 'sort_order' => 1, 'status' => 'published'],
            ['name' => 'Main', 'slug' => 'main', 'description' => 'Main slot', 'axis' => 'vertical', 'is_system' => true, 'sort_order' => 2, 'status' => 'published'],
            ['name' => 'Sidebar', 'slug' => 'sidebar', 'description' => 'Sidebar slot', 'axis' => 'vertical', 'is_system' => true, 'sort_order' => 3, 'status' => 'published'],
            ['name' => 'Footer', 'slug' => 'footer', 'description' => 'Footer slot', 'axis' => 'horizontal', 'is_system' => true, 'sort_order' => 4, 'status' => 'published'],
        ];

        collect($items)->each(fn (array $item) => SlotType::query()->updateOrCreate(['slug' => $item['slug']], $item));

        SlotType::query()->whereNotIn('slug', collect($items)->pluck('slug'))->delete();
    }
}
