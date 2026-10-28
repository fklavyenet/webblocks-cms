<?php

namespace WebBlocks\Cms\Support\Consent;

final class ConsentPresets
{
  public const CATEGORIES = ['necessary', 'preferences', 'analytics', 'marketing', 'external'];

  public function all(): array
  {
    return [
      'cms-analytics' => ['category' => 'analytics', 'name' => 'CMS visitor measurement', 'privacy_url' => null],
      'youtube' => ['category' => 'external', 'name' => 'YouTube', 'privacy_url' => 'https://policies.google.com/privacy'],
      'google-maps' => ['category' => 'external', 'name' => 'Google Maps', 'privacy_url' => 'https://policies.google.com/privacy'],
      'vimeo' => ['category' => 'external', 'name' => 'Vimeo', 'privacy_url' => 'https://vimeo.com/privacy'],
    ] + app(ConsentIntegrations::class)->all();
  }

  public function embed(string $value): ?array
  {
    // Accept Google's copied iframe only as input. Never render its HTML or attributes.
    if (str_contains($value, '<')) {
      if (! preg_match('/^\s*<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>\s*<\/iframe>\s*$/i', $value, $match)) {
        return null;
      }
      $value = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $value = trim($value);
    $parts = parse_url($value);
    if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
      return null;
    }
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    if (in_array($host, ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be'], true)) {
      parse_str($parts['query'] ?? '', $query);
      $id = $host === 'youtu.be' ? trim($path, '/') : ($query['v'] ?? null);
      if (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})/?$~', $path, $match)) {
        $id = $match[1];
      }
      if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return ['service' => 'youtube', 'url' => 'https://www.youtube-nocookie.com/embed/'.$id];
      }
    }
    if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true) && preg_match('~^/(?:video/)?([0-9]+)/?$~', $path, $match)) {
      return ['service' => 'vimeo', 'url' => 'https://player.vimeo.com/video/'.$match[1].'?dnt=1'];
    }
    if (in_array($host, ['www.google.com', 'google.com'], true) && preg_match('~^/maps/embed(?:/v1/(?:place|view|directions|streetview|search))?/?$~', $path)) {
      return ['service' => 'google-maps', 'url' => $value];
    }

    return null;
  }
}
