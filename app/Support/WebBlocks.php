<?php

namespace App\Support;

final class WebBlocks
{
    public const NAME = 'WebBlocks CMS';

    public const SLOGAN = 'A modern block-based CMS';

    public const HANDLE = 'webblocks-cms';

    public const VERSION = '1.1.0';

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
}
