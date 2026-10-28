<?php

namespace WebBlocks\Cms\Support\Consent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ConsentService;
use WebBlocks\Cms\Models\ConsentSetting;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class ConsentConfiguration
{
  public function settings(Site $site): array
  {
    $key = 'cms.consent.settings.'.$site->id;
    if (! request()->attributes->has($key)) {
      $record = $this->ready() ? ConsentSetting::query()->find($site->id) : null;
      request()->attributes->set($key, ['enabled' => $record?->enabled ?? true, 'lifetime_days' => $record?->lifetime_days ?? 180,
        'privacy_url' => $record?->privacy_url, 'position' => $record?->position ?? 'bottom']);
    }

    return request()->attributes->get($key);
  }

  public function ready(): bool
  {
    if (request()->attributes->get('cms.consent.schema')) {
      return true;
    }
    $ready = Schema::hasTable('wbcms_consent_services');
    if ($ready) {
      request()->attributes->set('cms.consent.schema', true);
    }

    return $ready;
  }

  /** Presets are package definitions; only site overrides and custom services are persisted. */
  public function services(Site $site, ?string $locale = null): array
  {
    $services = [];
    foreach (app(ConsentPresets::class)->all() as $handle => $preset) {
      $services[$handle] = $preset + ['handle' => $handle, 'provider' => $handle, 'enabled' => true,
        'revision' => 'builtin-1', 'description' => $preset['description'] ?? $this->text($site, 'service_'.$handle, $locale), 'script_url' => null, 'cookies' => []];
    }
    foreach ($this->records($site) as $entry) {
      $record = $entry['record'];
      $services[$record->handle] = $record->only(['handle', 'provider', 'category', 'name', 'description', 'privacy_url', 'script_url', 'enabled', 'revision']);
      $preset = app(ConsentPresets::class)->all()[$record->handle] ?? null;
      if ($preset && isset($preset['revision'])) {
        $services[$record->handle]['revision'] = substr(hash('sha256', $preset['revision'].'|'.$record->revision), 0, 36);
        $services[$record->handle]['script_url'] = $preset['script_url'] ?? null;
      }
      $services[$record->handle]['cookies'] = $entry['cookies'];
    }
    foreach ($services as $handle => &$service) {
      $service['name'] = $this->override($site, 'service.'.$handle.'.name', $locale) ?? ($handle === 'cms-analytics' && $service['name'] === 'CMS visitor measurement' ? $this->text($site, 'measurement', $locale) : $service['name']);
      $service['description'] = $this->override($site, 'service.'.$handle.'.description', $locale) ?? $service['description'];
      $service['used'] = $service['provider'] === 'custom' || $this->used($site, $handle);
    }

    return $services;
  }

  public function publicServices(Site $site, ?string $locale = null): array
  {
    return array_filter($this->services($site, $locale), fn ($service) => $service['enabled'] && $service['used']);
  }

  public function registerEmbed(Site $site, string $handle): void
  {
    $used = request()->attributes->get('cms.consent.embeds', []);
    $used[$site->id][$handle] = true;
    request()->attributes->set('cms.consent.embeds', $used);
  }

  public function text(Site $site, string $key, ?string $locale = null): string
  {
    return $this->override($site, $key, $locale) ?? app(CmsTranslator::class)->get('consent.'.$key, $locale);
  }

  public function override(Site $site, string $key, ?string $locale): ?string
  {
    if (! $this->ready()) {
      return null;
    }
    $locale ??= app()->getLocale();
    $cacheKey = 'cms.consent.texts.'.$site->id;
    if (! request()->attributes->has($cacheKey)) {
      request()->attributes->set($cacheKey, DB::table('wbcms_consent_translations')->where('site_id', $site->id)->get()->groupBy('key')->map(fn ($rows) => $rows->pluck('value', 'locale')->all())->all());
    }
    $rows = request()->attributes->get($cacheKey)[$key] ?? [];

    return $rows[$locale] ?? $rows[app(SystemSettings::class)->defaultLocaleCode()] ?? $rows['en'] ?? null;
  }

  private function records(Site $site): array
  {
    $key = 'cms.consent.records.'.$site->id;
    if (! request()->attributes->has($key)) {
      $records = $this->ready() ? ConsentService::query()->where('site_id', $site->id)->orderBy('id')->get() : collect();
      $cookies = $records->isEmpty() ? collect() : DB::table('wbcms_consent_cookies')->whereIn('service_id', $records->modelKeys())->orderBy('id')->get()->groupBy('service_id');
      request()->attributes->set($key, $records->map(fn ($record) => ['record' => $record,
        'cookies' => ($cookies[$record->id] ?? collect())->map(fn ($row) => ['name' => $row->name, 'path' => $row->path, 'duration' => $row->duration])->all()])->all());
    }

    return request()->attributes->get($key);
  }

  private function used(Site $site, string $handle): bool
  {
    if (isset(app(ConsentIntegrations::class)->all()[$handle])) {
      return true;
    }
    if ($handle === 'cms-analytics') {
      return (bool) config('cms.visitor_reports.enabled', true);
    }
    if (request()->attributes->get('cms.consent.embeds', [])[$site->id][$handle] ?? false) {
      return true;
    }
    // Draft pages never activate a service on the live site. Shared source pages
    // can register the providers actually rendered in the current response above.
    $cacheKey = 'cms.consent.used.'.$site->id;
    if (! request()->attributes->has($cacheKey)) {
      $urls = Block::query()->whereIn('type', ['video', 'external-content'])->where('status', 'published')
        ->where(function ($query) use ($site): void {
          $query->whereHas('page', fn ($pages) => $pages->where('site_id', $site->id)->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)->where('status', Page::STATUS_PUBLISHED))
            ->orWhereIn('id', DB::table('wbcms_shared_slot_blocks as placement')
              ->join('wbcms_shared_slots as shared', 'shared.id', '=', 'placement.shared_slot_id')
              ->join('wbcms_page_slots as slot', 'slot.shared_slot_id', '=', 'shared.id')
              ->join('wbcms_pages as live', 'live.id', '=', 'slot.page_id')
              ->where('shared.site_id', $site->id)->where('shared.is_active', true)
              ->where('live.site_id', $site->id)->where('live.status', Page::STATUS_PUBLISHED)
              ->select('placement.block_id'));
        })->pluck('url');
      request()->attributes->set($cacheKey, $urls->map(fn ($url) => app(ConsentPresets::class)->embed((string) $url)['service'] ?? null)->filter()->unique()->all());
    }

    return in_array($handle, request()->attributes->get($cacheKey), true);
  }
}
