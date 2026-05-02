<?php

namespace Database\Seeders;

use App\Models\LayoutType;
use Illuminate\Database\Seeder;

class LayoutTypeSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Default', 'slug' => 'default', 'description' => 'Default layout type', 'category' => 'content', 'is_system' => true, 'sort_order' => 1, 'status' => 'published'],
            ['name' => 'Landing', 'slug' => 'landing', 'description' => 'Landing layout type', 'category' => 'marketing', 'is_system' => true, 'sort_order' => 2, 'status' => 'published'],
            ['name' => 'Sidebar Left', 'slug' => 'sidebar-left', 'description' => 'Sidebar left layout type', 'category' => 'content', 'is_system' => true, 'sort_order' => 3, 'status' => 'published'],
            ['name' => 'Sidebar Right', 'slug' => 'sidebar-right', 'description' => 'Sidebar right layout type', 'category' => 'content', 'is_system' => true, 'sort_order' => 4, 'status' => 'published'],
            ['name' => 'Full Width', 'slug' => 'full-width', 'description' => 'Full width layout type', 'category' => 'content', 'is_system' => true, 'sort_order' => 5, 'status' => 'published'],
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'description' => 'Dashboard layout type', 'category' => 'admin', 'is_system' => true, 'sort_order' => 6, 'status' => 'published'],
            ['name' => 'System', 'slug' => 'system', 'description' => 'System layout type', 'category' => 'system', 'is_system' => true, 'sort_order' => 7, 'status' => 'published'],
        ])->each(fn (array $item) => LayoutType::query()->updateOrCreate(['slug' => $item['slug']], $item));
    }
}
