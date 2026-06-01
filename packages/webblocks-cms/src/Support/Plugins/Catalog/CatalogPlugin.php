<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Support\Arr;

class CatalogPlugin
{
  /**
   * @param  array<string, mixed>  $payload
   */
  public function __construct(
    public readonly string $handle,
    public readonly string $label,
    public readonly ?string $summary,
    public readonly ?string $vendor,
    public readonly ?string $compatibilityStatus,
    public readonly ?string $requiredCmsVersion,
    public readonly ?string $channel,
    public readonly ?string $status,
    public readonly ?string $documentationUrl,
    public readonly ?string $detailsUrl,
    public readonly ?string $downloadUrl,
    public readonly ?CatalogRelease $latestCompatibleRelease,
  ) {}

  /**
   * @param  array<string, mixed>  $payload
   */
  public static function fromArray(array $payload, ?CatalogRelease $latestCompatibleRelease = null): ?self
  {
    $handle = self::stringOrNull(Arr::get($payload, 'handle'));

    if ($handle === null) {
      return null;
    }

    $embeddedRelease = Arr::get($payload, 'latest_compatible_release', Arr::get($payload, 'latest_release'));

    if ($latestCompatibleRelease === null && is_array($embeddedRelease)) {
      $latestCompatibleRelease = CatalogRelease::fromArray($embeddedRelease);
    }

    $label = self::stringOrNull(Arr::get($payload, 'label', Arr::get($payload, 'name'))) ?? $handle;
    $vendor = self::stringOrNull(Arr::get($payload, 'vendor', Arr::get($payload, 'author')));

    if ($vendor === null) {
      $vendor = self::stringOrNull(Arr::get($payload, 'vendor.name', Arr::get($payload, 'author.name')));
    }

    return new self(
      handle: $handle,
      label: $label,
      summary: self::stringOrNull(Arr::get($payload, 'summary', Arr::get($payload, 'description'))),
      vendor: $vendor,
      compatibilityStatus: self::stringOrNull(Arr::get($payload, 'compatibility.status', Arr::get($payload, 'compatibility_status'))),
      requiredCmsVersion: self::stringOrNull(Arr::get($payload, 'compatibility.requires_cms', Arr::get($payload, 'required_cms_version', Arr::get($payload, 'requires_cms')))),
      channel: self::stringOrNull(Arr::get($payload, 'channel')),
      status: self::stringOrNull(Arr::get($payload, 'status')),
      documentationUrl: self::safeUrl(Arr::get($payload, 'documentation_url', Arr::get($payload, 'urls.documentation'))),
      detailsUrl: self::safeUrl(Arr::get($payload, 'details_url', Arr::get($payload, 'urls.details'))),
      downloadUrl: self::safeUrl(Arr::get($payload, 'download_url', Arr::get($payload, 'urls.download'))),
      latestCompatibleRelease: $latestCompatibleRelease,
    );
  }

  public function displayRequiredCmsVersion(): ?string
  {
    return $this->latestCompatibleRelease?->requiredCmsVersion ?? $this->requiredCmsVersion;
  }

  public function displayChannel(): ?string
  {
    return $this->latestCompatibleRelease?->channel ?? $this->channel;
  }

  public function displayStatus(): ?string
  {
    return $this->latestCompatibleRelease?->status ?? $this->status;
  }

  public function firstDocumentationUrl(): ?string
  {
    return $this->documentationUrl ?? $this->latestCompatibleRelease?->documentationUrl;
  }

  public function firstDetailsUrl(): ?string
  {
    return $this->detailsUrl ?? $this->latestCompatibleRelease?->detailsUrl;
  }

  public function firstDownloadUrl(): ?string
  {
    return $this->downloadUrl ?? $this->latestCompatibleRelease?->downloadUrl;
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
}
