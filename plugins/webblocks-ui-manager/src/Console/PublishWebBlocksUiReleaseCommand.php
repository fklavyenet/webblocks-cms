<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiPublishRun;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePublisher;

class PublishWebBlocksUiReleaseCommand extends Command
{
  protected $signature = 'webblocks-ui-manager:publish-release
    {version : WebBlocks UI version, for example v2.7.9}
    {--dry-run : Validate and report the local publish plan without writing artifacts}';

  protected $description = 'Validate and publish prepared WebBlocks UI release artifacts into the configured local CDN target';

  public function __construct(
    private readonly WebBlocksUiReleasePublisher $publisher,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $run = (bool) $this->option('dry-run')
      ? $this->publisher->dryRun((string) $this->argument('version'))
      : $this->publisher->publish((string) $this->argument('version'));

    $this->line('Mode: '.$run->mode);
    $this->line('Status: '.$run->status);
    $this->line('Target root: '.$run->target_root);
    $this->line('Target release: '.$run->target_release_path);
    $this->line('Message: '.$run->message);

    foreach ($run->operations ?? [] as $operation) {
      $this->line('- '.$operation['action'].' '.$operation['artifact'].' -> '.$operation['target_path']);
    }

    return $run->status === WebBlocksUiPublishRun::STATUS_SUCCEEDED ? self::SUCCESS : self::FAILURE;
  }
}
