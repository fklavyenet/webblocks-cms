<?php

namespace WebBlocks\Cms\Support\Translations;

use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

class PanelLanguageSource
{
  public const CODE_PATTERN = '[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,2}';

  public function nativeOptions(): array
  {
    return app(RequestMemo::class)->remember('panel.languages.native', [], function (): array {
      $options = AdminLocaleResolver::PACKAGED_OPTIONS;

      foreach (glob(lang_path('vendor/webblocks-cms/*/webblocks-language.txt')) ?: [] as $manifest) {
        $directory = dirname($manifest);
        $code = basename($directory);
        if (isset($options[$code]) || ! $this->validCode($code) || ! is_file($directory.'/admin.php')
          || ! is_file($manifest) || ! is_readable($manifest)) {
          continue;
        }

        $name = trim((string) file_get_contents($manifest, length: 401));
        if ($name !== '' && mb_strlen($name) <= 100 && $this->plainText($name) && ! preg_match('/[\r\n\t]/', $name)) {
          $options[$code] = strtoupper($code).' - '.$name;
        }
      }

      foreach (app(NativeLanguagePacks::class)->installed() as $code => $pack) {
        $options[$code] ??= strtoupper($code).' - '.$pack->name;
      }

      return $options;
    });
  }

  public function validCode(string $code): bool
  {
    return strlen($code) <= 12 && preg_match('/\A'.self::CODE_PATTERN.'\z/', $code) === 1;
  }

  public function plainText(string $value): bool
  {
    return preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $value) === 0;
  }

  public function placeholders(string $value): array
  {
    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
    $placeholders = array_unique($matches[0]);
    sort($placeholders);

    return $placeholders;
  }
}
