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
        $isTranslatedColumnItem = $selectedBlockType?->slug === 'column_item' && $translationRegistry->isTranslatable($selectedBlockType?->slug) && $this->filled('locale');
        $isColumnItem = $selectedBlockType?->slug === 'column_item';
        $isNavigationAuto = in_array($selectedBlockType?->slug, ['navigation-auto', 'menu'], true);
        $isContactForm = $selectedBlockType?->slug === 'contact_form';
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
            'title' => [($isColumnItem || ($isLocaleRequest && $isTranslatedColumnItem)) ? 'required' : 'nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'content' => [($isColumnItem || ($isLocaleRequest && $isTranslatedColumnItem)) ? 'required' : 'nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
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
            'variant' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'string'],
            'settings' => ['nullable', 'string'],
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

            if (! $parentId) {
                if ($selectedBlockType?->slug !== 'columns') {
                    return;
                }
            }

            if ($selectedBlockType?->slug === 'columns') {
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

            if (! $parentId) {
                return;
            }

            $parent = Block::query()->with('parent')->find($parentId);
            $block = $this->route('block');

            if (! $parent || $parent->page_id !== $this->integer('page_id')) {
                $validator->errors()->add('parent_id', 'Parent block must belong to the same page.');

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
        }

        if (! empty($data['slot_type_id'])) {
            $slotType = SlotType::query()->find($data['slot_type_id']);
            $data['slot'] = $slotType?->slug;
        }

        unset($data['heading'], $data['intro_text'], $data['recipient_email'], $data['send_email_notification'], $data['store_submissions']);
        unset($data['navigation_menu_key']);

        return $data;
    }
}
