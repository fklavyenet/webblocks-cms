<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Support\Arr;

class CatalogRelease
{
  /**
   * @param  array<string, mixed>  $payload
   */
  public function __construct(
    public readonly ?string $version,
    public readonly ?string $requiredCmsVersion,
    public readonly ?string $channel,
    public readonly ?string $status,
    public readonly ?string $summary,
    public readonly ?string $notes,
    public readonly array $highlights,
    public readonly ?string $documentationUrl,
    public readonly ?string $detailsUrl,
    public readonly ?string $downloadUrl,
    public readonly ?string $checksumSha256,
    public readonly ?string $artifactFilename,
    public readonly ?string $artifactSize,
  ) {}

  /**
   * @param  array<string, mixed>  $payload
   */
  public static function fromArray(array $payload): self
  {
    return new self(
      version: self::stringOrNull(Arr::get($payload, 'version')),
      requiredCmsVersion: self::stringOrNull(Arr::get($payload, 'required_cms_version', Arr::get($payload, 'requires_cms'))),
      channel: self::stringOrNull(Arr::get($payload, 'channel')),
      status: self::stringOrNull(Arr::get($payload, 'status')),
      summary: self::stringOrNull(Arr::get($payload, 'summary')),
      notes: self::stringOrNull(Arr::get($payload, 'notes', Arr::get($payload, 'release_notes'))),
      highlights: self::stringList(Arr::get($payload, 'highlights')),
      documentationUrl: self::safeUrl(Arr::get($payload, 'documentation_url', Arr::get($payload, 'urls.documentation'))),
      detailsUrl: self::safeUrl(Arr::get($payload, 'details_url', Arr::get($payload, 'urls.details'))),
      downloadUrl: self::safeUrl(Arr::get($payload, 'download_url', Arr::get($payload, 'artifact_url', Arr::get($payload, 'urls.download')))),
      checksumSha256: self::stringOrNull(Arr::get($payload, 'checksum_sha256', Arr::get($payload, 'sha256'))),
      artifactFilename: self::stringOrNull(Arr::get($payload, 'artifact_filename', Arr::get($payload, 'filename'))),
      artifactSize: self::stringOrNull(Arr::get($payload, 'artifact_size', Arr::get($payload, 'size'))),
    );
  }

  public function displaySummary(): ?string
  {
    return $this->summary ?? $this->notes;
  }

  private static function stringOrNull(mixed $value): ?string
  {
    return is_string($value) && trim($value) !== '' ? trim($value) : null;
  }

  private static function safeUrl(mixed $value): ?string
  {
    $url = self::stringOrNull($value);

    if ($url === null) {
      return null;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);

    return in_array($scheme, ['http', 'https'], true) ? $url : null;
  }

  /**
   * @return array<int, string>
   */
  private static function stringList(mixed $value): array
  {
    if (! is_array($value)) {
      return [];
    }

    return array_values(array_filter(array_map(
      fn (mixed $item): ?string => self::stringOrNull($item),
      $value,
    )));
  }
}
