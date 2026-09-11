<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;

readonly class ContentSourceContext
{
  public function __construct(
    public ?Site $site,
    public ?Page $page,
    public ?string $locale,
    public bool $preview,
    public ?Authenticatable $actor = null,
  ) {}
}
