<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherTestAccess;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateAuthorizationException;

final class PairPublisherTestCommand extends Command
{
  protected $signature = 'webblocks:updates:pair-test';

  protected $description = 'Pair this CMS installation with an authorized Console test enrollment.';

  public function handle(CmsPublisherTestAccess $access): int
  {
    $code = $this->secret(__('webblocks-cms::admin.update_cli.publisher_test_pairing_code'), false);
    if (! is_string($code) || trim($code) === '') {
      return self::FAILURE;
    }
    try {
      $access->pair(trim($code));
    } catch (UpdateAuthorizationException $exception) {
      $this->error($exception->userMessage());

      return self::FAILURE;
    }
    $this->info(__('webblocks-cms::admin.update_cli.publisher_test_paired'));

    return self::SUCCESS;
  }
}
