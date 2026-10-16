<?php

namespace WebBlocks\Cms\Support\ContentSources;

final readonly class ContentPublicationRecord
{
  private function __construct(public string $status, public ?array $record = null) {}

  public static function available(array $record): self
  {
    return new self('available', $record);
  }

  public static function missing(): self
  {
    return new self('missing');
  }

  public static function unavailable(): self
  {
    return new self('unavailable');
  }

  public static function incomplete(): self
  {
    return new self('incomplete');
  }
}
