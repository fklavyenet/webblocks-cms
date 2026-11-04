<?php

namespace WebBlocks\Cms\Support\Media;

use WebBlocks\Cms\Models\Media;

final class PublicMediaSources
{
  public function __construct(private readonly MediaTransformService $transforms) {}

  public function forImage(?Media $media, bool $logo = false): array
  {
    if (! $media?->isImage() || $media->url() === null) {
      return ['url' => null, 'srcset' => null, 'webp' => null];
    }
    if ((int) $media->width < 1 || (int) $media->height < 1) {
      return ['url' => $media->url(), 'srcset' => null, 'webp' => null];
    }

    $variants = $logo ? ['logo'] : ['content-small', 'content', 'content-large'];
    $fallback = $this->transforms->result($media, $logo ? 'logo' : 'content');
    $candidates = $this->transforms->responsiveCandidates($media, $variants);
    // A failed or unsupported conversion returns the original. Never label that
    // fallback as WebP: the next source / img must remain usable on its own.
    $webp = collect($variants)
      ->map(fn (string $variant) => $this->transforms->result($media, $variant.'-webp'))
      ->filter(fn (MediaTransformResult $result) => $result->generated && $result->url && $result->width)
      ->unique('width')->sortBy('width')->values()->all();

    return [
      'url' => $fallback->url,
      'srcset' => count($candidates) > 1 ? $this->srcset($candidates) : null,
      'webp' => $this->srcset($webp),
    ];
  }

  private function srcset(array $candidates): ?string
  {
    return $candidates === [] ? null : collect($candidates)
      ->map(fn (MediaTransformResult $result) => $result->url.' '.$result->width.'w')->implode(', ');
  }
}
