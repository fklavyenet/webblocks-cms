<?php

namespace WebBlocks\Cms\Support\System\Updates;

/** Process-local identity established by an admitted API request or claimed worker. */
class CmsUpdateAdmissionContext
{
  public ?string $operationId = null;

  public ?string $requestId = null;
}
