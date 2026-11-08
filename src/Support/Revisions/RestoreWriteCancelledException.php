<?php

namespace WebBlocks\Cms\Support\Revisions;

use RuntimeException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class RestoreWriteCancelledException extends RuntimeException
{
  public function __construct()
  {
    parent::__construct(app(CmsTranslator::class)->get('storage.restore_cancelled', app(AdminLocaleResolver::class)->locale()));
  }
}
