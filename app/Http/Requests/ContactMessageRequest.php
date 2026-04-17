<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'submitted_at' => ['required', 'integer'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        return [
            'block_id' => (int) $data['block_id'],
            'page_id' => ! empty($data['page_id']) ? (int) $data['page_id'] : null,
            'source_url' => $data['source_url'] ?? null,
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
            'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
            'message' => trim((string) $data['message']),
            'website' => trim((string) ($data['website'] ?? '')),
            'submitted_at' => (int) $data['submitted_at'],
        ];
    }
}
