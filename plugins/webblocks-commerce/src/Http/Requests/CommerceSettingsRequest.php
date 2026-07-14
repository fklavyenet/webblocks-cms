<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\CommerceSettingsStore;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\Currency\CurrencyCatalog;

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
      'default_currency' => [
        'required',
        Rule::in(app(CurrencyCatalog::class)->codesForGateway((string) $this->input('gateway'))),
      ],
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
      CommerceSettingsStore::DEFAULT_CURRENCY => $validated['default_currency'],
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

  public function withValidator(Validator $validator): void
  {
    $validator->after(function (Validator $validator): void {
      if ($validator->errors()->has('gateway') || ! Schema::hasTable('webblocks_commerce_products')) {
        return;
      }

      $gateway = strtolower((string) $this->input('gateway'));
      $supported = app(CurrencyCatalog::class)->codesForGateway($gateway);
      $unsupported = CommerceProduct::query()
        ->where('status', '!=', CommerceProduct::STATUS_ARCHIVED)
        ->whereNotIn('currency', $supported)
        ->distinct()
        ->orderBy('currency')
        ->pluck('currency')
        ->all();

      if ($unsupported !== []) {
        $validator->errors()->add(
          'gateway',
          'The active catalog contains currencies unsupported by '.ucfirst($gateway).': '.implode(', ', $unsupported).'. Update or archive those products first.',
        );
      }
    });
  }

  protected function prepareForValidation(): void
  {
    $fields = [
      'gateway',
      'default_currency',
      'paypal_mode',
      'paypal_client_id',
      'paypal_client_secret',
      'paypal_webhook_id',
      'sumup_mode',
      'sumup_api_key',
      'sumup_merchant_code',
    ];

    $normalized = collect($fields)->mapWithKeys(function (string $field): array {
      $value = $this->input($field);

      return [$field => is_string($value) ? trim($value) : $value];
    })->all();

    if (! $this->exists('default_currency')) {
      $normalized['default_currency'] = app(CommerceSettingsStore::class)->value(CommerceSettingsStore::DEFAULT_CURRENCY) ?? 'USD';
    }

    $normalized['default_currency'] = strtoupper((string) ($normalized['default_currency'] ?? ''));

    $this->merge($normalized);
  }
}
