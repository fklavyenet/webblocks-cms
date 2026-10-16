<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

/** Optional public routing capability. Never enumerate the catalogue to resolve a URL. */
interface ContentPublicationRouteSource extends ContentPublicationSource
{
  /** Current public stable key for an exact slug; null means missing/withdrawn. Throw on upstream failure. */
  public function publicationKey(string $slug, ContentSourceContext $context): ?string;
}
