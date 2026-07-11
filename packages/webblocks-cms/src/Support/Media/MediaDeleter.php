<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\Media;

class MediaDeleter
{
  public function __construct(
    private readonly MediaUsageResolver $mediaUsageResolver,
    private readonly MediaTransformService $mediaTransformService,
  ) {}

  public function delete(Media $media): void
  {
    $usages = $this->mediaUsageResolver->resolve($media);

    if ($usages->isNotEmpty()) {
      throw new MediaInUseException($usages);
    }

    $this->mediaTransformService->clear($media);
    Storage::disk($media->disk)->delete($media->path);
    $media->delete();
  }
}
