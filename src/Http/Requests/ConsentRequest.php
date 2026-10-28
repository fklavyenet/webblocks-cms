<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ConsentService;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\ConsentPolicy;
use WebBlocks\Cms\Support\Consent\ConsentPresets;

class ConsentRequest extends FormRequest
{
  public function authorize(): bool
  {
    $site = $this->route('site');
    if (! $site instanceof Site || ! app(ConsentPolicy::class)->access($this, $site, ! $this->isMethodSafe())) {
      return false;
    }
    $service = ConsentService::query()->where('site_id', $site->id)->where('handle', $this->route('service'))->first();
    $codeChange = ($this->exists('script_url') && $this->input('script_url') !== $service?->script_url)
      || ($this->input('category') === 'necessary' && $service?->category !== 'necessary')
      || ($service?->script_url && (($this->exists('enabled') && $this->boolean('enabled') !== $service->enabled) || ($this->exists('category') && $this->input('category') !== $service->category)));

    return (! $codeChange && ! ($this->isMethod('DELETE') && $service?->script_url)) || app(ConsentPolicy::class)->code($this);
  }

  protected function prepareForValidation(): void
  {
    if ($this->boolean('_clear_cookies')) {
      $this->merge(['cookies' => []]);
    }
  }

  public function rules(): array
  {
    if ($this->isMethodSafe() || $this->isMethod('DELETE')) {
      return [];
    }
    $route = (string) $this->route()?->getName();
    if (str_contains($route, 'services.')) {
      return [
        'provider' => ['sometimes', Rule::in([...array_keys(app(ConsentPresets::class)->all()), 'custom'])],
        'name' => ['sometimes', 'required', 'string', 'max:160'],
        'description' => ['nullable', 'string', 'max:2000'],
        'category' => ['sometimes', Rule::in(ConsentPresets::CATEGORIES)],
        'enabled' => ['sometimes', 'boolean'],
        'privacy_url' => ['nullable', 'url:https', 'max:2048'],
        'script_url' => ['nullable', 'url:https', 'max:2048', 'not_regex:/[\s<>"\']/'],
        'cookies' => ['sometimes', 'array', 'max:20'],
        'cookies.*' => ['array:name,path,duration'],
        'cookies.*.name' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_-]+$/D'],
        'cookies.*.path' => ['required', 'string', 'max:255', 'regex:~^/[^;\s\x00-\x1F\x7F]*$~D'],
        'cookies.*.duration' => ['nullable', 'string', 'max:160'],
      ];
    }
    if (str_contains($route, 'texts.')) {
      return ['locale' => ['required', 'string', 'max:16'], 'texts' => ['required', 'array', 'max:100'], 'texts.*' => ['nullable', 'string', 'max:2000']];
    }

    return ['enabled' => ['sometimes', 'boolean'], 'lifetime_days' => ['sometimes', 'integer', 'between:1,730'],
      'privacy_url' => ['nullable', 'string', 'max:2048'], 'position' => ['sometimes', Rule::in(['bottom', 'top'])]];
  }

  public function after(): array
  {
    return [function ($validator): void {
      if ($this->isMethodSafe()) {
        return;
      }
      $allowed = array_merge(array_keys($this->rules()), ['_token', '_method', '_consent_service', '_consent_tab', '_new_handle', '_clear_cookies']);
      if ($this->routeIs('admin.consent.*')) {
        $allowed = array_merge($allowed, ['_consent_setting', '_consent_text']);
      }
      if (array_diff(array_keys($this->all()), $allowed) !== []) {
        $validator->errors()->add('consent', __('webblocks-cms::consent.invalid_fields'));
      }
      $site = $this->route('site');
      $service = $this->route('service');
      if ($service !== null && (! is_string($service) || ! preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $service))) {
        $validator->errors()->add('service', __('webblocks-cms::consent.invalid_service'));
      }
      if ($service && ! isset(app(ConsentPresets::class)->all()[$service]) && ConsentService::query()->where('site_id', $site->id)->count() >= 16
        && ! ConsentService::query()->where('site_id', $site->id)->where('handle', $service)->exists()) {
        $validator->errors()->add('service', __('webblocks-cms::consent.service_limit'));
      }
      if (is_string($this->input('locale')) && $this->filled('locale') && ! $site->hasEnabledLocale($this->string('locale')->toString())) {
        $validator->errors()->add('locale', __('webblocks-cms::consent.invalid_locale'));
      }
      $cookiePairs = [];
      foreach ((array) $this->input('cookies', []) as $cookie) {
        if (! is_array($cookie) || ! is_string($cookie['name'] ?? null) || ! is_string($cookie['path'] ?? null)) {
        continue;
        }
        $pair = ($cookie['name'] ?? '').'|'.($cookie['path'] ?? '');
        if (isset($cookiePairs[$pair])) {
          $validator->errors()->add('cookies', __('webblocks-cms::consent.invalid_fields'));
        }
        $cookiePairs[$pair] = true;
        $name = $cookie['name'] ?? '';
        if (in_array($name, [config('session.cookie'), 'XSRF-TOKEN', config('cms.visitor_reports.consent_cookie_name', 'webblocks_visitor_consent')], true) || str_starts_with($name, 'webblocks_consent_') || str_starts_with($name, 'remember_')) {
          $validator->errors()->add('cookies', __('webblocks-cms::consent.invalid_fields'));
        }
      }
      if (is_string($this->input('script_url')) && $this->filled('script_url') && (parse_url($this->input('script_url'), PHP_URL_USER) !== null || parse_url($this->input('script_url'), PHP_URL_PASS) !== null)) {
        $validator->errors()->add('script_url', __('webblocks-cms::consent.invalid_url'));
      }
      if ($this->exists('texts')) {
        $keys = ['title', 'body', 'preferences_title', 'preferences_body'];
        foreach (array_keys((array) $this->input('texts')) as $key) {
          if (! in_array($key, $keys, true) && ! preg_match('/^service\.[a-z][a-z0-9-]{0,39}\.(name|description)$/D', $key)) {
            $validator->errors()->add('texts', __('webblocks-cms::consent.invalid_fields'));
          }
        }
      }
      if (! str_contains((string) $this->route()?->getName(), 'services.') && is_string($this->input('privacy_url')) && $this->filled('privacy_url') && (Block::safePublicUrl($this->input('privacy_url')) === null || str_contains($this->input('privacy_url'), '\\') || (! str_starts_with($this->input('privacy_url'), '/') && ! str_starts_with($this->input('privacy_url'), 'https://')))) {
        $validator->errors()->add('privacy_url', __('webblocks-cms::consent.invalid_url'));
      }
    }];
  }
}
