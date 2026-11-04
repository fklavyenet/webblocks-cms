<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;

class SystemDiagnosticReports
{
  public const MAX_BYTES = 65536;

  public function prepare(Authenticatable $user, array $categories, ?int $runId = null): string
  {
    return $this->seal($user, app(SystemDiagnosticReportQuery::class)->collect($categories, $runId));
  }

  public function seal(Authenticatable $user, array $report): string
  {
    abort_if(strlen($this->encode($report)) > self::MAX_BYTES, 413);

    return Crypt::encryptString(json_encode(['installation' => hash('sha256', base_path().config('app.url')), 'actor' => (string) $user->getAuthIdentifier(), 'expires' => now()->addMinutes(30)->timestamp, 'report' => $report], JSON_THROW_ON_ERROR));
  }

  public function open(Authenticatable $user, string $envelope): array
  {
    try {
      if (strlen($envelope) > 140000) {
        throw new \RuntimeException;
      }
      $payload = json_decode(Crypt::decryptString($envelope), true, 32, JSON_THROW_ON_ERROR);
      if (($payload['installation'] ?? null) !== hash('sha256', base_path().config('app.url'))
        || ($payload['actor'] ?? null) !== (string) $user->getAuthIdentifier()
        || ($payload['expires'] ?? 0) <= now()->timestamp
        || ! is_array($payload['report'] ?? null)
        || strlen($this->encode($payload['report'])) > self::MAX_BYTES) {
        throw new \RuntimeException;
      }

      return $payload['report'];
    } catch (Throwable) {
      throw ValidationException::withMessages(['report' => __('webblocks-cms::diagnostics.expired')]);
    }
  }

  public function encode(array $report): string
  {
    return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  }
}
