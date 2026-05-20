<?php

namespace WebBlocks\Cms\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use WebBlocks\Cms\Support\System\InstalledVersionStore;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $allowInstalledSiteSeed = (bool) config('cms.install.allow_installed_site_seed', false);

        if ($this->installedSiteExists() && ! $allowInstalledSiteSeed) {
            throw new RuntimeException('DatabaseSeeder refuses to run on an installed site. Use targeted seeders only when you explicitly intend to modify live catalog data.');
        }

        $this->call([
            FoundationSiteLocaleSeeder::class,
            CoreCatalogSeeder::class,
        ]);

        app(InstalledVersionStore::class)->persist((string) config('app.version', 'dev'));
    }

    private function installedSiteExists(): bool
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasTable('users') || ! Schema::hasTable('sites')) {
            return false;
        }

        $storedVersion = app(InstalledVersionStore::class)->storedVersion();

        return is_string($storedVersion) && $storedVersion !== '';
    }
}
