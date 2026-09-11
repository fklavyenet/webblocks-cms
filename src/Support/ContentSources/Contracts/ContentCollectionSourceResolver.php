<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

interface ContentCollectionSourceResolver
{
  /**
   * @param  array<string, mixed>  $settings
   * @return iterable<array<string, mixed>>
   */
  public function resolveCollection(array $settings, ContentSourceContext $context): iterable;
}
