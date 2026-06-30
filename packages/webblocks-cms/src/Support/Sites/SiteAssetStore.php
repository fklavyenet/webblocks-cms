<?php

namespace WebBlocks\Cms\Support\Sites;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use WebBlocks\Cms\Models\Site;

class SiteAssetStore
{
  public const TYPE_CSS = 'css';

  public const TYPE_JS = 'js';

  public const TYPES = [
    self::TYPE_CSS,
    self::TYPE_JS,
  ];

  public function read(Site $site, string $type): array
  {
    $type = $this->normalizeType($type);
    $path = $this->absolutePath($site, $type);
    $exists = is_file($path);
    $contents = $exists ? (string) file_get_contents($path) : '';

    return [
      'type' => $type,
      'label' => strtoupper($type),
      'relative_path' => $this->relativePath($site, $type),
      'public_path' => '/'.$this->relativePath($site, $type),
      'absolute_path' => $path,
      'exists' => $exists,
      'contents' => $contents,
      'checksum' => $exists ? hash('sha256', $contents) : null,
      'size' => $exists ? strlen($contents) : 0,
      'updated_at' => $exists ? filemtime($path) : null,
    ];
  }

  public function write(Site $site, string $type, string $contents, ?string $expectedChecksum): array
  {
    $type = $this->normalizeType($type);
    $current = $this->read($site, $type);
    $currentChecksum = $current['checksum'];

    if ($expectedChecksum !== $currentChecksum) {
      throw new RuntimeException('The asset changed after it was opened. Reload the page before saving again.');
    }

    if ($current['exists'] && $current['contents'] !== $contents) {
      $this->writeRevision($site, $type, $current['contents'], (string) $currentChecksum);
    }

    $path = $this->absolutePath($site, $type);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $this->read($site, $type);
  }

  public function relativePath(Site $site, string $type): string
  {
    $handle = SiteHandle::normalize((string) $site->handle);
    $type = $this->normalizeType($type);

    if ($handle === '') {
      throw new RuntimeException('Site handle is required before site assets can be managed.');
    }

    return 'site/'.$handle.'/'.($type === self::TYPE_CSS ? 'css/site.css' : 'js/site.js');
  }

  private function absolutePath(Site $site, string $type): string
  {
    $path = public_path($this->relativePath($site, $type));
    $siteRoot = public_path('site').DIRECTORY_SEPARATOR;

    if (! str_starts_with($path, $siteRoot)) {
      throw new RuntimeException('Site asset path must stay under public/site.');
    }

    return $path;
  }

  private function writeRevision(Site $site, string $type, string $contents, string $checksum): void
  {
    $timestamp = now()->format('YmdHis');
    $suffix = $type === self::TYPE_CSS ? 'css' : 'js';
    $filename = $timestamp.'-'.$checksum.'.'.$suffix;
    $path = storage_path('app/cms/site-assets/'.$site->id.'/revisions/'.$type.'/'.$filename);

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
  }

  private function normalizeType(string $type): string
  {
    $type = strtolower(trim($type));

    if (! in_array($type, self::TYPES, true)) {
      throw new RuntimeException('Unsupported site asset type ['.Str::limit($type, 40, '').'].');
    }

    return $type;
  }
}
