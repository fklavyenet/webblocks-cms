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
    public readonly ?string $description,
    public readonly ?string $vendor,
    public readonly ?string $author,
    public readonly ?string $compatibilityStatus,
    public readonly ?string $requiredCmsVersion,
    public readonly ?string $channel,
    public readonly ?string $status,
    public readonly ?string $websiteUrl,
    public readonly ?string $documentationUrl,
    public readonly ?string $supportUrl,
    public readonly ?string $detailsUrl,
    public readonly ?string $downloadUrl,
    public readonly array $declaredPermissions,
    public readonly array $declaredRoutes,
    public readonly array $declaredMigrations,
    public readonly array $declaredProviders,
    public readonly array $declaredCommands,
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

    $author = self::stringOrNull(Arr::get($payload, 'author'));

    if ($author === null) {
      $author = self::stringOrNull(Arr::get($payload, 'author.name'));
    }

    return new self(
      handle: $handle,
      label: $label,
      summary: self::stringOrNull(Arr::get($payload, 'summary')),
      description: self::stringOrNull(Arr::get($payload, 'description')),
      vendor: $vendor,
      author: $author,
      compatibilityStatus: self::stringOrNull(Arr::get($payload, 'compatibility.status', Arr::get($payload, 'compatibility_status'))),
      requiredCmsVersion: self::stringOrNull(Arr::get($payload, 'compatibility.requires_cms', Arr::get($payload, 'required_cms_version', Arr::get($payload, 'requires_cms')))),
      channel: self::stringOrNull(Arr::get($payload, 'channel')),
      status: self::stringOrNull(Arr::get($payload, 'status')),
      websiteUrl: self::safeUrl(Arr::get($payload, 'website_url', Arr::get($payload, 'urls.website'))),
      documentationUrl: self::safeUrl(Arr::get($payload, 'documentation_url', Arr::get($payload, 'urls.documentation'))),
      supportUrl: self::safeUrl(Arr::get($payload, 'support_url', Arr::get($payload, 'urls.support'))),
      detailsUrl: self::safeUrl(Arr::get($payload, 'details_url', Arr::get($payload, 'urls.details'))),
      downloadUrl: self::safeUrl(Arr::get($payload, 'download_url', Arr::get($payload, 'artifact_url', Arr::get($payload, 'urls.download')))),
      declaredPermissions: self::stringList(Arr::get($payload, 'permissions')),
      declaredRoutes: self::stringList(Arr::get($payload, 'routes')),
      declaredMigrations: self::stringList(Arr::get($payload, 'migrations')),
      declaredProviders: self::stringList(Arr::get($payload, 'providers')),
      declaredCommands: self::stringList(Arr::get($payload, 'commands')),
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

  public function firstWebsiteUrl(): ?string
  {
    return $this->websiteUrl;
  }

  public function firstSupportUrl(): ?string
  {
    return $this->supportUrl;
  }

  public function firstDetailsUrl(): ?string
  {
    return $this->detailsUrl ?? $this->latestCompatibleRelease?->detailsUrl;
  }

  public function firstDownloadUrl(): ?string
  {
    return $this->downloadUrl ?? $this->latestCompatibleRelease?->downloadUrl;
  }

  public function isCompatible(): bool
  {
    return in_array($this->compatibilityStatus, ['compatible', 'supported'], true)
      || ($this->compatibilityStatus === null && $this->latestCompatibleRelease !== null);
  }

  public function hasInstallableArtifact(): bool
  {
    return $this->isCompatible()
      && $this->latestCompatibleRelease !== null
      && $this->firstDownloadUrl() !== null
      && $this->latestCompatibleRelease->checksumSha256 !== null
      && $this->latestCompatibleRelease->artifactFilename !== null;
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
