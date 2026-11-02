<?php

namespace WebBlocks\Cms\Models;

class UpdateChainStep extends CmsModel
{
  protected $table = 'wbcms_update_chain_steps';

  protected $guarded = [];

  protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

  public function release(AdminUpdateOperation $operation): array
  {
    return ['from_version' => $this->from_version, 'version' => $this->version,
      'minimum_client_version' => $this->minimum_client_version, 'checksum_sha256' => $this->checksum_sha256,
      'signature' => $this->signature, 'artifact_url' => $this->artifact_url,
      'source_reference' => $this->source_reference, 'product' => $operation->product, 'channel' => $operation->channel];
  }
}
