<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\SiteSeoPolicy;

final class SiteSeoAuditRequest extends FormRequest
{
  public function authorize(): bool
  {
    $site = $this->route('site');
    $audit = $this->route('seoAudit');
    if (! $site instanceof Site || ! app(SiteSeoPolicy::class)->access($this, $site)) {
      return false;
    }
    abort_if($audit instanceof SeoAudit && $audit->site_id !== (int) $site->id, 404);

    return true;
  }

  public function rules(): array
  {
    return [
      'audit' => ['sometimes', 'integer', 'min:1'],
      'tab' => ['sometimes', Rule::in(['pages', 'findings', 'links'])],
      'locale' => ['nullable', 'string', Rule::in($this->route('site')->enabledLocales()->pluck('code')->all())],
      'page_id' => ['nullable', 'integer', 'min:0'],
      'status' => ['nullable', Rule::in(['draft', 'in_review', 'published', 'archived', 'navigation'])],
      'category' => ['nullable', Rule::in(['technical', 'editorial', 'not_checked'])],
      'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z_]+$/D'],
      'link_state' => ['nullable', Rule::in(['ok', 'external', 'outside_scope', 'automatic_not_checked', 'missing_page', 'unpublished_target', 'protected_target', 'wrong_locale', 'old_url', 'missing_target_translation'])],
      'per_page' => ['sometimes', 'integer', 'between:1,100'],
      'pages_page' => ['sometimes', 'integer', 'min:1'],
      'findings_page' => ['sometimes', 'integer', 'min:1'],
      'links_page' => ['sometimes', 'integer', 'min:1'],
    ];
  }

  protected function prepareForValidation(): void
  {
    if (is_string($this->input('locale')) && $this->input('locale') !== '') {
      $this->merge(['locale' => Locale::normalizeCode($this->input('locale')) ?? trim($this->input('locale'))]);
    }
  }

  public function after(): array
  {
    return [function ($validator): void {
      if ($this->routeIs('admin.sites.seo-audits.store', 'internal-content-api.sites.seo-audits.store') && ! $this->route('site')->enabledLocales()->exists()) {
        $validator->errors()->add('locale', __('webblocks-cms::seo.locale_unavailable'));
      }
    }];
  }

  protected function getRedirectUrl(): string
  {
    $tab = $this->input('tab');
    $parameters = ['site' => $this->route('site'), 'tab' => in_array($tab, ['pages', 'findings', 'links'], true) ? $tab : 'findings'];
    $audit = $this->input('audit');
    if (is_scalar($audit) && ctype_digit((string) $audit) && SeoAudit::query()->where('site_id', $this->route('site')->id)->whereKey($audit)->exists()) {
      $parameters['audit'] = $audit;
    }

    return route('admin.sites.seo-audits.index', $parameters);
  }
}
