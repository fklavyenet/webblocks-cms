<?php

namespace App\Http\Requests\Admin;

use App\Models\PageLayout;
use App\Models\PageLayoutSlot;
use App\Models\SlotType;
use App\Support\Pages\LayoutMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PageLayoutSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;
        $layoutSlot = $this->route('page_layout_slot');
        $layoutSlot = $layoutSlot instanceof PageLayoutSlot ? $layoutSlot : null;
        $slotTypeId = (int) $this->input('slot_type_id');
        $slotType = $slotTypeId > 0 ? SlotType::query()->find($slotTypeId) : null;
        $slotName = LayoutMarkup::normalizeSlotName($this->input('slot_name'));

        if (! $slotName && $slotType) {
            $slotName = LayoutMarkup::normalizeSlotName($slotType->slug);
        }

        if ($pageLayout?->is_system && $layoutSlot?->is_system) {
            $slotName = $layoutSlot->slot_name;
            $slotTypeId = (int) ($layoutSlot->slot_type_id ?? $slotTypeId);
        }

        $this->merge([
            'slot_type_id' => $slotTypeId > 0 ? $slotTypeId : null,
            'slot_name' => $slotName,
            'html_element' => LayoutMarkup::normalizeElement($this->input('html_element')),
            'html_id' => LayoutMarkup::normalizeHtmlId($this->input('html_id')),
            'html_classes' => LayoutMarkup::normalizeTokenList($this->input('html_classes')),
            'is_required' => $this->boolean('is_required', false),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }

    public function rules(): array
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;
        $layoutSlot = $this->route('page_layout_slot');
        $layoutSlot = $layoutSlot instanceof PageLayoutSlot ? $layoutSlot : null;

        return [
            'slot_type_id' => [
                'required',
                'integer',
                Rule::exists(SlotType::class, 'id')->where(fn ($query) => $query->where('status', 'published')),
            ],
            'slot_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(PageLayoutSlot::class, 'slot_name')
                    ->where(fn ($query) => $query->where('page_layout_id', $pageLayout?->id))
                    ->ignore($layoutSlot?->id),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'html_element' => ['required', Rule::in(LayoutMarkup::allowedElements())],
            'html_id' => ['nullable', 'string', 'max:255'],
            'html_classes' => ['nullable', 'string', 'max:1000'],
            'before_html' => ['nullable', 'string'],
            'start_html' => ['nullable', 'string'],
            'end_html' => ['nullable', 'string'],
            'after_html' => ['nullable', 'string'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! LayoutMarkup::hasValidHtmlId($this->input('html_id'))) {
                $validator->errors()->add('html_id', 'HTML ID must be a safe HTML id token.');
            }

            if (! LayoutMarkup::hasValidTokenList($this->input('html_classes'))) {
                $validator->errors()->add('html_classes', 'CSS classes must be a safe whitespace-separated class token list.');
            }

            foreach (['before_html', 'start_html', 'end_html', 'after_html'] as $field) {
                $error = LayoutMarkup::trustedHtmlError($this->input($field));

                if ($error !== null) {
                    $validator->errors()->add($field, $error);
                }
            }
        }];
    }

    public function validatedData(): array
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;
        $layoutSlot = $this->route('page_layout_slot');
        $layoutSlot = $layoutSlot instanceof PageLayoutSlot ? $layoutSlot : null;
        $data = $this->validated();

        $data['slot_type_id'] = (int) $data['slot_type_id'];
        $data['slot_name'] = LayoutMarkup::normalizeSlotName($data['slot_name']);
        $data['html_element'] = LayoutMarkup::normalizeElement($data['html_element']);
        $data['html_id'] = LayoutMarkup::normalizeHtmlId($data['html_id'] ?? null);
        $data['html_classes'] = LayoutMarkup::normalizeTokenList($data['html_classes'] ?? null);
        $data['label'] = filled($data['label'] ?? null) ? trim((string) $data['label']) : null;
        $data['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;
        $data['before_html'] = trim((string) ($data['before_html'] ?? '')) ?: null;
        $data['start_html'] = trim((string) ($data['start_html'] ?? '')) ?: null;
        $data['end_html'] = trim((string) ($data['end_html'] ?? '')) ?: null;
        $data['after_html'] = trim((string) ($data['after_html'] ?? '')) ?: null;
        $data['is_required'] = (bool) $data['is_required'];
        $data['is_active'] = (bool) $data['is_active'];
        $data['sort_order'] = (int) $data['sort_order'];
        $data['is_system'] = $layoutSlot?->is_system ?? false;

        if ($pageLayout?->is_system && $layoutSlot?->is_system) {
            $data['slot_name'] = $layoutSlot->slot_name;
            $data['slot_type_id'] = $layoutSlot->slot_type_id;
        }

        return $data;
    }
}
