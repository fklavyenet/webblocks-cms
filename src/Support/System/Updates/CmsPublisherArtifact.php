<?php

namespace WebBlocks\Cms\Support\System\Updates;

final class CmsPublisherArtifact
{
  public ?string $url = null;

  public ?string $archive = null;

  public ?string $commit = null;

  public ?string $version = null;

  public ?string $checksum = null;

  public ?int $releaseId = null;

  public ?string $receiptProof = null;

  public bool $transactional = false;

  public bool $applicationChanged = false;

  public function start(string $url): void
  {
    $this->url = $url;
    $this->archive = $this->commit = null;
    $this->version = $this->checksum = $this->receiptProof = null;
    $this->releaseId = null;
    $this->transactional = config('publisher-client.channel') === 'test';
    $this->applicationChanged = false;
  }
}
