<?php

namespace WebBlocks\Cms\Support\Theme;

use Composer\Semver\Semver;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;
use WebBlocks\Cms\Support\WebBlocks;
use ZipArchive;

final class ThemePackage
{
  public const MAX_KILOBYTES = 8192;

  public const STYLESHEET = 'theme.css';

  private const MANIFEST = 'webblocks-theme.json';

  private const MIME = [
    'css' => 'text/css',
    'woff2' => 'font/woff2',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'webp' => 'image/webp',
    'avif' => 'image/avif',
  ];

  public function read(string $path): array
  {
    $zip = new ZipArchive;
    if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
      $this->invalid('invalid_archive');
    }

    try {
      $entries = $this->entries($zip);
      $manifest = $this->manifest($entries[self::MANIFEST] ?? '');
      unset($entries[self::MANIFEST]);

      if (! isset($entries[self::STYLESHEET])) {
        $this->invalid('invalid_archive');
      }

      $assets = [];
      foreach ($entries as $name => $contents) {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $mime = self::MIME[$extension] ?? null;
        if ($name === self::STYLESHEET) {
          $contents = $this->stylesheet($contents, $entries, $manifest['handle']);
        } elseif ($mime === null || ! $this->matchesAsset($contents, $mime)) {
          $this->invalid('invalid_asset');
        }
        $assets[$name] = ['mime' => $mime, 'contents' => $contents, 'checksum' => hash('sha256', $contents)];
      }

      return [
        'version' => collect($manifest)->except('layouts')->all() + ['checksum' => hash_file('sha256', $path)],
        'layouts' => $manifest['layouts'],
        'assets' => $assets,
      ];
    } finally {
      $zip->close();
    }
  }

  public static function compatible(object $version): bool
  {
    return Semver::satisfies(WebBlocks::VERSION, $version->cms)
      && Semver::satisfies(ltrim(WebBlocks::UI_VERSION, 'v'), $version->ui);
  }

  private function entries(ZipArchive $zip): array
  {
    $entries = [];
    $total = 0;
    if ($zip->numFiles > 64) {
      $this->invalid('invalid_archive');
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
      $entry = $zip->statIndex($index);
      $name = $entry['name'] ?? '';
      if (! preg_match('~^(?:[a-z0-9][a-z0-9_-]{0,63}/){0,3}[a-z0-9][a-z0-9_-]{0,63}(?:\.[a-z0-9]{1,8})?/?$~D', $name)
        || strlen($name) > 190 || isset($entries[$name])
        || ! $zip->getExternalAttributesIndex($index, $os, $attributes)) {
        $this->invalid('invalid_archive');
      }

      $type = $os === ZipArchive::OPSYS_UNIX ? (($attributes >> 16) & 0170000) : 0;
      if (str_ends_with($name, '/')) {
        if (! in_array($type, [0, 0040000], true) || $entry['size'] !== 0) {
          $this->invalid('invalid_archive');
        }

        continue;
      }
      $limit = match ($name) {
        self::MANIFEST => 8192,
        self::STYLESHEET => 131072,
        default => 2097152,
      };
      $total += $entry['size'];
      if (! in_array($type, [0, 0100000], true) || $entry['size'] < 1 || $entry['size'] > $limit || $total > self::MAX_KILOBYTES * 1024
        || ($entry['encryption_method'] ?? 0) !== 0
        || ($contents = $zip->getFromIndex($index, $limit + 1)) === false || strlen($contents) !== $entry['size']
        || ($name !== self::MANIFEST && $name !== self::STYLESHEET
          && (! str_starts_with($name, 'assets/') || ! in_array(pathinfo($name, PATHINFO_EXTENSION), ['woff2', 'png', 'jpg', 'webp', 'avif'], true)))) {
        $this->invalid('invalid_archive');
      }
      $entries[$name] = $contents;
    }

    return $entries;
  }

  private function manifest(string $json): array
  {
    try {
      $manifest = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $this->invalid('invalid_manifest');
    }

    if (! is_array($manifest) || ($manifest['format_version'] ?? null) !== 1
      || array_diff(array_keys($manifest), ['format_version', 'handle', 'version', 'name', 'license', 'cms', 'ui', 'layouts', 'button_corners']) !== []) {
      $this->invalid('invalid_manifest');
    }

    foreach ([
      'handle' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',
      'version' => '/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[a-z0-9]+(?:[.-][a-z0-9]+)*)?$/D',
      'name' => '/^[\p{L}\p{N}][\p{L}\p{N}\p{M} ._-]*$/uD',
      'license' => '/^[a-zA-Z0-9][a-zA-Z0-9.+-]*$/D',
      'cms' => '/^[v0-9a-zA-Z~^*<>=|.,! -]+$/D',
      'ui' => '/^[v0-9a-zA-Z~^*<>=|.,! -]+$/D',
    ] as $field => $pattern) {
      $value = $manifest[$field] ?? null;
      $limit = match ($field) {
        'handle' => 63,
        'version', 'license' => 64,
        default => 100,
      };
      if (! is_string($value) || mb_strlen($value) > $limit || ! preg_match($pattern, $value)) {
        $this->invalid('invalid_manifest');
      }
    }

    $layouts = $manifest['layouts'] ?? null;
    if (! is_array($layouts) || ! array_is_list($layouts) || $layouts === []
      || count($layouts) > count(PageLayoutCatalog::handles())
      || ! is_bool($manifest['button_corners'] ?? false)) {
      $this->invalid('invalid_manifest');
    }
    foreach ($layouts as $layout) {
      if (! is_string($layout) || ! in_array($layout, PageLayoutCatalog::handles(), true)) {
        $this->invalid('invalid_manifest');
      }
    }

    try {
      self::compatible((object) $manifest);
    } catch (InvalidArgumentException) {
      $this->invalid('invalid_manifest');
    }

    unset($manifest['format_version']);
    $manifest['layouts'] = array_values(array_unique($layouts));
    $manifest['button_corners'] ??= false;

    return $manifest;
  }

  private function stylesheet(string $css, array $entries, string $handle): string
  {
    // The public stylesheet is data from an uploaded archive. Keep its resources
    // local and its selectors inside the theme root. No CSS escapes or imports.
    if (! mb_check_encoding($css, 'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f<>]/', $css) || str_contains($css, '\\')) {
      $this->invalid('invalid_css');
    }
    $css = preg_replace('~/\*.*?\*/~s', '', $css);
    if (str_contains($css, '/*') || str_contains($css, '*/')
      || preg_match('/!\s*important\b|@(?!(?:media|supports|font-face)\b)|\b(?:image(?:-set)?|-webkit-image-set|src|paint|element)\s*\(/i', $css)) {
      $this->invalid('invalid_css');
    }
    $withoutUrls = preg_replace_callback('~\burl\(\s*([\x22\x27]?)(assets/[a-z0-9_./-]+)\1\s*\)~i', function (array $match) use ($entries): string {
      if (! isset($entries[$match[2]])) {
        $this->invalid('invalid_asset');
      }

      return 'local-resource';
    }, $css);
    if (preg_match('/\burl\s*\(/i', $withoutUrls)) {
      $this->invalid('invalid_css');
    }

    $depth = 0;
    preg_match_all('/"[^"\r\n]*"|\x27[^\x27\r\n]*\x27|[{}"\x27]/', $css, $tokens);
    foreach ($tokens[0] as $token) {
      if ($token === '{') {
        $depth++;
      } elseif ($token === '}') {
        $depth--;
      } elseif (strlen($token) === 1) {
        $this->invalid('invalid_css');
      }
      if ($depth < 0 || $depth > 8) {
        $this->invalid('invalid_css');
      }
    }
    if ($depth !== 0) {
      $this->invalid('invalid_css');
    }

    return '@scope (body[data-wb-theme-package="'.$handle.'"]) {'.PHP_EOL.$css.PHP_EOL.'}'.PHP_EOL;
  }

  private function matchesAsset(string $contents, string $mime): bool
  {
    if ($mime === 'font/woff2') {
      return strlen($contents) >= 48 && str_starts_with($contents, 'wOF2');
    }

    $image = @getimagesizefromstring($contents);

    return $image !== false && $image['mime'] === $mime && $image[0] <= 8192 && $image[1] <= 8192;
  }

  private function invalid(string $key): never
  {
    throw ValidationException::withMessages(['package' => __('webblocks-cms::themes.'.$key)]);
  }
}
