<?php

namespace WebBlocks\Cms\Actions\Consent;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\ConsentSetting;
use WebBlocks\Cms\Models\Site;

final class UpdateConsentSettings
{
  public function settings(Site $site, array $values): void
  {
    ConsentSetting::query()->updateOrCreate(['site_id' => $site->id], $values);
  }

  public function texts(Site $site, string $locale, array $values): void
  {
    DB::transaction(function () use ($site, $locale, $values): void {
      foreach ($values as $key => $value) {
        $query = DB::table('wbcms_consent_translations')->where(['site_id' => $site->id, 'locale' => $locale, 'key' => $key]);
        if (trim((string) $value) === '') {
          $query->delete();
        } else {
          $query->updateOrInsert(['site_id' => $site->id, 'locale' => $locale, 'key' => $key], ['value' => $value]);
        }
      }
    });
    request()->attributes->remove('cms.consent.texts.'.$site->id);
  }
}
