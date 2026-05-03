<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Support\System\InstalledVersionStore;
use Database\Seeders\CoreCatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ShowcaseInstallSeeder;
use Database\Seeders\StarterInstallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InstallSeedersSafetyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function starter_install_seeder_refuses_to_run_on_an_initialized_site(): void
    {
        $this->seed(CoreCatalogSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('StarterInstallSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks');

        $this->seed(StarterInstallSeeder::class);
    }

    #[Test]
    public function showcase_install_seeder_refuses_to_run_on_an_initialized_site(): void
    {
        $this->seed(CoreCatalogSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ShowcaseInstallSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks');

        $this->seed(ShowcaseInstallSeeder::class);
    }

    #[Test]
    public function database_seeder_refuses_to_run_on_an_installed_site(): void
    {
        $this->seed(CoreCatalogSeeder::class);

        app(InstalledVersionStore::class)->persist('1.7.0');

        Page::query()->create([
            'site_id' => 1,
            'title' => 'Live page',
            'slug' => 'live-page',
            'status' => 'published',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DatabaseSeeder refuses to run on an installed site');

        $this->seed(DatabaseSeeder::class);
    }
}
