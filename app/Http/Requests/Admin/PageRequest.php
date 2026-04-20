<?php

namespace App\Http\Requests\Admin;

use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'site_id' => $this->input('site_id') ?: Site::query()->orderByDesc('is_primary')->value('id'),
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
                            ->where('locale_id', $defaultLocaleId));

                    return $translationId ? $rule->ignore($translationId) : $rule;
                })(),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
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
        $data = $this->validated();
        $data['page_type'] = 'default';
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
            ->map(function (array $block, int $index) {
                $blockType = ! empty($block['block_type_id'])
                    ? BlockType::query()->find($block['block_type_id'])
                    : null;
                $galleryAssetIds = collect($block['gallery_asset_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();

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
                $block['is_system'] = (bool) ($blockType?->is_system ?? false);
                $block['_delete'] = (bool) ($block['_delete'] ?? false);
                $block['sort_order'] = $index;
                $block['_block_assets'] = [
                    'gallery_item' => $galleryAssetIds,
                    'attachment' => ! empty($block['attachment_asset_id']) ? [(int) $block['attachment_asset_id']] : [],
                ];

                unset($block['gallery_asset_ids'], $block['attachment_asset_id']);

                return $block;
            })
            ->values()
            ->all();

        return $data;
    }
}
