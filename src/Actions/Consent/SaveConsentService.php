<?php

namespace WebBlocks\Cms\Actions\Consent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\ConsentService;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Consent\ConsentPresets;

final class SaveConsentService
{
  public function execute(Site $site, string $handle, array $values): ConsentService
  {
    return DB::transaction(function () use ($site, $handle, $values): ConsentService {
      $preset = app(ConsentPresets::class)->all()[$handle] ?? null;
      $record = ConsentService::query()->where('site_id', $site->id)->where('handle', $handle)->lockForUpdate()->first()
        ?? new ConsentService(['site_id' => $site->id, 'handle' => $handle, 'provider' => $preset ? $handle : 'custom', 'name' => $preset['name'] ?? $handle,
          'category' => $preset['category'] ?? 'preferences', 'privacy_url' => $preset['privacy_url'] ?? null, 'enabled' => true]);
      $cookies = $values['cookies'] ?? null;
      unset($values['cookies']);
      $record->fill($values);
      if (($preset && $record->provider !== $handle) || (! $preset && ! $record->exists && $record->provider !== 'custom') || ($record->provider !== 'custom' && $record->script_url)) {
        throw ValidationException::withMessages(['provider' => __('webblocks-cms::consent.invalid_service')]);
      }
      // Revisions change only when the permission's meaning or executable source changes.
      $oldCookies = $record->exists ? DB::table('wbcms_consent_cookies')->where('service_id', $record->id)->orderBy('id')->get(['name', 'path', 'duration'])->map(fn ($cookie) => (array) $cookie)->all() : [];
      if (! $record->exists || $record->isDirty(['provider', 'category', 'enabled', 'script_url']) || ($cookies !== null && $cookies !== $oldCookies)) {
        $record->revision = (string) Str::uuid();
      }
      $record->save();
      if ($cookies !== null) {
        DB::table('wbcms_consent_cookies')->where('service_id', $record->id)->delete();
        foreach ($cookies as $cookie) {
          DB::table('wbcms_consent_cookies')->insert($cookie + ['service_id' => $record->id]);
        }
      }

      return $record;
    });
  }

  public function delete(Site $site, string $handle): void
  {
    // Preset overrides remain tombstones: deletion must never silently re-enable a provider.
    if (isset(app(ConsentPresets::class)->all()[$handle]) || ConsentService::query()->where('site_id', $site->id)->where('handle', $handle)->where('provider', '!=', 'custom')->exists()) {
      $this->execute($site, $handle, ['enabled' => false]);
    } else {
      ConsentService::query()->where('site_id', $site->id)->where('handle', $handle)->delete();
      request()->attributes->remove('cms.consent.records.'.$site->id);
    }
  }
}
