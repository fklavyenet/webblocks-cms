<?php

namespace WebBlocks\Cms\Actions\System;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;

final class UpdateVerificationEmailTemplates
{
  public function execute(string $locale, array $values, bool $reset = false): void
  {
    DB::transaction(function () use ($locale, $values, $reset): void {
      foreach (VerificationEmailTemplates::FIELDS as $field) {
        $key = VerificationEmailTemplates::key($locale, $field);
        if ($reset) {
          SystemSetting::query()->where('key', $key)->delete();
        } else {
          SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => trim($values[$field])]);
        }
      }
    });
  }
}
