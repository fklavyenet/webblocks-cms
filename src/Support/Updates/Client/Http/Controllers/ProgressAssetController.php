<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Http\Controllers;

final class ProgressAssetController
{
    public function __invoke(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $root = dirname(__DIR__, 2);
        $path = is_file($root.'/Resources/js/update-progress.js') ? $root.'/Resources/js/update-progress.js' : dirname($root).'/resources/js/update-progress.js';
        return response()->file($path, ['Content-Type' => 'application/javascript; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
