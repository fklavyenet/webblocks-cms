<?php

namespace WebBlocks\Cms\Support\Media;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use WebBlocks\Cms\Models\Media;

class RemoteMediaFetcher
{
  private const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/svg+xml',
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'application/pdf',
    'text/plain',
    'text/csv',
    'application/msword',
    'application/vnd.ms-excel',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/rtf',
    'application/zip',
  ];

  public function __construct(private readonly MediaUploader $mediaUploader) {}

  public function fetch(string $url, array $data = [], ?int $uploadedBy = null): Media
  {
    $maxBytes = $this->maxBytes();
    [$response, $effectiveUrl] = $this->download($url, $maxBytes);
    $mimeType = $this->mimeType($response);

    if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
      throw new RuntimeException('Remote media type is not allowed: '.$mimeType.'.');
    }

    $contents = $response->body();

    if (strlen($contents) > $maxBytes) {
      throw new RuntimeException('Remote media is larger than the configured limit.');
    }

    $temporaryPath = tempnam(storage_path('framework/cache'), 'wb-remote-media-');

    if ($temporaryPath === false) {
      throw new RuntimeException('Unable to create a temporary file for remote media.');
    }

    file_put_contents($temporaryPath, $contents);

    $uploadedFile = new UploadedFile(
      $temporaryPath,
      $this->filenameFromUrl($effectiveUrl, $mimeType),
      $mimeType,
      null,
      true,
    );

    try {
      return $this->mediaUploader->upload($uploadedFile, $data, $uploadedBy);
    } finally {
      @unlink($temporaryPath);
    }
  }

  /**
   * @return array{0: Response, 1: string}
   */
  private function download(string $url, int $maxBytes): array
  {
    $currentUrl = $url;
    $maxRedirects = (int) config('webblocks-cms.media.remote_fetch.max_redirects', 3);

    for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
      $this->assertPublicUrl($currentUrl);

      $response = Http::accept('*/*')
        ->timeout((int) config('webblocks-cms.media.remote_fetch.timeout_seconds', 15))
        ->connectTimeout((int) config('webblocks-cms.media.remote_fetch.connect_timeout_seconds', 5))
        ->withOptions([
          'allow_redirects' => false,
          'progress' => function (int $downloadTotal, int $downloadedBytes) use ($maxBytes): void {
            if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
              throw new RuntimeException('Remote media is larger than the configured limit.');
            }
          },
        ])
        ->get($currentUrl);

      if ($response->redirect()) {
        $location = $response->header('Location');

        if (! is_string($location) || trim($location) === '') {
          throw new RuntimeException('Remote media redirect did not include a valid target.');
        }

        $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));

        continue;
      }

      if (! $response->successful()) {
        throw new RuntimeException('Remote media could not be fetched. The server responded with HTTP '.$response->status().'.');
      }

      return [$response, $currentUrl];
    }

    throw new RuntimeException('Remote media followed too many redirects.');
  }

  private function assertPublicUrl(string $url): void
  {
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''), '[]');

    if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
      throw new RuntimeException('Remote media URL must be an HTTP or HTTPS URL.');
    }

    if ($this->isLocalHostName($host)) {
      throw new RuntimeException('Remote media URL must not target a local host.');
    }

    foreach ($this->resolveHostIps($host) as $ip) {
      if (! $this->isPublicIp($ip)) {
        throw new RuntimeException('Remote media URL resolves to a private or reserved network address.');
      }
    }
  }

  private function resolveHostIps(string $host): array
  {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return [$host];
    }

    $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
    $ips = [];

    foreach ($records as $record) {
      foreach (['ip', 'ipv6'] as $key) {
        if (isset($record[$key]) && is_string($record[$key])) {
          $ips[] = $record[$key];
        }
      }
    }

    if ($ips === []) {
      $fallback = @gethostbynamel($host) ?: [];
      $ips = array_values(array_filter($fallback, 'is_string'));
    }

    if ($ips === []) {
      throw new RuntimeException('Remote media host could not be resolved.');
    }

    return array_values(array_unique($ips));
  }

  private function isLocalHostName(string $host): bool
  {
    $host = strtolower(rtrim($host, '.'));

    return $host === 'localhost' || str_ends_with($host, '.localhost');
  }

  private function isPublicIp(string $ip): bool
  {
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
  }

  private function mimeType(Response $response): string
  {
    $contentType = strtolower(trim((string) $response->header('Content-Type')));
    $mimeType = trim(explode(';', $contentType)[0] ?? '');

    return $mimeType !== '' ? $mimeType : 'application/octet-stream';
  }

  private function filenameFromUrl(string $url, string $mimeType): string
  {
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    $basename = basename($path);
    $filename = $basename !== '' && $basename !== '/' ? $basename : 'remote-media';
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($extension === '') {
      $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
      $extension = $extensions[0] ?? '';
      $filename .= $extension !== '' ? '.'.$extension : '';
    }

    $name = Str::slug(pathinfo($filename, PATHINFO_FILENAME));

    return ($name !== '' ? $name : 'remote-media').($extension !== '' ? '.'.$extension : '');
  }

  private function maxBytes(): int
  {
    return max(1, (int) config('webblocks-cms.media.remote_fetch.max_kilobytes', 51200)) * 1024;
  }
}
