<?php

namespace WebBlocks\Cms\Support\Updates;

final class ReleaseDefaults
{
  public const SERVER_URL = 'https://publisher.webblocksui.com';

  public const PRODUCT_KEY = 'webblocks-cms';

  public const CHANNEL = 'stable';

  public const LATEST_PATH = '/api/updates/latest';

  public const PUBLISH_PATH = '/api/updates/publish';

  /**
   * Pinned base64 Ed25519 public key used to verify signed update releases.
   *
   * Empty means signature verification is not enforced yet (checksum
   * verification still applies). Run `php artisan webblocks:updates:keygen`,
   * then pin the printed public key here or set WEBBLOCKS_UPDATE_PUBLIC_KEY.
   */
  public const UPDATE_PUBLIC_KEY = 'iUYAXjEXDcdJDIQdfWW04gZHVvUf8tPfPSjS4uKHXFk=';

  public static function latestUrl(): string
  {
    return self::SERVER_URL.self::LATEST_PATH;
  }

  public static function publishUrl(): string
  {
    return self::SERVER_URL.self::PUBLISH_PATH;
  }
}
