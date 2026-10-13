<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

class AdminApprovedUpdateClient extends UpdateServerClient
{
  public function __construct(private readonly UpdateServerClient $delegate, private readonly AdminUpdateOperation $operation) {}

  public function check(): UpdateCheckResult
  {
    $check = $this->delegate->check();
    if ($check->installedVersion !== $this->operation->from_version
      || ($check->release['version'] ?? $check->latestVersion) !== $this->operation->target_version
      || ! hash_equals($this->operation->checksum_sha256, strtolower((string) ($check->release['checksum_sha256'] ?? '')))) {
      throw new ApprovedUpdateTargetChanged;
    }

    return $check;
  }

  public function checkForVersion(string $version): UpdateCheckResult
  {
  return $this->delegate->checkForVersion($version);
  }
}
