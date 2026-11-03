<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Support\Jobs\JobCommandBridge;

class PruneEditorRecoveriesCommand extends Command
{
  protected $signature = 'webblocks:editor-recovery:prune';

  protected $description = 'Remove expired editorial recovery checkpoints';

  public function handle(WriteEditorRecovery $write): int
  {
    return JobCommandBridge::run('editor-recovery', fn () => $this->work($write));
  }

  private function work(WriteEditorRecovery $write): int
  {
    $this->info(__('webblocks-cms::recovery.pruned', ['count' => $write->expire()]));

    return self::SUCCESS;
  }
}
