<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\NavigationItem;
use App\Models\Page;
use Database\Seeders\CoreCatalogSeeder;
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
}
