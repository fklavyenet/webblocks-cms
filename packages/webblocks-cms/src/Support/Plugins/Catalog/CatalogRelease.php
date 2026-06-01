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
    public readonly ?string $artifactStatus,
    public readonly ?string $scanStatus,
  ) {}

  /**
   * @param  array<string, mixed>  $payload
   */
  public static function fromArray(array $payload): self
  {
    $artifact = Arr::get($payload, 'artifact');
    $artifact = is_array($artifact) ? $artifact : [];

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
      downloadUrl: self::safeUrl(Arr::get($artifact, 'download_url', Arr::get($payload, 'download_url', Arr::get($payload, 'artifact_url', Arr::get($payload, 'urls.download'))))),
      checksumSha256: self::stringOrNull(Arr::get($artifact, 'checksum_sha256', Arr::get($payload, 'checksum_sha256', Arr::get($payload, 'sha256')))),
      artifactFilename: self::stringOrNull(Arr::get($artifact, 'file_name', Arr::get($payload, 'artifact_filename', Arr::get($payload, 'filename')))),
      artifactSize: self::sizeOrNull(Arr::get($artifact, 'size_bytes', Arr::get($payload, 'artifact_size', Arr::get($payload, 'size')))),
      artifactStatus: self::stringOrNull(Arr::get($artifact, 'validation_status', Arr::get($payload, 'artifact_status', Arr::get($payload, 'artifact.status')))),
      scanStatus: self::stringOrNull(Arr::get($artifact, 'scan_status', Arr::get($payload, 'scan_status', Arr::get($payload, 'artifact.scan_status')))),
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

  private static function sizeOrNull(mixed $value): ?string
  {
    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }

    return self::stringOrNull($value);
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
