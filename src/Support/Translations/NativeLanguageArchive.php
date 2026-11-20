<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use JsonException;
use UnexpectedValueException;
use WebBlocks\Cms\Support\WebBlocks;
use ZipArchive;

final class NativeLanguageArchive
{
  public const MAX_BYTES = 4194304;

  private const MANIFEST = 'webblocks-language.json';

  public function __construct(
    private readonly NativeLanguageSource $source,
    private readonly LanguageArrayLiteral $literal,
  ) {}

  public function read(string $file, string $code): array
  {
    $zip = new ZipArchive;
    if ($zip->open($file, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
      $this->invalid();
    }

    try {
      $source = $this->source->groups();
      $entries = $this->entries($zip, $code, array_keys($source));
      $meta = json_decode($entries[self::MANIFEST] ?? '', true, 4, JSON_THROW_ON_ERROR);
      $name = is_array($meta) ? ($meta['name'] ?? null) : null;
      if (($meta['format_version'] ?? null) !== 1 || ($meta['locale'] ?? null) !== $code
        || ($meta['source_version'] ?? null) !== WebBlocks::VERSION || ($meta['requires_native_language_packs'] ?? null) !== true
        || ! is_string($name) || ! preg_match('/\A[\p{L}\p{N}][\p{L}\p{N}\p{M} ._()\x{2019}\x{0027}-]{0,99}\z/u', $name)
        || trim($entries['lang/vendor/webblocks-cms/'.$code.'/webblocks-language.txt'] ?? '') !== $name) {
        $this->invalid();
      }

      $lines = [];
      foreach ($source as $group => $english) {
        $path = $this->path($code, $group);
        $translation = Arr::dot($this->literal->read($entries[$path] ?? ''));
        if (count($english) !== count($translation) || array_diff_key($english, $translation) !== []) {
          $this->invalid();
        }

        foreach ($english as $key => $text) {
          $line = $translation[$key];
          if (! is_string($line) || ! $this->source->validTranslation($group, $text, $line)) {
            $this->invalid();
          }
          $lines[] = ['language_code' => $code, 'catalog_group' => $group, 'translation_key' => $key,
            'source_hash' => hash('sha256', $text), 'translation' => $line];
        }
      }

      if (($meta['message_count'] ?? null) !== count($lines)) {
        $this->invalid();
      }

      return ['name' => $name, 'lines' => $lines];
    } catch (JsonException|UnexpectedValueException) {
      $this->invalid();
    } finally {
      $zip->close();
    }
  }

  private function entries(ZipArchive $zip, string $code, array $groups): array
  {
    $allowed = [
      self::MANIFEST => 8192,
      'README.md' => 65536,
      'LICENSE' => 65536,
      'lang/vendor/webblocks-cms/'.$code.'/webblocks-language.txt' => 401,
    ];
    foreach ($groups as $group) {
      $allowed[$this->path($code, $group)] = self::MAX_BYTES;
    }
    if ($zip->numFiles > count($allowed)) {
      $this->invalid();
    }

    $files = [];
    $total = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $entry = $zip->statIndex($i);
      $name = $entry['name'] ?? '';
      $size = $entry['size'] ?? 0;
      $total += $size;
      if (! isset($allowed[$name]) || isset($files[$name]) || $size < 1 || $size > $allowed[$name]
        || $total > self::MAX_BYTES || ($entry['encryption_method'] ?? 0) !== 0
        || ! $zip->getExternalAttributesIndex($i, $os, $attributes)
        || ($os === ZipArchive::OPSYS_UNIX && ! in_array(($attributes >> 16) & 0170000, [0, 0100000], true))
        || ($content = $zip->getFromIndex($i, $size + 1)) === false || strlen($content) !== $size) {
        $this->invalid();
      }

      $files[$name] = $content;
    }

    return $files;
  }

  private function path(string $code, string $group): string
  {
    [$namespace, $file] = explode('::', $group);

    return 'lang/vendor/'.$namespace.'/'.$code.'/'.$file.'.php';
  }

  private function invalid(): never
  {
    throw ValidationException::withMessages(['catalog' => __('webblocks-cms::panel-languages.extensions_invalid_package')]);
  }
}
