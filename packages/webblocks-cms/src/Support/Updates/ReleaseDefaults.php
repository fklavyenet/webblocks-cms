<?php

namespace WebBlocks\Cms\Support\Updates;

final class ReleaseDefaults
{
  public const SERVER_URL = 'https://publisher.webblocksui.com';

  public const PRODUCT_KEY = 'webblocks-cms';

  public const CHANNEL = 'stable';

  public const LATEST_PATH = '/api/updates/latest';

  public const PUBLISH_PATH = '/api/updates/publish';

  public static function latestUrl(): string
  {
    return self::SERVER_URL.self::LATEST_PATH;
  }

  public static function publishUrl(): string
  {
    return self::SERVER_URL.self::PUBLISH_PATH;
  }
}
