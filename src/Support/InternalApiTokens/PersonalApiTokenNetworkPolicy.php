<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use WebBlocks\Cms\Models\CmsApiToken;

class PersonalApiTokenNetworkPolicy
{
  public function allows(CmsApiToken $token, ?string $ip): bool
  {
    $ranges = array_values(array_filter($token->allowed_ip_ranges ?? []));

    if ($ranges === []) {
      return true;
    }

    return $ip !== null && collect($ranges)->contains(fn (string $range): bool => $this->contains($range, $ip));
  }

  public function valid(string $range): bool
  {
    [$address, $prefix] = array_pad(explode('/', trim($range), 2), 2, null);
    $packed = @inet_pton($address);

    if ($packed === false) {
      return false;
    }

    if ($prefix === null) {
      return true;
    }

    return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= strlen($packed) * 8;
  }

  private function contains(string $range, string $ip): bool
  {
    if (! $this->valid($range)) {
      return false;
    }

    [$address, $prefix] = array_pad(explode('/', trim($range), 2), 2, null);
    $network = inet_pton($address);
    $candidate = @inet_pton($ip);

    if ($candidate === false || strlen($network) !== strlen($candidate)) {
      return false;
    }

    $bits = $prefix === null ? strlen($network) * 8 : (int) $prefix;
    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;

    if (substr($network, 0, $bytes) !== substr($candidate, 0, $bytes)) {
      return false;
    }

    if ($remainder === 0) {
      return true;
    }

    $mask = (0xFF << (8 - $remainder)) & 0xFF;

    return (ord($network[$bytes]) & $mask) === (ord($candidate[$bytes]) & $mask);
  }
}
