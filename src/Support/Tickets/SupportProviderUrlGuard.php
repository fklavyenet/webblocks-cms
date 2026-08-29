<?php

namespace WebBlocks\Cms\Support\Tickets;

final class SupportProviderUrlGuard
{
  public function normalize(string $url): string
  {
    return $this->normalizeUrl($url, false);
  }

  public function normalizeNavigationUrl(string $url): string
  {
    return $this->normalizeUrl($url, true);
  }

  private function normalizeUrl(string $url, bool $allowQuery): string
  {
    $url = rtrim(trim($url), '/');
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

    if ($scheme !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || (! $allowQuery && isset($parts['query'])) || isset($parts['fragment'])) {
      throw new SupportProviderException('The support provider must be a plain HTTPS origin URL.');
    }

    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
      throw new SupportProviderException('The support provider must not target a local host.');
    }

    foreach ($this->resolve($host) as $ip) {
      if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        throw new SupportProviderException('The support provider resolves to a private or reserved network address.');
      }
    }

    return $url;
  }

  private function resolve(string $host): array
  {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return [$host];
    }

    $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
    $ips = [];

    foreach ($records as $record) {
      foreach (['ip', 'ipv6'] as $key) {
        if (isset($record[$key]) && is_string($record[$key])) {
          $ips[] = $record[$key];
        }
      }
    }

    if ($ips === []) {
      throw new SupportProviderException('The support provider host could not be resolved.');
    }

    return array_values(array_unique($ips));
  }
}
