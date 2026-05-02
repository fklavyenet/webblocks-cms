<?php

namespace App\Http\Requests\Admin;

use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = (string) $this->input('title');
        $slug = (string) $this->input('slug');

        $this->merge([
            'site_id' => $this->input('site_id') ?: Site::primary()?->id,
            'slug' => Str::slug($slug !== '' ? $slug : $title),
        ]);
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $page = $page instanceof Page ? $page : null;
        $siteId = (int) $this->input('site_id');
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
            'public_shell' => ['nullable', Rule::in(array_merge(Page::allowedPublicShellPresets(), ['dashboard']))],
            'slots' => ['nullable', 'array'],
            'slots.*.id' => ['nullable', 'integer', 'exists:page_slots,id'],
            'slots.*.slot_type_id' => ['required', 'integer', 'exists:slot_types,id', 'distinct:strict'],
            'slots.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'slots.*._delete' => ['nullable', 'boolean'],
            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'integer', 'exists:blocks,id'],
            'blocks.*.block_type_id' => ['required', 'integer', 'exists:block_types,id'],
            'blocks.*.slot_type_id' => ['nullable', 'integer', 'exists:slot_types,id'],
            'blocks.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'blocks.*.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.subtitle' => ['nullable', 'string', 'max:255'],
            'blocks.*.content' => ['nullable', 'string'],
            'blocks.*.url' => ['nullable', 'string', 'max:2048'],
            'blocks.*.asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'blocks.*.gallery_asset_ids' => ['nullable', 'array'],
            'blocks.*.gallery_asset_ids.*' => ['integer', 'exists:assets,id'],
            'blocks.*.attachment_asset_id' => ['nullable', 'integer', 'exists:assets,id'],
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
                'public_shell' => Page::normalizePublicShellPreset($data['public_shell'] ?? ($existingSettings['public_shell'] ?? 'default')),
            ];
            $data['settings'] = $data['settings'] === [] ? null : $data['settings'];
        } else {
            unset($data['settings']);
        }

        $data['translation'] = [
            'name' => $data['title'],
            'slug' => $data['slug'],
        ];

        $data['slots'] = collect($data['slots'] ?? [])
            ->map(function (array $slot, int $index) {
                $slot['_delete'] = (bool) ($slot['_delete'] ?? false);
                $slot['sort_order'] = $index;

                return $slot;
            })
            ->values()
            ->all();

        $data['blocks'] = collect($data['blocks'] ?? [])
            ->map(function (array $block, int $index) use ($authorization) {
                $blockType = ! empty($block['block_type_id'])
                    ? BlockType::query()->find($block['block_type_id'])
                    : null;
                $galleryAssetIds = $authorization->filterAllowedAssetIds($this->user(), $block['gallery_asset_ids'] ?? []);
                $attachmentAssetId = $authorization->normalizeAllowedAssetId($this->user(), ! empty($block['attachment_asset_id']) ? (int) $block['attachment_asset_id'] : null);

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
                $block['asset_id'] = $authorization->normalizeAllowedAssetId($this->user(), ! empty($block['asset_id']) ? (int) $block['asset_id'] : null);
                $block['is_system'] = (bool) ($blockType?->is_system ?? false);
                $block['_delete'] = (bool) ($block['_delete'] ?? false);
                $block['sort_order'] = $index;
                $block['_block_assets'] = [
                    'gallery_item' => $galleryAssetIds,
                    'attachment' => $attachmentAssetId ? [$attachmentAssetId] : [],
                ];

                unset($block['gallery_asset_ids'], $block['attachment_asset_id']);

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

            if (! $page || $siteId <= 0 || $page->site_id === $siteId) {
                return;
            }

            $enabledLocaleIds = Site::query()
                ->whereKey($siteId)
                ->with(['enabledLocales:id'])
                ->first()?->enabledLocales
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all() ?? [];

            $invalidLocaleCodes = $page->translations
                ->reject(fn (PageTranslation $translation) => in_array((int) $translation->locale_id, $enabledLocaleIds, true))
                ->load('locale')
                ->map(fn (PageTranslation $translation) => strtoupper((string) $translation->locale?->code))
                ->filter()
                ->values();

            if ($invalidLocaleCodes->isNotEmpty()) {
                $validator->errors()->add('site_id', 'Target site must enable all existing page translation locales: '.$invalidLocaleCodes->join(', ').'.');
            }
        }];
    }
}
