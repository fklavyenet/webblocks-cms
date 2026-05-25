<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class PageRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $page = $this->route('page');
    $page = $page instanceof Page ? $page : null;
    $title = (string) $this->input('title');
    $slug = (string) $this->input('slug');
    $submittedSiteId = $this->input('site_id');

    $this->merge([
      'site_id' => filled($submittedSiteId) ? $submittedSiteId : ($page?->site_id ?? Site::primary()?->id),
      'slug' => Str::slug($slug !== '' ? $slug : $title),
    ]);
  }

  public function rules(): array
  {
    $page = $this->route('page');
    $page = $page instanceof Page ? $page : null;
    $siteId = (int) $this->input('site_id');
    $allowedLayoutHandles = array_values(array_unique(array_filter([
      ...app(PageLayoutManager::class)->activeHandles(),
      $page?->publicShellPreset(),
      'dashboard',
    ])));
    $defaultLocaleId = (int) Locale::query()->where('is_default', true)->value('id');
    $translationId = $page
      ? $page->translations()->where('locale_id', $defaultLocaleId)->value('id')
      : null;

    return [
      'site_id' => ['required', 'integer', 'exists:sites,id'],
      'title' => ['required', 'string', 'max:255'],
      'slug' => [
        'required',
        'string',
        'max:255',
        (function () use ($translationId, $siteId, $defaultLocaleId) {
          $rule = Rule::unique(PageTranslation::class, 'slug')
            ->where(fn ($query) => $query
              ->where('site_id', $siteId)
              ->where('locale_id', $defaultLocaleId)
            );

          return $translationId ? $rule->ignore($translationId) : $rule;
        })(),
      ],
      'public_shell' => ['nullable', Rule::in($allowedLayoutHandles)],
      'blocks' => ['nullable', 'array'],
      'blocks.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
      'blocks.*.block_type_id' => ['required', 'integer', 'exists:block_types,id'],
      'blocks.*.slot_type_id' => ['nullable', 'integer', 'exists:slot_types,id'],
      'blocks.*.sort_order' => ['nullable', 'integer', 'min:0'],
      'blocks.*.title' => ['nullable', 'string', 'max:255'],
      'blocks.*.subtitle' => ['nullable', 'string', 'max:255'],
      'blocks.*.content' => ['nullable', 'string'],
      'blocks.*.url' => ['nullable', 'string', 'max:2048'],
      'blocks.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
      'blocks.*.asset_id' => ['nullable', 'integer', 'exists:media,id'],
      'blocks.*.gallery_media_ids' => ['nullable', 'array'],
      'blocks.*.gallery_media_ids.*' => ['integer', 'exists:media,id'],
      'blocks.*.gallery_asset_ids' => ['nullable', 'array'],
      'blocks.*.gallery_asset_ids.*' => ['integer', 'exists:media,id'],
      'blocks.*.gallery_items' => ['nullable', 'array'],
      'blocks.*.gallery_items.*.media_id' => ['required_with:blocks.*.gallery_items', 'integer', 'exists:media,id'],
      'blocks.*.gallery_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
      'blocks.*.gallery_items.*.alt_text' => ['nullable', 'string', 'max:255'],
      'blocks.*.gallery_items.*.caption' => ['nullable', 'string', 'max:255'],
      'blocks.*.gallery_items.*.overlay_title' => ['nullable', 'string', 'max:255'],
      'blocks.*.gallery_items.*.overlay_text' => ['nullable', 'string'],
      'blocks.*.gallery_variant' => ['nullable', Rule::in(['grid', 'masonry', 'masonary', 'collage'])],
      'blocks.*.gallery_columns' => ['nullable', Rule::in(['2', '3', '4', '5'])],
      'blocks.*.gallery_gap' => ['nullable', Rule::in(['none', 'sm', 'md', 'lg'])],
      'blocks.*.gallery_aspect_ratio' => ['nullable', Rule::in(['auto', 'square', '4:3', '16:9', 'portrait'])],
      'blocks.*.gallery_captions_mode' => ['nullable', Rule::in(['hidden', 'below', 'overlay', 'on-hover'])],
      'blocks.*.gallery_overlay_mode' => ['nullable', Rule::in(['none', 'gradient', 'solid'])],
      'blocks.*.gallery_lightbox_enabled' => ['nullable', 'boolean'],
      'blocks.*.attachment_media_id' => ['nullable', 'integer', 'exists:media,id'],
      'blocks.*.attachment_asset_id' => ['nullable', 'integer', 'exists:media,id'],
      'blocks.*.variant' => ['nullable', 'string', 'max:255'],
      'blocks.*.meta' => ['nullable', 'string'],
      'blocks.*.settings' => ['nullable', 'string'],
      'blocks.*.status' => ['required', Rule::in(['draft', 'published'])],
      'blocks.*.is_system' => ['nullable', 'boolean'],
      'blocks.*._delete' => ['nullable', 'boolean'],
    ];
  }

  public function validatedData(): array
  {
    /** @var AdminAuthorization $authorization */
    $authorization = app(AdminAuthorization::class);
    $data = $this->validated();
    $page = $this->route('page');
    $page = $page instanceof Page ? $page : null;
    $data['page_type'] = 'default';
    $data['status'] = $page instanceof Page ? $page->status : Page::STATUS_DRAFT;
    $existingSettings = $page?->settings;
    $existingSettings = is_array($existingSettings) ? $existingSettings : [];

    if (Page::supportsSettingsColumn()) {
      $data['settings'] = [
        'public_shell' => Page::normalizePublicShellHandle($data['public_shell'] ?? ($existingSettings['public_shell'] ?? 'default')),
      ];
      $data['settings'] = $data['settings'] === [] ? null : $data['settings'];
    } else {
      unset($data['settings']);
    }

    $data['translation'] = [
      'name' => $data['title'],
      'slug' => $data['slug'],
    ];

    $data['blocks'] = collect($data['blocks'] ?? [])
      ->map(function (array $block, int $index) use ($authorization) {
        $blockType = ! empty($block['block_type_id'])
          ? BlockType::query()->find($block['block_type_id'])
          : null;
        $submittedGalleryItems = collect($block['gallery_items'] ?? [])
          ->map(function (array $item, int $galleryIndex): array {
            return [
              'media_id' => (int) ($item['media_id'] ?? 0),
              'sort_order' => (int) ($item['sort_order'] ?? $galleryIndex),
              'alt_text' => trim((string) ($item['alt_text'] ?? '')) ?: null,
              'caption' => trim((string) ($item['caption'] ?? '')) ?: null,
              'overlay_title' => trim((string) ($item['overlay_title'] ?? '')) ?: null,
              'overlay_text' => trim((string) ($item['overlay_text'] ?? '')) ?: null,
            ];
          })
          ->sortBy('sort_order')
          ->values();
        $galleryAssetIds = $authorization->filterAllowedMediaIds(
          $this->user(),
          $submittedGalleryItems->isNotEmpty()
            ? $submittedGalleryItems->pluck('media_id')->all()
            : ($block['gallery_media_ids'] ?? $block['gallery_asset_ids'] ?? []),
        );
        $attachmentAssetId = $authorization->normalizeAllowedMediaId($this->user(), ! empty($block['attachment_media_id']) ? (int) $block['attachment_media_id'] : (! empty($block['attachment_asset_id']) ? (int) $block['attachment_asset_id'] : null));

        $block['settings'] = trim((string) ($block['settings'] ?? '')) ?: null;
        $decodedSettings = [];

        if ($block['settings']) {
          $parsedSettings = json_decode((string) $block['settings'], true);
          $decodedSettings = is_array($parsedSettings) ? $parsedSettings : [];
        }

        $block['settings'] = $decodedSettings === []
          ? null
          : json_encode($decodedSettings, JSON_UNESCAPED_SLASHES);

        $block['meta'] = trim((string) ($block['meta'] ?? '')) ?: null;
        $block['media_id'] = $authorization->normalizeAllowedMediaId($this->user(), ! empty($block['media_id']) ? (int) $block['media_id'] : (! empty($block['asset_id']) ? (int) $block['asset_id'] : null));
        $block['is_system'] = (bool) ($blockType?->is_system ?? false);
        $block['_delete'] = (bool) ($block['_delete'] ?? false);
        $block['sort_order'] = $index;
        $block['_block_media'] = [
          'gallery_item' => $galleryAssetIds,
          'attachment' => $attachmentAssetId ? [$attachmentAssetId] : [],
        ];
        $block['_gallery_items'] = $submittedGalleryItems
          ->filter(fn (array $item) => in_array($item['media_id'], $galleryAssetIds, true))
          ->values()
          ->all();

        if (($blockType?->slug ?? null) === 'gallery') {
          $settings = $decodedSettings;
          $submittedVariant = trim((string) ($block['gallery_variant'] ?? ($settings['variant'] ?? 'grid')));
          $settings['variant'] = match ($submittedVariant) {
            'masonry', 'masonary' => 'masonry',
            'collage' => 'collage',
            default => 'grid',
          };
          $settings['columns'] = in_array(trim((string) ($block['gallery_columns'] ?? ($settings['columns'] ?? '3'))), ['2', '3', '4', '5'], true)
            ? trim((string) ($block['gallery_columns'] ?? ($settings['columns'] ?? '3')))
            : '3';
          $settings['gap'] = in_array(trim((string) ($block['gallery_gap'] ?? ($settings['gap'] ?? 'md'))), ['none', 'sm', 'md', 'lg'], true)
            ? trim((string) ($block['gallery_gap'] ?? ($settings['gap'] ?? 'md')))
            : 'md';
          $settings['aspect_ratio'] = in_array(trim((string) ($block['gallery_aspect_ratio'] ?? ($settings['aspect_ratio'] ?? 'auto'))), ['auto', 'square', '4:3', '16:9', 'portrait'], true)
            ? trim((string) ($block['gallery_aspect_ratio'] ?? ($settings['aspect_ratio'] ?? 'auto')))
            : 'auto';
          $settings['captions_mode'] = in_array(trim((string) ($block['gallery_captions_mode'] ?? ($settings['captions_mode'] ?? 'below'))), ['hidden', 'below', 'overlay', 'on-hover'], true)
            ? trim((string) ($block['gallery_captions_mode'] ?? ($settings['captions_mode'] ?? 'below')))
            : 'below';
          $settings['overlay_mode'] = in_array(trim((string) ($block['gallery_overlay_mode'] ?? ($settings['overlay_mode'] ?? 'gradient'))), ['none', 'gradient', 'solid'], true)
            ? trim((string) ($block['gallery_overlay_mode'] ?? ($settings['overlay_mode'] ?? 'gradient')))
            : 'gradient';
          $settings['lightbox_enabled'] = array_key_exists('gallery_lightbox_enabled', $block)
            ? (bool) $block['gallery_lightbox_enabled']
            : (bool) ($settings['lightbox_enabled'] ?? true);
          $block['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);
          $block['title'] = null;
          $block['subtitle'] = null;
          $block['content'] = null;
          $block['url'] = null;
          $block['variant'] = null;
          $block['meta'] = null;
        }

        unset($block['asset_id'], $block['gallery_asset_ids'], $block['gallery_media_ids'], $block['gallery_items'], $block['attachment_asset_id'], $block['attachment_media_id']);

        return $block;
      })
      ->values()
      ->all();

    unset($data['public_shell']);

    return $data;
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      $siteId = (int) $this->input('site_id');
      $page = $this->route('page');
      $page = $page instanceof Page ? $page->loadMissing('translations') : null;

      if (! $page || $siteId <= 0) {
        return;
      }

      if ($page->site_id !== $siteId) {
        $validator->errors()->add('site_id', 'Existing pages cannot be moved between sites from the Edit Page screen.');

        return;
      }

      if ($page->site_id === $siteId) {
        return;
      }
    }];
  }
}
