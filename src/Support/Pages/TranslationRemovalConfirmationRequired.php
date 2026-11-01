<?php

namespace WebBlocks\Cms\Support\Pages;

use RuntimeException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class TranslationRemovalConfirmationRequired extends RuntimeException
{
  public function __construct(public readonly array $translations)
  {
    parent::__construct(app(CmsTranslator::class)->admin(
      'page_revisions.translation_removal_required',
      app(AdminLocaleResolver::class)->locale(),
      ['languages' => implode(', ', array_column($translations, 'code'))],
    ));
  }
}
