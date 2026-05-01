<?php

namespace Tests\Feature\Console;

use App\Models\Locale;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteDeleteCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function site_delete_command_reports_a_dry_run_summary(): void
    {
        $site = $this->createSecondarySite();

        $this->artisan('site:delete', [
            'site' => $site->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('site resolved: Campaign')
            ->expectsOutputToContain('dry-run: yes')
            ->expectsOutputToContain('Site delete dry-run passed.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    #[Test]
    public function site_delete_command_requires_force_for_real_deletes(): void
    {
        $site = $this->createSecondarySite();

        $this->artisan('site:delete', [
            'site' => $site->id,
        ])
            ->expectsOutputToContain('Deletion requires --force.')
            ->assertExitCode(1);

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    #[Test]
    public function site_delete_command_deletes_when_forced(): void
    {
        $site = $this->createSecondarySite();

        $this->artisan('site:delete', [
            'site' => $site->id,
            '--force' => true,
        ])
            ->expectsOutputToContain('Site deleted successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    #[Test]
    public function site_delete_command_deletes_site_with_page_revisions_when_forced(): void
    {
        $site = $this->createSecondarySite();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Campaign Page',
            'slug' => 'campaign-page',
            'status' => 'published',
        ]);

        $revision = PageRevision::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'label' => 'Imported revision',
            'reason' => 'CLI delete regression',
            'snapshot' => ['page' => ['id' => $page->id, 'site_id' => $site->id]],
        ]);

        $this->artisan('site:delete', [
            'site' => $site->id,
            '--force' => true,
        ])
            ->expectsOutputToContain('Site deleted successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('page_revisions', ['id' => $revision->id]);
    }

    private function createSecondarySite(): Site
    {
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();

        $site = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $site->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        return $site;
    }
}
