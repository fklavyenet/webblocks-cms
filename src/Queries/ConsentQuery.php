<?php

namespace WebBlocks\Cms\Queries;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\ConsentPolicy;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Consent\ConsentConfiguration;
use WebBlocks\Cms\Support\Consent\ConsentPresets;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class ConsentQuery
{
  public function workspace(Request $request): array
  {
    $sites = Site::query()->whereIn('id', app(CmsAuthorization::class)->siteIds($request->user(), 'cms.consent.read'))->primaryFirst()->orderBy('name')->get();
    $site = $request->filled('site') ? $sites->firstWhere('id', $request->integer('site')) : ($sites->firstWhere('id', $request->session()->get('cms.consent.site')) ?? $sites->first());
    abort_unless($site, 403);
    $request->session()->put('cms.consent.site', $site->id);

    $data = $this->forSite($site);
    $selectedLocale = $request->session()->getOldInput('locale', $request->input('locale', $request->session()->get('consent_locale')));
    $selectedLocale = is_string($selectedLocale) && $data['locales']->contains('code', $selectedLocale) ? $selectedLocale : ($data['locales']->first()?->code ?? 'en');
    $textRows = $request->routeIs('admin.consent.texts') ? $this->textRows($site, $selectedLocale, $data['translations']) : [];
    $editSetting = $request->session()->getOldInput('_consent_setting', $request->query('edit'));
    $editText = $request->session()->getOldInput('_consent_text', $request->query('edit'));
    if (is_string($editText) && ! isset($textRows[$editText])) {
      foreach ($textRows as $group => $row) {
        if (isset($row['fields'][$editText])) {
          $editText = $group;
          break;
        }
      }
    }
    $resetSite = $sites->first();
    $resetLocale = $resetSite->id === $site->id ? $data['locales']->first()?->code : $resetSite->enabledLocales()->first()?->code;

    return $data + ['sites' => $sites, 'site' => $site, 'canWrite' => app(ConsentPolicy::class)->access($request, $site, true), 'canCode' => app(ConsentPolicy::class)->code($request),
      'selectedLocale' => $selectedLocale, 'textRows' => $textRows,
      'editSetting' => is_string($editSetting) && in_array($editSetting, ['enabled', 'lifetime_days', 'privacy_url', 'position'], true) ? $editSetting : null,
      'editText' => is_string($editText) && isset($textRows[$editText]) ? $editText : null,
      'resetFilters' => ['site' => $resetSite->id, 'locale' => $resetLocale ?? 'en']];
  }

  private function textRows(Site $site, string $locale, Collection $translations): array
  {
    $config = app(ConsentConfiguration::class);
    $translator = app(CmsTranslator::class);
    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $rows = [];
    foreach (['notice' => ['title', 'body'], 'preferences' => ['preferences_title', 'preferences_body']] as $group => $keys) {
      $rows[$group] = ['label' => $translator->get('consent.'.$group.'_group', $adminLocale), 'fields' => []];
      foreach ($keys as $key) {
        $rows[$group]['fields'][$key] = ['label' => $translator->get('consent.text_label_'.$key, $adminLocale), 'value' => $config->text($site, $key, $locale)];
      }
    }
    foreach ($config->services($site, $locale) as $handle => $service) {
      $group = 'service.'.$handle;
      $rows[$group] = ['label' => $service['name'], 'fields' => []];
      foreach (['name', 'description'] as $field) {
        $rows[$group]['fields'][$group.'.'.$field] = ['label' => $translator->get('consent.'.$field, $adminLocale), 'value' => $service[$field] ?? ''];
      }
    }
    $stored = $translations->where('locale', $locale)->pluck('value', 'key');
    foreach ($rows as &$row) {
      $customized = 0;
      foreach ($row['fields'] as $key => &$field) {
        $field['stored'] = $stored->get($key);
        $customized += $stored->has($key) ? 1 : 0;
      }
      unset($field);
      $row['translation_status'] = $customized === 0 ? 'default_value' : ($customized === count($row['fields']) ? 'customized' : 'partly_customized');
    }

    return $rows;
  }

  public function forSite(Site $site): array
  {
    $config = app(ConsentConfiguration::class);

    return ['settings' => $config->settings($site), 'services' => $config->services($site), 'presets' => app(ConsentPresets::class)->all(),
      'locales' => $site->enabledLocales()->get(), 'translations' => DB::table('wbcms_consent_translations')->where('site_id', $site->id)->orderBy('locale')->get(['locale', 'key', 'value'])];
  }
}
