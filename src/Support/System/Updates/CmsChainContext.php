<?php

namespace WebBlocks\Cms\Support\System\Updates;

/** Process-local scope, established only by the claimed private step command. */
class CmsChainContext
{
  public ?int $stepId = null;

  public bool $supervising = false;
}
