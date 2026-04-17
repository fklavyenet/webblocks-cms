<?php

namespace Database\Seeders;

use App\Models\PageType;
use Illuminate\Database\Seeder;

class PageTypeSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Home', 'slug' => 'home', 'description' => 'Homepage page type', 'is_system' => true, 'sort_order' => 1, 'status' => 'published'],
            ['name' => 'Page', 'slug' => 'page', 'description' => 'Default content page', 'is_system' => true, 'sort_order' => 2, 'status' => 'published'],
            ['name' => 'Landing', 'slug' => 'landing', 'description' => 'Landing page type', 'is_system' => true, 'sort_order' => 3, 'status' => 'published'],
            ['name' => 'Blog', 'slug' => 'blog', 'description' => 'Blog page type', 'is_system' => true, 'sort_order' => 4, 'status' => 'published'],
            ['name' => 'Archive', 'slug' => 'archive', 'description' => 'Archive page type', 'is_system' => true, 'sort_order' => 5, 'status' => 'published'],
            ['name' => 'System', 'slug' => 'system', 'description' => 'System page type', 'is_system' => true, 'sort_order' => 6, 'status' => 'published'],
        ])->each(fn (array $item) => PageType::query()->updateOrCreate(['slug' => $item['slug']], $item));
    }
}
