<?php

namespace WebBlocks\Cms\Support\Plugins\Catalog;

use Illuminate\Support\Arr;

class CatalogPlugin
{
  /**
   * @param  list<array{url: string, alt: ?string, caption: ?string, width: ?int, height: ?int}>  $screenshots
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
    public readonly ?string $artworkUrl,
    public readonly ?string $artworkAlt,
    public readonly array $categories,
    public readonly string $pricingType,
    public readonly ?int $priceMinor,
    public readonly ?string $priceCurrency,
    public readonly ?string $billingPeriod,
    public readonly int $downloadsTotal,
    public readonly array $dailyDownloads,
    public readonly array $declaredPermissions,
    public readonly array $declaredRoutes,
    public readonly array $declaredMigrations,
    public readonly array $declaredProviders,
    public readonly array $declaredCommands,
    public readonly ?CatalogRelease $latestCompatibleRelease,
    public readonly array $screenshots = [],
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
      artworkUrl: self::safeUrl(Arr::get($payload, 'artwork.card_url')),
      artworkAlt: self::stringOrNull(Arr::get($payload, 'artwork.alt')),
      categories: self::namedList(Arr::get($payload, 'categories')),
      pricingType: self::stringOrNull(Arr::get($payload, 'pricing.type')) === 'paid' ? 'paid' : 'free',
      priceMinor: self::integerOrNull(Arr::get($payload, 'pricing.price_minor')),
      priceCurrency: self::stringOrNull(Arr::get($payload, 'pricing.currency')),
      billingPeriod: self::stringOrNull(Arr::get($payload, 'pricing.billing_period')),
      downloadsTotal: max(0, self::integerOrNull(Arr::get($payload, 'downloads.total')) ?? 0),
      dailyDownloads: self::downloadSeries(Arr::get($payload, 'downloads.daily')),
      declaredPermissions: self::stringList(Arr::get($payload, 'permissions')),
      declaredRoutes: self::stringList(Arr::get($payload, 'routes')),
      declaredMigrations: self::stringList(Arr::get($payload, 'migrations')),
      declaredProviders: self::stringList(Arr::get($payload, 'providers')),
      declaredCommands: self::stringList(Arr::get($payload, 'commands')),
      latestCompatibleRelease: $latestCompatibleRelease,
      screenshots: self::screenshots($payload['media'] ?? null),
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
      && $this->latestCompatibleRelease?->hasInstallableArtifact() === true;
  }

  private static function stringOrNull(mixed $value): ?string
  {
    return is_string($value) && trim($value) !== '' ? trim($value) : null;
  }

  private static function integerOrNull(mixed $value): ?int
  {
    return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
  }

  /** @return array<int, array{slug: string, name: string}> */
  private static function namedList(mixed $value): array
  {
    if (! is_array($value)) {
      return [];
    }

    return array_values(array_filter(array_map(function (mixed $item): ?array {
      if (! is_array($item)) {
        return null;
      }

      $slug = self::stringOrNull(Arr::get($item, 'slug'));
      $name = self::stringOrNull(Arr::get($item, 'name'));

      return $slug !== null && $name !== null ? ['slug' => $slug, 'name' => $name] : null;
    }, $value)));
  }

  /** @return array<int, array{date: string, downloads: int}> */
  private static function downloadSeries(mixed $value): array
  {
    if (! is_array($value)) {
      return [];
    }

    return array_values(array_filter(array_map(function (mixed $item): ?array {
      if (! is_array($item)) {
        return null;
      }

      $date = self::stringOrNull(Arr::get($item, 'date'));
      $downloads = self::integerOrNull(Arr::get($item, 'downloads'));

      return $date !== null && $downloads !== null ? ['date' => $date, 'downloads' => max(0, $downloads)] : null;
    }, $value)));
  }

  /** @return list<array{url: string, alt: ?string, caption: ?string, width: ?int, height: ?int}> */
  private static function screenshots(mixed $media): array
  {
    if (! is_array($media)) {
      return [];
    }

    $screenshots = [];
    foreach ($media as $item) {
      if (! is_array($item) || ($item['role'] ?? null) !== 'screenshot') {
        continue;
      }

      $url = self::safeUrl($item['url'] ?? null);
      if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
        continue;
      }

      $width = self::integerOrNull($item['width'] ?? null);
      $height = self::integerOrNull($item['height'] ?? null);
      $screenshots[] = [
        'url' => $url,
        'alt' => self::stringOrNull($item['alt'] ?? null),
        'caption' => self::stringOrNull($item['caption'] ?? null),
        'width' => $width > 0 ? $width : null,
        'height' => $height > 0 ? $height : null,
      ];
    }

    return $screenshots;
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
