<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageDesign;
use WebBlocks\Cms\Support\ContentSources\PublicationSources;
use WebBlocks\Cms\Support\Pages\PagePath;

final class AutomaticPageRequest extends FormRequest
{
  public function authorize(): bool
  {
    $rule = $this->route('automaticPage');
    $site = Site::query()->find($this->integer('site_id'));

    return $site && ($rule instanceof AutomaticPageRule
      ? $this->user()?->can('update', $rule) && (int) $rule->site_id === (int) $site->id
      : $this->user()?->can('create', [AutomaticPageRule::class, $site]));
  }

  public function rules(): array
  {
    $rule = $this->route('automaticPage');
    $page = fn () => Rule::exists('wbcms_pages', 'id')->where('site_id', $this->integer('site_id'))->whereNot('page_type', Page::TYPE_SHARED_SLOT_SOURCE);

    return ['name' => ['required', 'string', 'max:160'], 'site_id' => ['required', 'integer'],
      'source_handle' => ['required', 'string', 'max:160'], 'template_page_id' => ['required', 'integer', $page()],
      'catalogue_page_id' => ['nullable', 'integer', $page()], 'base_path' => ['required', 'string', 'max:190',
        Rule::unique('wbcms_automatic_page_rules')->where('site_id', $this->integer('site_id'))->ignore($rule?->id)],
      'revision' => [$rule ? 'required' : 'nullable', 'integer', 'min:1']];
  }

  public function after(): array
  {
    return [function ($validator): void {
      if ($validator->errors()->isNotEmpty()) {
        return;
      }
      if (array_diff(array_keys($this->all()), ['_token', '_method', 'name', 'site_id', 'source_handle', 'template_page_id', 'catalogue_page_id', 'base_path', 'revision']) !== []) {
        $validator->errors()->add('name', __('webblocks-cms::automatic-pages.invalid_fields'));
      }
      try {
        $path = PagePath::canonicalize($this->input('base_path'));
        if ($path === '/' || $path !== $this->input('base_path') || PagePath::isReserved($path)) {
          throw new \InvalidArgumentException;
        }
      } catch (\InvalidArgumentException) {
        $validator->errors()->add('base_path', __('webblocks-cms::automatic-pages.invalid_path'));
      }
      $site = Site::query()->findOrFail($this->integer('site_id'));
      $page = Page::query()->visibleInAdmin()->where('site_id', $site->id)->find($this->integer('template_page_id'));
      $configuration = $page ? app(PublicationSources::class)->find($this->input('source_handle'), $site, $this->user(), $page) : null;
      if (! $configuration) {
        $validator->errors()->add('source_handle', __('webblocks-cms::automatic-pages.source_unavailable'));
      }
      if ($configuration && app(AutomaticPageDesign::class)->connections($page, $configuration['entity']) === []) {
        $validator->errors()->add('template_page_id', __('webblocks-cms::automatic-pages.template_binding'));
      }
      if ($page && $site->enabledLocales()->whereNotIn('wbcms_locales.id', $page->translations()->pluck('locale_id'))->exists()) {
        $validator->errors()->add('template_page_id', __('webblocks-cms::automatic-pages.template_languages'));
      }
    }];
  }
}
