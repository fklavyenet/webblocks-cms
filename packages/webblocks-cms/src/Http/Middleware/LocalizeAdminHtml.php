<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class LocalizeAdminHtml
{
  private const TRANSLATABLE_ATTRIBUTES = [
    'aria-label',
    'data-wb-admin-dirty-close-confirm',
    'placeholder',
    'title',
  ];

  public function __construct(private readonly AdminLocaleResolver $localeResolver) {}

  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);
    $locale = $this->localeResolver->locale($request->user());

    if ($locale === 'en' || ! $this->shouldLocalize($request, $response)) {
      return $response;
    }

    $phrases = $this->phrases($locale);

    if ($phrases === []) {
      return $response;
    }

    $content = $response->getContent();

    if (! is_string($content) || $content === '') {
      return $response;
    }

    $response->setContent($this->localizeHtml($content, $phrases));

    return $response;
  }

  private function shouldLocalize(Request $request, Response $response): bool
  {
    if (! $response->isSuccessful() || ! $request->is('webadmin*') || $request->is('webadmin/api*')) {
      return false;
    }

    $contentType = (string) $response->headers->get('Content-Type', '');

    return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
  }

  private function phrases(string $locale): array
  {
    $phrases = trans(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.html', [], $locale);

    if (! is_array($phrases)) {
      return [];
    }

    $phrases = array_filter($phrases, fn ($value, $key): bool => is_string($key) && is_string($value) && $key !== '' && $value !== '', ARRAY_FILTER_USE_BOTH);
    uksort($phrases, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return $phrases;
  }

  private function localizeHtml(string $html, array $phrases): string
  {
    $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

    if (! is_array($parts)) {
      return $html;
    }

    $skipDepth = 0;

    foreach ($parts as $index => $part) {
      if ($part === '') {
        continue;
      }

      if ($part[0] === '<') {
        $lower = strtolower($part);

        if (preg_match('/^<\s*(script|style|code|pre|textarea)\b/i', $part) === 1) {
          $skipDepth++;
        } elseif (preg_match('/^<\s*\/\s*(script|style|code|pre|textarea)\s*>/i', $part) === 1) {
          $skipDepth = max(0, $skipDepth - 1);
        }

        if ($skipDepth === 0 && ! str_starts_with($lower, '<!')) {
          $parts[$index] = $this->localizeAttributes($part, $phrases);
        }

        continue;
      }

      if ($skipDepth === 0) {
        $parts[$index] = $this->localizeTextNode($part, $phrases);
      }
    }

    return implode('', $parts);
  }

  private function localizeAttributes(string $tag, array $phrases): string
  {
    $attributes = implode('|', array_map(fn (string $attribute): string => preg_quote($attribute, '/'), self::TRANSLATABLE_ATTRIBUTES));

    return (string) preg_replace_callback(
      '/\b('.$attributes.')="([^"]*)"/i',
      fn (array $matches): string => $matches[1].'="'.e($this->translatePhrase(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $phrases), false).'"',
      $tag,
    );
  }

  private function localizeTextNode(string $text, array $phrases): string
  {
    if (trim($text) === '') {
      return $text;
    }

    if (preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $matches) !== 1) {
      return $text;
    }

    return $matches[1].$this->translatePhrase($matches[2], $phrases).$matches[3];
  }

  private function translatePhrase(string $value, array $phrases): string
  {
    if (isset($phrases[$value])) {
      return $phrases[$value];
    }

    foreach ($phrases as $english => $translated) {
      $value = preg_replace(
        '/(?<![A-Za-z0-9_])'.preg_quote($english, '/').'(?![A-Za-z0-9_])/',
        $translated,
        $value,
      ) ?? $value;
    }

    return $value;
  }
}
