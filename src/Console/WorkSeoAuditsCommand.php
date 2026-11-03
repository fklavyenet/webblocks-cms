<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Support\Seo\SiteSeoAuditWorker;

final class WorkSeoAuditsCommand extends Command
{
  protected $signature = 'webblocks:seo:work {--limit=10 : Maximum steps per audit}';

  protected $description = 'Process bounded batches of explicitly requested saved-content SEO audits';

  public function handle(SiteSeoAuditWorker $worker): int
  {
    if (! Schema::hasTable('wbcms_seo_audits')) {
      return self::SUCCESS;
    }
    $deadline = microtime(true) + 40;
    foreach (SeoAudit::query()->whereIn('state', ['queued', 'running'])->orderBy('updated_at')->limit(10)->pluck('id') as $id) {
      if (microtime(true) >= $deadline) {
        break;
      }
      $worker->advance($id, (int) $this->option('limit'), max(1, (int) ($deadline - microtime(true))));
    }

    return self::SUCCESS;
  }
}
