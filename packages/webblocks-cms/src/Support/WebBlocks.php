<?php

namespace WebBlocks\Cms\Support;

final class WebBlocks
{
  public const NAME = 'WebBlocks CMS';

  public const SLOGAN = 'A modern block-based CMS';

  public const HANDLE = 'webblocks-cms';

  public const VERSION = '1.32.63';

  public const UI_VERSION = 'v2.7.8';

  public const UI_DIST_BASE = 'https://raw.githubusercontent.com/fklavyenet/webblocks-ui/'.self::UI_VERSION.'/packages/webblocks/dist';

  public const UI_CSS_URL = self::UI_DIST_BASE.'/webblocks-ui.min.css';

  public const ICONS_CSS_URL = self::UI_DIST_BASE.'/webblocks-icons.min.css';

  public const UI_JS_URL = self::UI_DIST_BASE.'/webblocks-ui.min.js';

  public const ICONS_MANIFEST_URL = self::UI_DIST_BASE.'/webblocks-icons.json';

  public static function name(): string
  {
    return self::NAME;
  }

  public static function slogan(): string
  {
    return self::SLOGAN;
  }

  public static function handle(): string
  {
    return self::HANDLE;
  }

  public static function version(): string
  {
    return self::VERSION;
  }

  public static function uiVersion(): string
  {
    return self::UI_VERSION;
  }

  public static function uiCssUrl(): string
  {
    return self::UI_CSS_URL;
  }

  public static function iconsCssUrl(): string
  {
    return self::ICONS_CSS_URL;
  }

  public static function uiJsUrl(): string
  {
    return self::UI_JS_URL;
  }

  public static function iconsManifestUrl(): string
  {
    return self::ICONS_MANIFEST_URL;
  }
}
