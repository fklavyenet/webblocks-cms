<?php

namespace WebBlocks\Cms\Support\ContentSources\Contracts;

use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;

interface ContentSourceAccessPolicy
{
  public function allows(ContentSourceContext $context): bool;
}
