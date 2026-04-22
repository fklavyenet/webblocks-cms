<?php

namespace Tests\Feature\Console;

use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteCloneCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function site_clone_command_reports_a_dry_run_summary(): void
    {
        $source = Site::query()->create([
            'name' => 'Source',
            'handle' => 'source',
            'domain' => 'source.example.test',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $source->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        Page::query()->create([
            'site_id' => $source->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $this->artisan('site:clone', [
            'source' => $source->id,
            'target' => 'target-preview',
            '--target-handle' => 'target-preview',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('source resolved: Source')
            ->expectsOutputToContain('pages cloned: 1')
            ->expectsOutputToContain('dry-run: yes')
            ->assertExitCode(0);

        $this->assertSame(0, Site::query()->where('handle', 'target-preview')->count());
    }
}
