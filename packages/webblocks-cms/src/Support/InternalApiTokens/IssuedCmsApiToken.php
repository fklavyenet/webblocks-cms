<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use WebBlocks\Cms\Models\CmsApiToken;

class IssuedCmsApiToken
{
  public function __construct(
    public readonly CmsApiToken $record,
    public readonly string $plainToken,
  ) {}
}
