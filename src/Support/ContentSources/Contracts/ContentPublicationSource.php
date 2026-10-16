<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentPublicationPage;
use WebBlocks\Cms\Support\ContentSources\ContentPublicationRecord;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

/** Optional publication contract 1.0. Enumeration and availability must be current, never stale fallback copy. */
interface ContentPublicationSource
{
  /** A bounded page of public identities, independent of block collection limits. */
  public function publicationPage(?string $cursor, int $limit, ContentSourceContext $context): ContentPublicationPage;

  /** Distinguish a withdrawn/missing record from an upstream failure. */
  public function publicationRecord(string $key, ContentSourceContext $context): ContentPublicationRecord;
}
