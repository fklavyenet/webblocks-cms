<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SlotType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $pageId = $page instanceof Page ? $page->id : null;

        return [
            'slot_type_id' => [
                'required',
                'integer',
                Rule::exists(SlotType::class, 'id')->where(fn ($query) => $query->where('status', 'published')),
                Rule::unique(PageSlot::class, 'slot_type_id')->where(fn ($query) => $query->where('page_id', $pageId)),
            ],
        ];
    }
}
