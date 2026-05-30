<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Catalog\CatalogRepairer;

class CatalogRepairCommand extends Command
{
  protected $signature = 'webblocks:catalog-repair
    {--dry-run : Report changes without writing catalog rows}
    {--block-types : Repair shipped block type catalog rows}
    {--slot-types : Repair shipped slot type catalog rows}
    {--page-layouts : Repair shipped page layouts and managed layout slots}
    {--icons : Repair fallback WebBlocks UI icon catalog rows}
    {--all : Repair all supported catalog scopes}';

  protected $description = 'Repair shipped WebBlocks CMS catalog rows without deleting custom catalog entries';

  public function __construct(
    private readonly CatalogRepairer $repairer,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $scopes = $this->scopes();

    if ($scopes === []) {
      $this->warn('No catalog scope selected. Re-run with --dry-run and one or more scopes, or use --all.');
      $this->line('Available scopes: --block-types, --slot-types, --page-layouts, --icons, --all');

      return self::SUCCESS;
    }

    $dryRun = (bool) $this->option('dry-run');
    $summary = $this->repairer->repair($scopes, $dryRun);

    $this->info($dryRun ? 'Catalog repair dry run complete.' : 'Catalog repair complete.');

    foreach ($summary as $scope => $scopeSummary) {
      $this->line($scope.': created '.$scopeSummary['created']
        .', updated '.$scopeSummary['updated']
        .', unchanged '.$scopeSummary['unchanged']
        .', skipped '.$scopeSummary['skipped']);
    }

    $this->line('Custom catalog rows are preserved.');

    return self::SUCCESS;
  }

  private function scopes(): array
  {
    if ($this->option('all')) {
      return ['block-types', 'slot-types', 'page-layouts', 'icons'];
    }

    return collect(['block-types', 'slot-types', 'page-layouts', 'icons'])
      ->filter(fn (string $scope) => (bool) $this->option($scope))
      ->values()
      ->all();
  }
}
