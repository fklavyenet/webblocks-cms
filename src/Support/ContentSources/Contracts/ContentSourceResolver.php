<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

interface ContentSourceResolver
{
  /**
   * Resolve one source record into the fields declared by its definition.
   *
   * @return array<string, mixed>|null
   */
  public function resolve(string $recordKey, ContentSourceContext $context): ?array;

  /**
   * Records an editor may use while configuring and previewing a binding.
   *
   * @return array<string, string> record key => human-readable label
   */
  public function options(ContentSourceContext $context): array;
}
