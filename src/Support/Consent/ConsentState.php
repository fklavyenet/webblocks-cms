<?php

namespace WebBlocks\Cms\Support\Consent;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;
use WebBlocks\Cms\Models\Site;

final class ConsentState
{
  public function cookieName(Site $site): string
  {
    return 'webblocks_consent_'.$site->id;
  }

  public function read(Request $request, Site $site, array $services): array
  {
    $grants = [];
    $known = [];
    $status = null;
    $raw = $request->cookie($this->cookieName($site));
    try {
      if (is_string($raw) && $raw !== '') {
        // Compact authenticated browser protocol, never a CMS persistence document.
        $signatureAt = strrpos($raw, '|');
        if ($signatureAt === false || ! hash_equals(hash_hmac('sha256', substr($raw, 0, $signatureAt), (string) config('app.key')), substr($raw, $signatureAt + 1))) {
          throw new \UnexpectedValueException;
        }
        [$version, $siteId, $expires, $status, $items] = array_pad(explode('|', substr($raw, 0, $signatureAt), 5), 5, '');
        if ($version !== '1' || (int) $siteId !== (int) $site->id || (int) $expires <= now()->getTimestamp() || ! in_array($status, ['accepted', 'rejected', 'custom'], true)) {
          throw new \UnexpectedValueException;
        }
        $choices = [];
        foreach (explode(',', $items) as $item) {
          [$fingerprint, $allowed] = array_pad(explode(':', $item, 2), 2, '');
          $choices[$fingerprint] = $allowed === '1';
        }
        foreach ($services as $handle => $service) {
          $fingerprint = $this->fingerprint($handle, $service['revision']);
          if (array_key_exists($fingerprint, $choices)) {
            $known[$handle] = true;
            $grants[$handle] = $choices[$fingerprint];
          }
        }
      }
    } catch (Throwable) {
      $status = null;
    }
    if ($status === null) {
      $grants = [];
      $known = [];
      // A legacy analytics decision authorizes only the existing measurement.
      $legacy = $request->cookie((string) config('cms.visitor_reports.consent_cookie_name', 'webblocks_visitor_consent'));
      if (! $raw && isset($services['cms-analytics']) && $services['cms-analytics']['revision'] === 'builtin-1' && in_array($legacy, ['accepted', 'declined'], true)) {
        $grants['cms-analytics'] = $legacy === 'accepted';
        $known['cms-analytics'] = true;
        $status = $legacy === 'accepted' ? 'accepted' : 'rejected';
      }
    }

    $optional = [];
    foreach ($services as $handle => $service) {
      if ($service['category'] === 'necessary') {
        $grants[$handle] = true;
      } else {
        $optional[$handle] = $service;
      }
    }

    return ['status' => $status, 'services' => $grants, 'needs_choice' => ($status === null && $optional !== []) || array_diff_key($optional, $known) !== []];
  }

  public function allows(Request $request, Site $site, string $handle): bool
  {
    $config = app(ConsentConfiguration::class);

    return $config->settings($site)['enabled'] && ($this->read($request, $site, $config->publicServices($site))['services'][$handle] ?? false);
  }

  public function cookie(Site $site, array $services, array $grants, string $status, int $days): Cookie
  {
    $items = [];
    foreach ($services as $handle => $service) {
      $items[] = $this->fingerprint($handle, $service['revision']).':'.(($service['category'] === 'necessary' || ($grants[$handle] ?? false)) ? '1' : '0');
    }
    $expires = now()->getTimestamp() + $days * 86400;
    $payload = '1|'.$site->id.'|'.$expires.'|'.$status.'|'.implode(',', $items);
    $value = $payload.'|'.hash_hmac('sha256', $payload, (string) config('app.key'));

    return cookie($this->cookieName($site), $value, $days * 1440, '/', null, request()->isSecure(), true, false, 'lax');
  }

  private function fingerprint(string $handle, string $revision): string
  {
    return substr(hash('sha256', $handle.'|'.$revision), 0, 24);
  }
}
