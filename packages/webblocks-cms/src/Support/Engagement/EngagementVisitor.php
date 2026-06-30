<?php

namespace WebBlocks\Cms\Support\Engagement;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EngagementVisitor
{
  private const SESSION_KEY = 'webblocks.engagement.visitor_id';

  public function visitorHash(Request $request, int $siteId, int $blockId): string
  {
    $visitorId = $request->session()->get(self::SESSION_KEY);

    if (! is_string($visitorId) || $visitorId === '') {
      $visitorId = (string) Str::uuid();
      $request->session()->put(self::SESSION_KEY, $visitorId);
    }

    return hash('sha256', implode('|', [
      config('app.key'),
      $siteId,
      $blockId,
      $visitorId,
    ]));
  }

  public function ipHash(?string $ipAddress): ?string
  {
    if (! $ipAddress) {
      return null;
    }

    return hash('sha256', config('app.key').'|'.$ipAddress);
  }
}
