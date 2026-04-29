<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $block = $this->route('block');
        $selectedBlockTypeId = (int) ($this->input('block_type_id') ?: $block?->block_type_id ?: 0);
        $selectedBlockType = $selectedBlockTypeId > 0 ? BlockType::query()->find($selectedBlockTypeId) : null;
        $translationRegistry = app(BlockTranslationRegistry::class);
        $isTranslatedBuilderChild = in_array($selectedBlockType?->slug, ['column_item', 'feature-item', 'link-list-item'], true)
            && $translationRegistry->isTranslatable($selectedBlockType?->slug)
            && $this->filled('locale');
        $isBuilderChild = in_array($selectedBlockType?->slug, ['column_item', 'feature-item', 'link-list-item'], true);
        $isColumns = $selectedBlockType?->slug === 'columns';
        $isFeatureGrid = $selectedBlockType?->slug === 'feature-grid';
        $isLinkList = $selectedBlockType?->slug === 'link-list';
        $isNavigationAuto = in_array($selectedBlockType?->slug, ['navigation-auto', 'menu'], true);
        $isContactForm = $selectedBlockType?->slug === 'contact_form';
        $isHero = $selectedBlockType?->slug === 'hero';
        $isCode = $selectedBlockType?->slug === 'code';
        $isHeader = $selectedBlockType?->slug === 'header';
        $isPlainText = $selectedBlockType?->slug === 'plain_text';
        $isContentHeader = $selectedBlockType?->slug === 'content_header';
        $isButtonLink = $selectedBlockType?->slug === 'button_link';
        $isCluster = $selectedBlockType?->slug === 'cluster';
        $supportsAlignment = $isHeader || $isPlainText || $isContentHeader;
        $supportsSectionSpacing = $selectedBlockType?->slug === 'section';
        $supportsContainerWidth = $selectedBlockType?->slug === 'container';
        $supportsClusterAlignment = $isCluster;
        $supportsClusterGap = $isCluster;
        $isLayoutPrimitive = in_array($selectedBlockType?->slug, ['section', 'container', 'cluster'], true);
        $isLocaleRequest = $this->filled('locale');
        $requiresContactCopy = $isContactForm && (! $isLocaleRequest || $this->route('block') instanceof Block);

        return [
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:blocks,id',
                Rule::notIn([$block?->id]),
            ],
            'block_type_id' => ['required', 'integer', 'exists:block_types,id'],
            'slot_type_id' => ['required', 'integer', 'exists:slot_types,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'locale' => ['nullable', 'string', 'regex:'.Locale::CODE_VALIDATION_PATTERN, 'exists:locales,code'],
            'title' => [($isBuilderChild || ($isLocaleRequest && $isTranslatedBuilderChild)) ? 'required' : 'nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'content' => [($isBuilderChild || ($isLocaleRequest && $isTranslatedBuilderChild)) ? 'required' : 'nullable', 'string'],
            'text' => [($isHeader || $isPlainText) ? 'required' : 'nullable', 'string'],
            'level' => [$isHeader ? 'required' : 'nullable', Rule::in(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])],
            'name' => [$isLayoutPrimitive ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'alignment' => [$supportsAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'left', 'center', 'right'])],
            'spacing' => [$supportsSectionSpacing ? 'nullable' : 'prohibited', Rule::in(['', 'sm', 'lg'])],
            'width' => [$supportsContainerWidth ? 'nullable' : 'prohibited', Rule::in(['', 'sm', 'md', 'lg', 'xl', 'full'])],
            'cluster_gap' => [$supportsClusterGap ? 'nullable' : 'prohibited', Rule::in(['', '2', '4', '6'])],
            'cluster_alignment' => [$supportsClusterAlignment ? 'nullable' : 'prohibited', Rule::in(['', 'start', 'center', 'end'])],
            'title' => [$isContentHeader ? 'required' : (($isBuilderChild || ($isLocaleRequest && $isTranslatedBuilderChild)) ? 'required' : 'nullable'), 'string', 'max:255'],
            'intro_text' => [$isContentHeader ? 'nullable' : 'prohibited', 'string'],
            'meta_items' => [$isContentHeader ? 'nullable' : 'prohibited', 'array'],
            'meta_items.*' => [$isContentHeader ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'title_level' => [$isContentHeader ? 'required' : 'prohibited', Rule::in(['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])],
            'url' => [$isButtonLink ? 'required' : 'nullable', 'string', 'max:2048'],
            'label' => [$isButtonLink ? 'required' : 'prohibited', 'string', 'max:255'],
            'target' => [$isButtonLink ? 'nullable' : 'prohibited', Rule::in(['_self', '_blank'])],
            'layout' => [$isHero ? 'nullable' : 'nullable', 'string', 'max:255'],
            'title_tag' => [$isHero ? 'nullable' : 'nullable', Rule::in(['h1', 'h2', 'h3'])],
            'language' => [$isCode ? 'nullable' : 'nullable', 'string', 'max:255'],
            'primary_cta_label' => ['nullable', 'string', 'max:255'],
            'primary_cta_url' => ['nullable', 'string', 'max:2048'],
            'secondary_cta_label' => ['nullable', 'string', 'max:255'],
            'secondary_cta_url' => ['nullable', 'string', 'max:2048'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'gallery_asset_ids' => ['nullable', 'array'],
            'gallery_asset_ids.*' => ['integer', 'exists:assets,id'],
            'attachment_asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'column_items' => ['nullable', 'array'],
            'column_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'column_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'column_items.*.title' => ['nullable', 'string', 'max:255'],
            'column_items.*.content' => ['nullable', 'string'],
            'column_items.*.url' => ['nullable', 'string', 'max:2048'],
            'column_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'column_items.*.is_system' => ['nullable', 'boolean'],
            'column_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'column_items.*._delete' => ['nullable', 'boolean'],
            'feature_items' => ['nullable', 'array'],
            'feature_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'feature_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'feature_items.*.title' => ['nullable', 'string', 'max:255'],
            'feature_items.*.content' => ['nullable', 'string'],
            'feature_items.*.url' => ['nullable', 'string', 'max:2048'],
            'feature_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'feature_items.*.is_system' => ['nullable', 'boolean'],
            'feature_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'feature_items.*._delete' => ['nullable', 'boolean'],
            'link_list_items' => ['nullable', 'array'],
            'link_list_items.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'link_list_items.*.block_type_id' => ['nullable', 'integer', 'exists:block_types,id'],
            'link_list_items.*.title' => ['nullable', 'string', 'max:255'],
            'link_list_items.*.subtitle' => ['nullable', 'string', 'max:255'],
            'link_list_items.*.content' => ['nullable', 'string'],
            'link_list_items.*.url' => ['nullable', 'string', 'max:2048'],
            'link_list_items.*.status' => ['nullable', Rule::in(['draft', 'published'])],
            'link_list_items.*.is_system' => ['nullable', 'boolean'],
            'link_list_items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'link_list_items.*._delete' => ['nullable', 'boolean'],
            'variant' => [($isLayoutPrimitive || $isContentHeader) ? 'prohibited' : 'nullable', $isButtonLink ? Rule::in(['primary', 'secondary']) : 'string', 'max:255'],
            'meta' => [$isLayoutPrimitive ? 'prohibited' : 'nullable', 'string'],
            'settings' => [$isLayoutPrimitive ? 'prohibited' : 'nullable', 'string'],
            'heading' => [$isContactForm ? 'nullable' : 'nullable', 'string', 'max:255'],
            'intro_text' => [$isContactForm ? 'nullable' : 'nullable', 'string'],
            'submit_label' => [$requiresContactCopy ? 'required' : 'nullable', 'string', 'max:255'],
            'success_message' => [$requiresContactCopy ? 'required' : 'nullable', 'string', 'max:1000'],
            'recipient_email' => [($isContactForm && ! $isLocaleRequest) ? 'nullable' : 'nullable', 'email:rfc', 'max:255'],
            'send_email_notification' => [($isContactForm && ! $isLocaleRequest) ? 'required' : 'nullable', 'boolean'],
            'store_submissions' => [($isContactForm && ! $isLocaleRequest) ? 'required' : 'nullable', 'boolean'],
            'navigation_menu_key' => [$isNavigationAuto ? 'required' : 'nullable', Rule::in(NavigationItem::menuKeys())],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $page = Page::query()->with('site.locales')->find($this->integer('page_id'));
            $localeCode = Locale::normalizeCode($this->input('locale'));

            if ($localeCode !== null && (! $page || ! $page->site || ! $page->site->hasEnabledLocale($localeCode))) {
                $validator->errors()->add('locale', 'Selected locale must be enabled for the page site.');
            }

            $parentId = $this->integer('parent_id');
            $selectedBlockTypeId = (int) ($this->input('block_type_id') ?: $this->route('block')?->block_type_id ?: 0);
            $selectedBlockType = $selectedBlockTypeId > 0 ? BlockType::query()->find($selectedBlockTypeId) : null;
            $isColumns = $selectedBlockType?->slug === 'columns';
            $isFeatureGrid = $selectedBlockType?->slug === 'feature-grid';
            $isLinkList = $selectedBlockType?->slug === 'link-list';

            if ($selectedBlockType?->slug === 'button_link') {
                $url = trim((string) $this->input('url', ''));

                if ($url !== '' && ! preg_match('/^(https?:\/\/|\/|#|mailto:|tel:)/i', $url)) {
                    $validator->errors()->add('url', 'Button link URL must be a full URL, site path, anchor, mailto link, or telephone link.');
                }
            }

            if (! $parentId) {
                if (! in_array($selectedBlockType?->slug, ['columns', 'feature-grid', 'link-list'], true)) {
                    return;
                }
            }

            if ($isColumns) {
                foreach ($this->input('column_items', []) as $index => $columnItem) {
                    if ((bool) ($columnItem['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($columnItem['title'] ?? null)) {
                        $validator->errors()->add("column_items.{$index}.title", 'Column item title is required.');
                    }

                    if (blank($columnItem['content'] ?? null)) {
                        $validator->errors()->add("column_items.{$index}.content", 'Column item text is required.');
                    }
                }
            }

            if ($isFeatureGrid) {
                foreach ($this->input('feature_items', []) as $index => $featureItem) {
                    if ((bool) ($featureItem['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($featureItem['title'] ?? null)) {
                        $validator->errors()->add("feature_items.{$index}.title", 'Feature item title is required.');
                    }

                    if (blank($featureItem['content'] ?? null)) {
                        $validator->errors()->add("feature_items.{$index}.content", 'Feature item text is required.');
                    }
                }
            }

            if ($isLinkList) {
                foreach ($this->input('link_list_items', []) as $index => $item) {
                    if ((bool) ($item['_delete'] ?? false)) {
                        continue;
                    }

                    if (blank($item['title'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.title", 'Link list item title is required.');
                    }

                    if (blank($item['subtitle'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.subtitle", 'Link list item meta is required.');
                    }

                    if (blank($item['content'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.content", 'Link list item description is required.');
                    }

                    if (blank($item['url'] ?? null)) {
                        $validator->errors()->add("link_list_items.{$index}.url", 'Link list item URL is required.');
                    }
                }
            }

            if (! $parentId) {
                return;
            }

            $parent = Block::query()->with('parent')->find($parentId);
            $block = $this->route('block');

            if (! $parent || $parent->page_id !== $this->integer('page_id')) {
                $validator->errors()->add('parent_id', 'Parent block must belong to the same page.');

                return;
            }

            if ($selectedBlockType?->slug === 'link-list-item' && ! $parent->isLinkList()) {
                $validator->errors()->add('parent_id', 'Link list items can only be placed under a link-list block.');

                return;
            }

            if (! $block) {
                return;
            }

            if ($parent->id === $block->id) {
                $validator->errors()->add('parent_id', 'A block cannot be its own parent.');

                return;
            }

            $cursor = $parent;

            while ($cursor) {
                if ($cursor->id === $block->id) {
                    $validator->errors()->add('parent_id', 'A block cannot be moved under its own child tree.');

                    return;
                }

                $cursor = $cursor->parent;
            }
        }];
    }

    public function messages(): array
    {
        return [
            'locale.regex' => 'Use a valid locale code.',
            'locale.exists' => 'Selected locale is invalid.',
        ];
    }

    public function validatedData(): array
    {
        /** @var AdminAuthorization $authorization */
        $authorization = app(AdminAuthorization::class);
        $data = $this->validated();
        $data['locale'] = Locale::normalizeCode($data['locale'] ?? null);
        $pageId = (int) $data['page_id'];

        if (! empty($data['parent_id'])) {
            $parentMatchesPage = Block::query()
                ->whereKey($data['parent_id'])
                ->where('page_id', $pageId)
                ->exists();

            if (! $parentMatchesPage) {
                $data['parent_id'] = null;
            }
        }

        $settings = trim((string) ($data['settings'] ?? ''));
        $data['settings'] = $settings === '' ? null : $settings;
        $meta = trim((string) ($data['meta'] ?? ''));
        $data['meta'] = $meta === '' ? null : $meta;
        $data['asset_id'] = $authorization->normalizeAllowedAssetId($this->user(), ! empty($data['asset_id']) ? (int) $data['asset_id'] : null);

        $galleryAssetIds = $authorization->filterAllowedAssetIds($this->user(), $data['gallery_asset_ids'] ?? []);
        $attachmentAssetId = $authorization->normalizeAllowedAssetId($this->user(), ! empty($data['attachment_asset_id']) ? (int) $data['attachment_asset_id'] : null);

        $decodedSettings = [];

        if (! empty($data['settings'])) {
            $parsedSettings = json_decode((string) $data['settings'], true);
            $decodedSettings = is_array($parsedSettings) ? $parsedSettings : [];
        }

        $data['settings'] = $decodedSettings === []
            ? null
            : json_encode($decodedSettings, JSON_UNESCAPED_SLASHES);

        unset($data['gallery_asset_ids']);
        unset($data['attachment_asset_id']);

        $data['_block_assets'] = [
            'gallery_item' => $galleryAssetIds,
            'attachment' => $attachmentAssetId ? [$attachmentAssetId] : [],
        ];

        if (! empty($data['block_type_id'])) {
            $blockType = BlockType::query()->find($data['block_type_id']);
            $data['type'] = $blockType?->slug;
            $data['source_type'] = $blockType?->source_type ?? 'static';
            $data['is_system'] = (bool) ($blockType?->is_system ?? false);

            if ($blockType?->slug === 'hero') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedHeroEdit = $data['locale'] !== null;

                $layout = trim((string) ($data['layout'] ?? ''));
                $titleTag = trim((string) ($data['title_tag'] ?? ''));

                $settings = $existingSettings;

                if (! $isTranslatedHeroEdit) {
                    $settings['layout'] = $layout !== '' ? $layout : null;
                    $settings['title_tag'] = in_array($titleTag, ['h1', 'h2', 'h3'], true) ? $titleTag : null;
                }

                $data['url'] = null;
                $data['variant'] = $isTranslatedHeroEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['variant'] ?? '')) ?: null);
                $data['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if ($blockType?->slug === 'cta') {
                $isTranslatedCtaEdit = $data['locale'] !== null;

                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = $isTranslatedCtaEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['variant'] ?? '')) ?: null);
            }

            if ($blockType?->slug === 'code') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedCodeEdit = $data['locale'] !== null;
                $language = trim((string) ($data['language'] ?? ''));
                $settings = $existingSettings;

                unset($settings['lang']);

                if (! $isTranslatedCodeEdit) {
                    $settings['language'] = $language !== '' ? $language : null;
                }

                $data['url'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode(array_filter($settings, fn ($value) => $value !== null && $value !== '' && $value !== []), JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }
            }

            if (in_array($blockType?->slug, ['navigation-auto', 'menu'], true)) {
                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode([
                    'menu_key' => $data['navigation_menu_key'] ?? NavigationItem::MENU_PRIMARY,
                ], JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'contact_form') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $isTranslatedContactFormEdit = $data['locale'] !== null;

                $data['title'] = trim((string) ($data['heading'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = trim((string) ($data['intro_text'] ?? '')) ?: null;
                $data['url'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['asset_id'] = null;
                $data['settings'] = json_encode([
                    'recipient_email' => $isTranslatedContactFormEdit
                        ? ($existingSettings['recipient_email'] ?? null)
                        : (trim((string) ($data['recipient_email'] ?? '')) ?: null),
                    'send_email_notification' => $isTranslatedContactFormEdit
                        ? (bool) ($existingSettings['send_email_notification'] ?? true)
                        : (bool) ($data['send_email_notification'] ?? true),
                    'store_submissions' => $isTranslatedContactFormEdit
                        ? (bool) ($existingSettings['store_submissions'] ?? true)
                        : (bool) ($data['store_submissions'] ?? true),
                ], JSON_UNESCAPED_SLASHES);
            }

            if ($blockType?->slug === 'header') {
                $isTranslatedHeaderEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));

                if (! $isTranslatedHeaderEdit) {
                    if (in_array($alignment, ['left', 'center', 'right'], true)) {
                        $settings['alignment'] = $alignment;
                    } else {
                        unset($settings['alignment']);
                    }
                }

                $data['title'] = trim((string) ($data['text'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
                $data['variant'] = $isTranslatedHeaderEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['level'] ?? '')) ?: 'h2');
            }

            if ($blockType?->slug === 'content_header') {
                $isTranslatedContentHeaderEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));
                $metaItems = collect($data['meta_items'] ?? [])
                    ->map(fn ($item) => trim((string) $item))
                    ->filter()
                    ->values()
                    ->all();

                if (! $isTranslatedContentHeaderEdit) {
                    if (in_array($alignment, ['left', 'center', 'right'], true)) {
                        $settings['alignment'] = $alignment;
                    } else {
                        unset($settings['alignment']);
                    }
                }

                $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
                $data['subtitle'] = trim((string) ($data['intro_text'] ?? '')) ?: null;
                $data['content'] = null;
                $data['meta'] = $metaItems === []
                    ? null
                    : json_encode($metaItems, JSON_UNESCAPED_SLASHES);
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
                $data['variant'] = $isTranslatedContentHeaderEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (trim((string) ($data['title_level'] ?? '')) ?: 'h1');
            }

            if ($blockType?->slug === 'button_link') {
                $isTranslatedButtonLinkEdit = $data['locale'] !== null;
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;

                if (! $isTranslatedButtonLinkEdit) {
                    $settings['url'] = trim((string) ($data['url'] ?? '')) ?: null;
                    $settings['target'] = ($data['target'] ?? '_self') === '_blank' ? '_blank' : '_self';
                }

                $data['title'] = trim((string) ($data['label'] ?? '')) ?: null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['meta'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $settings = array_filter($settings, fn ($value) => $value !== null && $value !== '');
                $data['settings'] = json_encode($settings, JSON_UNESCAPED_SLASHES);

                if ($data['settings'] === '[]' || $data['settings'] === '{}') {
                    $data['settings'] = null;
                }

                $data['variant'] = $isTranslatedButtonLinkEdit
                    ? ($this->route('block')?->getRawOriginal('variant'))
                    : (in_array(trim((string) ($data['variant'] ?? 'primary')), ['primary', 'secondary'], true) ? trim((string) ($data['variant'] ?? 'primary')) : 'primary');
            }

            if ($blockType?->slug === 'plain_text') {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $settings = $existingSettings;
                $alignment = trim((string) ($data['alignment'] ?? ''));

                if (in_array($alignment, ['left', 'center', 'right'], true)) {
                    $settings['alignment'] = $alignment;
                } else {
                    unset($settings['alignment']);
                }

                $data['content'] = trim((string) ($data['text'] ?? '')) ?: null;
                $data['title'] = null;
                $data['subtitle'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }

            if (in_array($blockType?->slug, ['section', 'container', 'cluster'], true)) {
                $existingSettings = $this->route('block') instanceof Block
                    ? json_decode((string) $this->route('block')->getRawOriginal('settings'), true)
                    : [];
                $existingSettings = is_array($existingSettings) ? $existingSettings : [];
                $layoutName = trim((string) ($data['name'] ?? ''));
                $settings = $existingSettings;
                $spacing = trim((string) ($data['spacing'] ?? ''));
                $width = trim((string) ($data['width'] ?? ''));
                $clusterGap = trim((string) ($data['cluster_gap'] ?? ''));
                $clusterAlignment = trim((string) ($data['cluster_alignment'] ?? ''));

                if ($layoutName !== '') {
                    $settings['layout_name'] = $layoutName;
                } else {
                    unset($settings['layout_name']);
                }

                if ($blockType->slug === 'section') {
                    if (in_array($spacing, ['sm', 'lg'], true)) {
                        $settings['spacing'] = $spacing;
                    } else {
                        unset($settings['spacing']);
                    }

                    unset($settings['width']);
                }

                if ($blockType->slug === 'container') {
                    if (in_array($width, ['sm', 'md', 'lg', 'xl', 'full'], true)) {
                        $settings['width'] = $width;
                    } else {
                        unset($settings['width']);
                    }

                    unset($settings['spacing']);
                }

                if ($blockType->slug === 'cluster') {
                    if (in_array($clusterGap, ['2', '4', '6'], true)) {
                        $settings['gap'] = $clusterGap;
                    } else {
                        unset($settings['gap']);
                    }

                    if (in_array($clusterAlignment, ['center', 'end'], true)) {
                        $settings['alignment'] = $clusterAlignment;
                    } else {
                        unset($settings['alignment']);
                    }

                    unset($settings['spacing'], $settings['width']);
                }

                $data['title'] = null;
                $data['subtitle'] = null;
                $data['content'] = null;
                $data['url'] = null;
                $data['asset_id'] = null;
                $data['variant'] = null;
                $data['meta'] = null;
                $data['settings'] = $settings === []
                    ? null
                    : json_encode($settings, JSON_UNESCAPED_SLASHES);
            }
        }

        if (! empty($data['slot_type_id'])) {
            $slotType = SlotType::query()->find($data['slot_type_id']);
            $data['slot'] = $slotType?->slug;
        }

        unset($data['heading'], $data['intro_text'], $data['recipient_email'], $data['send_email_notification'], $data['store_submissions']);
        unset($data['layout']);
        unset($data['title_tag']);
        unset($data['language']);
        unset($data['navigation_menu_key']);
        unset($data['text'], $data['level']);
        unset($data['label'], $data['target']);
        unset($data['name'], $data['alignment'], $data['spacing'], $data['width'], $data['cluster_gap'], $data['cluster_alignment'], $data['intro_text'], $data['meta_items'], $data['title_level']);

        return $data;
    }
}
