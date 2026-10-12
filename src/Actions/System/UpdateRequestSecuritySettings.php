<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\System\RequestSecuritySettings;

class UpdateRequestSecuritySettings
{
  public function handle(array $data): void
  {
    DB::transaction(function () use ($data): void {
      if ($data['mode'] === 'defaults') {
        SystemSetting::query()->whereIn('key', RequestSecuritySettings::keys())->delete();

        return;
      }

      foreach (RequestSecuritySettings::DEFINITIONS as $field => $definition) {
        SystemSetting::query()->updateOrCreate(
          ['key' => RequestSecuritySettings::key($field)],
          ['value' => (string) $data[$field]],
        );
      }
    });
  }
}
