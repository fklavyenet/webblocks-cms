<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => str((string) $this->input('email'))->lower()->toString(),
            'role' => (string) $this->input('role', $this->route('user')?->role ?? User::ROLE_EDITOR),
            'is_active' => $this->boolean('is_active'),
            'site_ids' => collect($this->input('site_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in(User::roles())],
            'is_active' => ['nullable', 'boolean'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['integer', 'distinct', Rule::exists(Site::class, 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (in_array($this->input('role'), [User::ROLE_SITE_ADMIN, User::ROLE_EDITOR], true) && count($this->input('site_ids', [])) === 0) {
                $validator->errors()->add('site_ids', 'Select at least one site for site admins and editors.');
            }
        }];
    }
}
