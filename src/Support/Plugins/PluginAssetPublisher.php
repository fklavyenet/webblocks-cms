<?php

namespace WebBlocks\Cms\Support\Plugins;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Throwable;

/**
 * Copy an installed plugin's static files into the document root.
 *
 * `PluginPublicAsset` has always been able to emit a `<link>` or `<script>` for an
 * enabled plugin, and the manifest has always documented an `assets` key — but
 * nothing ever put the files anywhere a browser could reach, so the emitted tag
 * pointed at a 404. The appointments plugin found this while building its booking
 * form and shipped a fully server-rendered flow instead; every plugin since has
 * worked around the same hole.
 *
 * Publishing is a copy, not a symlink. A symlinked plugin directory would expose
 * everything in it — `src/`, migrations, whatever a plugin happens to keep next to
 * its CSS — through the web server, and one plugin shipping a stray `.env.example`
 * would be an install-wide disclosure. Copying means the document root contains
 * exactly the files that passed the checks below and nothing else.
 */
class PluginAssetPublisher
{
  /**
   * Where a plugin keeps the files it wants served, relative to its own root.
   *
   * A fixed directory rather than a manifest-declared path. A plugin naming its own
   * source directory could name `.`, and the check that this is not an escape would
   * then be the only thing standing between an install and its own `src/` being
   * served as text.
   */
  public const SOURCE_DIRECTORY = 'resources/public';

  /**
   * What may be served.
   *
   * An allowlist, and deliberately without `html`, `htm`, `svg`, `xml` or anything
   * a server might execute. `.php` is the obvious one; `.svg` and `.html` are the
   * ones people argue for, and both are documents a browser will run script from on
   * the site's own origin.
   *
   * @var list<string>
   */
  private const ALLOWED_EXTENSIONS = [
    'css', 'js', 'mjs', 'map',
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
    'woff', 'woff2', 'ttf', 'otf', 'eot',
    'json', 'txt',
  ];

  /**
   * Publish every installed plugin's assets.
   *
   * Disabled plugins are published too. Their tags are not emitted, so the files are
   * unreachable in practice, and republishing on every enable would mean the first
   * request after enabling raced a directory copy.
   *
   * @param  array<string, PluginDefinition>  $plugins  keyed by handle
   * @return array{published: int, skipped: int, plugins: int}
   */
  public function publishAll(array $plugins): array
  {
    $published = 0;
    $skipped = 0;
    $touched = 0;

    foreach ($plugins as $plugin) {
      $result = $this->publish($plugin);

      if ($result === null) {
        continue;
      }

      $touched++;
      $published += $result['published'];
      $skipped += $result['skipped'];
    }

    return ['published' => $published, 'skipped' => $skipped, 'plugins' => $touched];
  }

  /**
   * @return array{published: int, skipped: int}|null null when the plugin ships nothing
   */
  public function publish(PluginDefinition $plugin): ?array
  {
    $installPath = $plugin->installPathValue();

    if ($installPath === null || $installPath === '') {
      return null;
    }

    $source = realpath(rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::SOURCE_DIRECTORY);

    if ($source === false || ! is_dir($source)) {
      return null;
    }

    $target = $this->targetFor($plugin->handle());
    $published = 0;
    $skipped = 0;

    /*
     * Cleared first so a file removed from a plugin release stops being served.
     * Publishing over the top would leave the previous version's scripts reachable
     * indefinitely, which is the failure that makes "we removed that" untrue.
     */
    File::deleteDirectory($target);
    File::ensureDirectoryExists($target);

    foreach (File::allFiles($source, false) as $file) {
      if ($this->isPublishable($file, $source)) {
        if ($this->copy($file, $source, $target)) {
          $published++;
        } else {
          $skipped++;
        }

        continue;
      }

      $skipped++;
    }

    return ['published' => $published, 'skipped' => $skipped];
  }

  /**
   * Remove a plugin's published files.
   *
   * Called on uninstall. Leaving them would serve the assets of a plugin that is no
   * longer installed, from a path whose name still claims it is.
   */
  public function unpublish(string $handle): void
  {
    /*
     * A handle validator, not the dotted widget-key one — that rejects every real
     * handle, which would have made this a silent no-op and left an uninstalled
     * plugin's scripts being served for ever.
     */
    if (! PluginDefinition::isValidHandle($handle)) {
      return;
    }

    File::deleteDirectory($this->targetFor($handle));
  }

  /**
   * The public path a plugin's assets are served from.
   */
  public function publicPathFor(string $handle): string
  {
    return '/cms/plugins/'.$handle;
  }

  /**
   * Resolve one safe file from an installed plugin's public source directory.
   *
   * The web route uses this only when the preferred document-root copy is absent.
   * Keeping resolution here ensures the copy path and fallback path share the same
   * extension, dotfile, symlink and directory-boundary checks.
   */
  public function sourceFileFor(PluginDefinition $plugin, string $relative): ?string
  {
    if ($relative === '' || trim($relative, '/') !== $relative || str_contains($relative, "\0") || str_contains($relative, '\\')) {
      return null;
    }

    $segments = explode('/', $relative);

    if (array_any($segments, static fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.'))) {
      return null;
    }

    $installPath = $plugin->installPathValue();

    if ($installPath === null || $installPath === '') {
      return null;
    }

    $source = realpath(rtrim($installPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::SOURCE_DIRECTORY);

    if ($source === false || ! is_dir($source)) {
      return null;
    }

    $candidate = $source.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);

    if (is_link($candidate)) {
      return null;
    }

    $real = realpath($candidate);

    if ($real === false || ! is_file($real)) {
      return null;
    }

    $file = new SplFileInfo($real);

    return $this->isPublishable($file, $source) ? $real : null;
  }

  private function targetFor(string $handle): string
  {
    return public_path('cms'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.$handle);
  }

  private function isPublishable(SplFileInfo $file, string $source): bool
  {
    /*
     * A symlink inside the source directory is a path out of it, and `realpath`
     * below would resolve it to wherever it points — so it is refused outright
     * rather than resolved and argued about.
     */
    if (is_link($file->getPathname())) {
      return false;
    }

    $real = realpath($file->getPathname());

    if ($real === false || ! str_starts_with($real, $source.DIRECTORY_SEPARATOR)) {
      return false;
    }

    // A dotfile is never something a plugin meant to serve, and `.env` is the one
    // that matters.
    if (str_starts_with($file->getFilename(), '.')) {
      return false;
    }

    return in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true);
  }

  private function copy(SplFileInfo $file, string $source, string $target): bool
  {
    $relative = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
    $destination = $target.DIRECTORY_SEPARATOR.$relative;

    File::ensureDirectoryExists(dirname($destination));

    try {
      return File::copy($file->getPathname(), $destination) && is_file($destination);
    } catch (Throwable) {
      /*
       * A document root that cannot be written is an operator problem — a read-only
       * deploy, a permissions mistake — and not a reason to fail an enable. The
       * plugin falls back to the guarded Laravel asset route. The failed copy is
       * reported as skipped rather than counted as a successful publication.
       */
      return false;
    }
  }
}
