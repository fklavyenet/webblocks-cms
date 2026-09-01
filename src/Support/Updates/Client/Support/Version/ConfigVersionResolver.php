<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Support\Version;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Default version resolver.
 *
 * Reads the installed version from config: `publisher-client.version.source`
 * first (normally wired to the product's Support::VERSION), falling back to
 * the framework's `app.version`. For post-apply verification it tokenizes the
 * configured const file inside the applied root (§4.2) — parsing the source
 * rather than autoloading the not-yet-active new class.
 */
final class ConfigVersionResolver implements VersionResolver
{
    public function __construct(private readonly Config $config)
    {
    }

    public function current(): string
    {
        $explicit = $this->config->get('publisher-client.version.source');

        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $appVersion = $this->config->get('app.version');

        return is_string($appVersion) && $appVersion !== '' ? $appVersion : '0.0.0';
    }

    public function appliedVersion(string $appliedRoot): ?string
    {
        $relative = $this->config->get('publisher-client.version.const_file');
        $constName = $this->config->get('publisher-client.version.const_name', 'VERSION');

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $path = rtrim($appliedRoot, '/\\') . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return $this->parseConstFromFile($path, is_string($constName) ? $constName : 'VERSION');
    }

    /**
     * Extract `const <NAME> = '<value>'` from a PHP source file via the tokenizer.
     * Deliberately does not include or autoload the file.
     */
    private function parseConstFromFile(string $path, string $constName): ?string
    {
        $source = @file_get_contents($path);

        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $seenConst = false;
        $seenName = false;

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                // A ';' resets a partial match so we don't leak across statements.
                if ($token === ';') {
                    $seenConst = false;
                    $seenName = false;
                }
                continue;
            }

            [$id, $text] = $token;

            if ($id === T_CONST) {
                $seenConst = true;
                $seenName = false;
                continue;
            }

            if ($seenConst && $id === T_STRING && $text === $constName) {
                $seenName = true;
                continue;
            }

            if ($seenConst && $seenName && $id === T_CONSTANT_ENCAPSED_STRING) {
                return trim($text, "'\"");
            }
        }

        return null;
    }
}
