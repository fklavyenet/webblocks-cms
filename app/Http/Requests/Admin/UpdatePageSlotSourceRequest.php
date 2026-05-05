<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePageSlotSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sourceType = strtolower(trim((string) $this->input('source_type')));
        $sharedSlotId = $this->input('shared_slot_id');

        $this->merge([
            'source_type' => $sourceType,
            'shared_slot_id' => filled($sharedSlotId) ? (int) $sharedSlotId : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(PageSlot::sourceTypes())],
            'shared_slot_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $page = $this->route('page');
            $slot = $this->route('slot');

            if (! $page instanceof Page || ! $slot instanceof PageSlot || (int) $slot->page_id !== (int) $page->id) {
                return;
            }

            $sourceType = (string) $this->input('source_type');
            $sharedSlotId = $this->input('shared_slot_id');

            if ($sourceType !== PageSlot::SOURCE_TYPE_SHARED_SLOT) {
                return;
            }

            if (! $this->sharedSlotsSchemaAvailable()) {
                $validator->errors()->add('shared_slot_id', 'Shared Slots are not available until the Shared Slots migration has been run.');

                return;
            }

            if (! is_int($sharedSlotId) || $sharedSlotId < 1) {
                $validator->errors()->add('shared_slot_id', 'Select a compatible Shared Slot.');

                return;
            }

            $sharedSlot = SharedSlot::query()->find($sharedSlotId);

            if (! $sharedSlot instanceof SharedSlot) {
                $validator->errors()->add('shared_slot_id', 'Select a compatible Shared Slot.');

                return;
            }

            $issues = $sharedSlot->compatibilityIssuesFor($page, $slot->slotSlug());

            if ($issues === []) {
                return;
            }

            foreach ($issues as $issue) {
                $validator->errors()->add('shared_slot_id', match ($issue) {
                    'site' => 'Shared Slot must belong to the same site as the page.',
                    'inactive' => 'Inactive Shared Slots cannot be assigned to page slots.',
                    'public_shell' => 'Shared Slot public shell must match the page public shell.',
                    'slot_name' => 'Shared Slot slot name must match the page slot name.',
                    default => 'Select a compatible Shared Slot.',
                });
            }
        });
    }

    public function validatedData(): array
    {
        $data = $this->validated();

        return [
            'source_type' => PageSlot::normalizeSourceType($data['source_type']),
            'shared_slot_id' => $data['source_type'] === PageSlot::SOURCE_TYPE_SHARED_SLOT
                ? (int) $data['shared_slot_id']
                : null,
        ];
    }

    private function sharedSlotsSchemaAvailable(): bool
    {
        return Schema::hasTable('shared_slots')
            && Schema::hasColumn('page_slots', 'source_type')
            && Schema::hasColumn('page_slots', 'shared_slot_id');
    }
}
