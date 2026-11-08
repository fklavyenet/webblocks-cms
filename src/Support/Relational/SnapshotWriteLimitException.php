<?php

namespace WebBlocks\Cms\Support\Relational;

use RuntimeException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

/** Retain the existing restore controllers' runtime-error handling. */
final class SnapshotWriteLimitException extends RuntimeException
{
  public function __construct(public readonly string $reason)
  {
    parent::__construct(app(CmsTranslator::class)->get('storage.snapshot_limit_'.$reason, app(AdminLocaleResolver::class)->locale()));
  }
}
