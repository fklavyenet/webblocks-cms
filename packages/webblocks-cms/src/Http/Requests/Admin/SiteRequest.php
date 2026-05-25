<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteHandle;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $site = $this->route('site');
    $site = $site instanceof Site ? $site : null;
    $domain = app(SiteDomainNormalizer::class)->normalize($this->input('domain'));
    $defaultLocaleId = (int) Locale::query()->where('is_default', true)->value('id');
    $submittedLocaleIds = collect($this->input('locale_ids', []))
      ->map(fn ($id) => (int) $id)
      ->filter(fn ($id) => $id > 0);
    $authorization = app(AdminAuthorization::class);
    $user = $this->user();
    $rawHandle = trim((string) $this->input('handle'));
    $name = trim((string) $this->input('name'));
    $normalizedHandle = $rawHandle !== ''
          ? SiteHandle::normalize($rawHandle)
          : ($site?->exists ? (string) $site->handle : SiteHandle::normalize($name));

    $this->merge([
      'is_primary' => $this->boolean('is_primary'),
      'name' => $name,
      'handle' => $normalizedHandle,
      'domain' => $domain,
      'locale_ids' => $this->normalizedLocaleIds($submittedLocaleIds, $site, $defaultLocaleId)->all(),
      'display_name' => trim((string) $this->input('display_name')),
      'tagline' => trim((string) $this->input('tagline')),
      'contact_recipient_email' => trim((string) $this->input('contact_recipient_email')),
      'seo_title' => trim((string) $this->input('seo_title')),
      'seo_description' => trim((string) $this->input('seo_description')),
      'seo_keywords' => trim((string) $this->input('seo_keywords')),
      'favicon_media_id' => $user ? $authorization->normalizeAllowedMediaId($user, $this->integer('favicon_media_id') ?: $this->integer('favicon_asset_id') ?: null) : null,
      'social_image_media_id' => $user ? $authorization->normalizeAllowedMediaId($user, $this->integer('social_image_media_id') ?: $this->integer('social_image_asset_id') ?: null) : null,
      '_site_tab' => trim((string) $this->input('_site_tab', 'site')),
    ]);
  }

  public function rules(): array
  {
    $site = $this->route('site');
    $site = $site instanceof Site ? $site : null;
    $preservedLocaleIds = $site?->locales()->pluck('locales.id')->map(fn ($id) => (int) $id)->all() ?? [];

    return [
      'name' => ['required', 'string', 'max:255'],
      'handle' => ['required', 'string', 'max:255', 'regex:'.SiteHandle::validationPattern(), Rule::unique(Site::class, 'handle')->ignore($site?->id)],
      'domain' => ['nullable', 'string', 'max:255', Rule::unique(SiteDomain::class, 'domain')->ignore($site?->primaryDomain()?->id)],
      'is_primary' => ['nullable', 'boolean'],
      'display_name' => ['nullable', 'string', 'max:255'],
      'tagline' => ['nullable', 'string', 'max:255'],
      'favicon_media_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
      'contact_recipient_email' => ['nullable', 'email:rfc', 'max:255'],
      'seo_title' => ['nullable', 'string', 'max:255'],
      'seo_description' => ['nullable', 'string', 'max:1000'],
      'seo_keywords' => ['nullable', 'string', 'max:500'],
      'social_image_media_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')],
      'locale_ids' => ['required', 'array', 'min:1'],
      'locale_ids.*' => ['integer', Rule::exists(Locale::class, 'id')->where(fn ($query) => $query
        ->where(fn ($enabled) => $enabled
          ->where('is_enabled', true)
          ->when($preservedLocaleIds !== [], fn ($preserved) => $preserved->orWhereIn('id', $preservedLocaleIds))))],
      '_site_tab' => ['nullable', 'string', 'max:255'],
    ];
  }

  protected function getRedirectUrl(): string
  {
    $site = $this->route('site');

    if ($site instanceof Site) {
      return route('admin.sites.edit', ['site' => $site, 'tab' => $this->input('_site_tab', 'site')]);
    }

    return parent::getRedirectUrl();
  }

  private function normalizedLocaleIds(Collection $submittedLocaleIds, ?Site $site, int $defaultLocaleId): Collection
  {
    $fallbackLocaleIds = $site?->locales()->pluck('locales.id') ?? collect();

    $localeIds = $submittedLocaleIds->isNotEmpty()
          ? $submittedLocaleIds
          : $fallbackLocaleIds;

    if ($defaultLocaleId > 0) {
      $localeIds->push($defaultLocaleId);
    }

    return $localeIds
      ->map(fn ($id) => (int) $id)
      ->filter(fn ($id) => $id > 0)
      ->unique()
      ->values();
  }
}
