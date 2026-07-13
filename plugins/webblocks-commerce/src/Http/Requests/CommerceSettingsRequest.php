<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\CommerceSettingsStore;

class CommerceSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('webblocks-commerce.manage-settings');
  }

  /**
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'gateway' => ['required', Rule::in(['paypal', 'sumup'])],
      'paypal_mode' => ['required', Rule::in(['sandbox', 'live'])],
      'paypal_client_id' => ['nullable', 'string', 'max:1024'],
      'paypal_client_secret' => ['nullable', 'string', 'max:4096'],
      'paypal_webhook_id' => ['nullable', 'string', 'max:1024'],
      'sumup_mode' => ['required', Rule::in(['sandbox', 'live'])],
      'sumup_api_key' => ['nullable', 'string', 'max:4096'],
      'sumup_merchant_code' => ['nullable', 'string', 'max:255'],
      'clear_paypal_client_id' => ['nullable', 'boolean'],
      'clear_paypal_client_secret' => ['nullable', 'boolean'],
      'clear_paypal_webhook_id' => ['nullable', 'boolean'],
      'clear_sumup_api_key' => ['nullable', 'boolean'],
      'clear_sumup_merchant_code' => ['nullable', 'boolean'],
    ];
  }

  /**
   * @return array<string, string|null>
   */
  public function settingsPayload(): array
  {
    $validated = $this->validated();

    return [
      CommerceSettingsStore::GATEWAY => $validated['gateway'],
      CommerceSettingsStore::PAYPAL_MODE => $validated['paypal_mode'],
      CommerceSettingsStore::PAYPAL_CLIENT_ID => $validated['paypal_client_id'] ?? null,
      CommerceSettingsStore::PAYPAL_CLIENT_SECRET => $validated['paypal_client_secret'] ?? null,
      CommerceSettingsStore::PAYPAL_WEBHOOK_ID => $validated['paypal_webhook_id'] ?? null,
      CommerceSettingsStore::SUMUP_MODE => $validated['sumup_mode'],
      CommerceSettingsStore::SUMUP_API_KEY => $validated['sumup_api_key'] ?? null,
      CommerceSettingsStore::SUMUP_MERCHANT_CODE => $validated['sumup_merchant_code'] ?? null,
    ];
  }

  /**
   * @return array<int, string>
   */
  public function clearKeys(): array
  {
    $clear = [
      'clear_paypal_client_id' => CommerceSettingsStore::PAYPAL_CLIENT_ID,
      'clear_paypal_client_secret' => CommerceSettingsStore::PAYPAL_CLIENT_SECRET,
      'clear_paypal_webhook_id' => CommerceSettingsStore::PAYPAL_WEBHOOK_ID,
      'clear_sumup_api_key' => CommerceSettingsStore::SUMUP_API_KEY,
      'clear_sumup_merchant_code' => CommerceSettingsStore::SUMUP_MERCHANT_CODE,
    ];

    return collect($clear)
      ->filter(fn (string $key, string $field): bool => $this->boolean($field))
      ->values()
      ->all();
  }

  protected function prepareForValidation(): void
  {
    $fields = [
      'gateway',
      'paypal_mode',
      'paypal_client_id',
      'paypal_client_secret',
      'paypal_webhook_id',
      'sumup_mode',
      'sumup_api_key',
      'sumup_merchant_code',
    ];

    $this->merge(collect($fields)->mapWithKeys(function (string $field): array {
      $value = $this->input($field);

      return [$field => is_string($value) ? trim($value) : $value];
    })->all());
  }
}
