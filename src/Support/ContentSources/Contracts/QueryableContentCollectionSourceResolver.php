<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentCollectionQuery;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionResult;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

interface QueryableContentCollectionSourceResolver extends ContentCollectionSourceResolver
{
  /** @param array<string, mixed> $settings */
  public function queryCollection(ContentCollectionQuery $query, array $settings, ContentSourceContext $context): ContentCollectionResult;
}
