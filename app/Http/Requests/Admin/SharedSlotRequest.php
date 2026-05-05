<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Support\SharedSlots\SharedSlotSchema;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SharedSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));
        $handle = trim((string) $this->input('handle'));
        $slotName = trim((string) $this->input('slot_name'));
        $publicShell = trim((string) $this->input('public_shell'));

        $this->merge([
            'site_id' => $this->input('site_id') ?: Site::primary()?->id,
            'handle' => Str::slug($handle !== '' ? $handle : $name),
            'slot_name' => $slotName !== '' ? Str::slug($slotName) : null,
            'public_shell' => $publicShell !== '' ? Page::normalizePublicShellPreset($publicShell) : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function rules(): array
    {
        $sharedSlot = $this->route('shared_slot');
        $sharedSlot = $sharedSlot instanceof SharedSlot ? $sharedSlot : null;
        $siteId = (int) $this->input('site_id');

        $handleRules = [
            'required',
            'string',
            'max:100',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        ];

        if (app(SharedSlotSchema::class)->sharedSlotsTableExists()) {
            $handleRules[] = Rule::unique(SharedSlot::class, 'handle')
                ->where(fn ($query) => $query->where('site_id', $siteId))
                ->ignore($sharedSlot?->id);
        }

        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'handle' => $handleRules,
            'slot_name' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'public_shell' => ['nullable', Rule::in(Page::allowedPublicShellPresets())],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();
        $data['site_id'] = (int) $data['site_id'];
        $data['name'] = trim((string) $data['name']);
        $data['handle'] = Str::slug((string) $data['handle']);
        $data['slot_name'] = filled($data['slot_name'] ?? null) ? Str::slug((string) $data['slot_name']) : null;
        $data['public_shell'] = filled($data['public_shell'] ?? null)
            ? Page::normalizePublicShellPreset($data['public_shell'])
            : null;
        $data['is_active'] = (bool) $data['is_active'];

        return $data;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var AdminAuthorization $authorization */
            $authorization = app(AdminAuthorization::class);
            $schema = app(SharedSlotSchema::class);
            $sharedSlot = $this->route('shared_slot');
            $sharedSlot = $sharedSlot instanceof SharedSlot ? $sharedSlot : null;
            $siteId = (int) $this->input('site_id');

            if (! $schema->sharedSlotsTableExists()) {
                $validator->errors()->add('shared_slots', 'Shared Slots are not ready yet. Run the latest migrations before using Shared Slots.');

                return;
            }

            if ($siteId > 0 && ! $this->user()?->isSuperAdmin() && ! $authorization->scopeSitesForUser(Site::query(), $this->user())->whereKey($siteId)->exists()) {
                $validator->errors()->add('site_id', 'Selected site is outside your allowed site scope.');
            }

            if ($sharedSlot && $this->user()?->isEditor() && $siteId !== (int) $sharedSlot->site_id) {
                $validator->errors()->add('site_id', 'Editors cannot move Shared Slots to a different site.');
            }
        }];
    }
}
