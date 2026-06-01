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
    public readonly ?string $documentationUrl,
    public readonly ?string $detailsUrl,
    public readonly ?string $downloadUrl,
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
      documentationUrl: self::safeUrl(Arr::get($payload, 'documentation_url', Arr::get($payload, 'urls.documentation'))),
      detailsUrl: self::safeUrl(Arr::get($payload, 'details_url', Arr::get($payload, 'urls.details'))),
      downloadUrl: self::safeUrl(Arr::get($payload, 'download_url', Arr::get($payload, 'urls.download'))),
    );
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
