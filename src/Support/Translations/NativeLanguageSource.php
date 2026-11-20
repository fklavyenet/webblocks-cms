<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Support\Arr;

final class NativeLanguageSource
{
  public function __construct(private readonly PanelLanguageSource $panel) {}

  public function groups(): array
  {
    $groups = [];
    foreach ($this->directories() as $namespace => $directory) {
      foreach (glob($directory.'/*.php') ?: [] as $file) {
        $groups[$namespace.'::'.basename($file, '.php')] = Arr::dot(require $file);
      }
    }

    return $groups;
  }

  public function group(string $name): array
  {
    [$namespace, $group] = explode('::', $name, 2);
    $directory = $this->directories()[$namespace] ?? null;
    if ($directory === null || ! preg_match('/\A[a-z][a-z0-9_-]*\z/', $group) || ! is_file($file = $directory.'/'.$group.'.php')) {
      return [];
    }

    return Arr::dot(require $file);
  }

  public function validTranslation(string $group, string $english, string $translation): bool
  {
    if (! mb_check_encoding($translation, 'UTF-8') || mb_strlen($translation) > 4000 || (trim($translation) === '' && $translation !== $english)
      || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\p{Cf}/u', $translation)
      || $this->panel->placeholders($english) !== $this->panel->placeholders($translation)) {
      return false;
    }

    $markup = '/<[^<>]+>|[<>]|&(?:#\d+|#x[\da-fA-F]+|[A-Za-z][A-Za-z0-9]+);/u';
    preg_match_all($markup, $english, $sourceMarkup);
    preg_match_all($markup, $translation, $translatedMarkup);
    if ($sourceMarkup[0] !== $translatedMarkup[0] || $this->mailTokens($english) !== $this->mailTokens($translation)) {
      return false;
    }

    if ($group === 'webblocks-cms::notifications' && str_contains($english, '|')) {
      $forms = explode('|', $translation);

      // The locale's selector determines how many forms it needs.
      return count($forms) >= 1 && count($forms) <= 6
        && collect($forms)->every(fn ($form) => trim($form) !== '' && $this->panel->placeholders($form) === $this->panel->placeholders($english));
    }

    return substr_count($english, '|') === substr_count($translation, '|');
  }

  private function mailTokens(string $text): array
  {
    preg_match_all('/\{[A-Za-z_][A-Za-z0-9_]*\}/', $text, $tokens);
    sort($tokens[0]);

    return $tokens[0];
  }

  private function directories(): array
  {
    return [
      'webblocks-cms' => dirname(__DIR__, 3).'/resources/lang/en',
      'publisher-client' => dirname(__DIR__).'/Updates/Client/Resources/lang/en',
    ];
  }
}
