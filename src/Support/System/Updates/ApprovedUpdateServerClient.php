<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class ApprovedUpdateServerClient extends UpdateServerClient
{
  public function __construct(private readonly UpdateServerClient $delegate, private readonly SystemUpdateRequest $approval) {}

  public function check(): UpdateCheckResult
  {
    $check = $this->delegate->check();

    if ($check->installedVersion !== $this->approval->expected_current_version
      || ($check->release['version'] ?? $check->latestVersion) !== $this->approval->target_version
      || ! hash_equals($this->approval->checksum_sha256, strtolower((string) ($check->release['checksum_sha256'] ?? '')))) {
      throw new ApprovedUpdateTargetChanged;
    }

    return $check;
  }

  public function checkForVersion(string $installedVersion): UpdateCheckResult
  {
    return $this->delegate->checkForVersion($installedVersion);
  }
}
