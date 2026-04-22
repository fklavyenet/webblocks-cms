<?php

namespace App\Console\Commands;

use App\Support\Sites\SiteDeleteService;
use Illuminate\Console\Command;
use RuntimeException;

class SiteDeleteCommand extends Command
{
    protected $signature = 'site:delete
        {site : Site id, handle, name, or domain}
        {--force : Execute the delete instead of only validating}
        {--dry-run : Validate and summarize without deleting}';

    protected $description = 'Delete a non-primary site and its site-scoped content safely';

    public function __construct(
        private readonly SiteDeleteService $siteDeleteService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $report = $this->option('dry-run')
                ? $this->siteDeleteService->inspect($this->argument('site'))
                : $this->runDelete();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('site resolved: '.$report->site->name.' (#'.$report->site->id.')');
        $this->line('pages: '.$report->count('pages'));
        $this->line('page translations: '.$report->count('page_translations'));
        $this->line('page slots: '.$report->count('page_slots'));
        $this->line('blocks: '.$report->count('blocks'));
        $this->line('block translation rows: '.$report->count('block_translation_rows'));
        $this->line('navigation items: '.$report->count('navigation_items'));
        $this->line('locale assignments: '.$report->count('site_locales'));
        $this->line('contact messages blocking delete: '.$report->count('contact_messages'));
        $this->line('dry-run: '.($this->option('dry-run') ? 'yes' : 'no'));

        if ($report->hasBlockers()) {
            foreach ($report->blockers as $blocker) {
                $this->error($blocker);
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Site delete dry-run passed.');

            return self::SUCCESS;
        }

        $this->info('Site deleted successfully.');

        return self::SUCCESS;
    }

    private function runDelete()
    {
        if (! $this->option('force')) {
            throw new RuntimeException('Deletion requires --force. Use --dry-run to inspect first.');
        }

        return $this->siteDeleteService->delete($this->argument('site'));
    }
}
