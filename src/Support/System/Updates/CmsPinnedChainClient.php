<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Carbon\CarbonImmutable;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\Updates\Client\Chains\UpdatePlanClient;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;

class CmsPinnedChainClient extends UpdateServerClient
{
  public function __construct(private readonly AdminUpdateOperation $operation, private readonly UpdateChainStep $step) {}

  public function check(): UpdateCheckResult
  {
    if (WebBlocks::version() !== $this->step->from_version
      || config('publisher-client.product') !== $this->operation->product || config('publisher-client.channel') !== $this->operation->channel) {
      throw new ApprovedUpdateTargetChanged;
    }
    $release = app(UpdatePlanClient::class)->refreshStep($this->step->release($this->operation));
    $release['download_url'] = $release['artifact_url'];

    return new UpdateCheckResult('update_available', '', '', '', true, '1', '', $this->operation->product,
      $this->operation->channel, $this->step->from_version, $this->step->version, true,
      ['status' => 'compatible'], $release, null, null, CarbonImmutable::now());
  }
}
