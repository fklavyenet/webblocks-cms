<?php

namespace WebBlocks\Cms\Support\Logging;

use Throwable;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\System\SystemSettings;

final class LogFormatSettings
{
  private bool $resolving = false;

  public function mode(): ?string
  {
    if ($this->resolving) {
      return null;
    }
    $this->resolving = true;
    try {
      return app(RequestMemo::class)->remember('system.log-format', [], static function (): ?string {
        try {
          $mode = SystemSetting::query()->where('key', SystemSettings::LOG_FORMAT)->value('value');

          return in_array($mode, ['compact', 'full'], true) ? $mode : null;
        } catch (Throwable) {
          // Logging must remain usable during installation or a database outage.
          return null;
        }
      });
    } finally {
      $this->resolving = false;
    }
  }
}
