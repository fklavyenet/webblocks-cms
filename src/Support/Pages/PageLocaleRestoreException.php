<?php

namespace WebBlocks\Cms\Support\Pages;

use RuntimeException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class PageLocaleRestoreException extends RuntimeException
{
  public function __construct(public readonly array $issues)
  {
    parent::__construct(app(CmsTranslator::class)->admin('page_revisions.locale_restore.errors.'.($issues[0]['code'] ?? 'incomplete_snapshot'), app(AdminLocaleResolver::class)->locale()));
  }
}
