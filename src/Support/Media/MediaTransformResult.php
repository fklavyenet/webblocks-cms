<?php

namespace WebBlocks\Cms\Support\Media;

final readonly class MediaTransformResult
{
  public function __construct(
    public ?string $url,
    public ?int $width,
    public ?int $height,
    public bool $generated,
    public string $status,
  ) {}
}
