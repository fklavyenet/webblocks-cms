<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceSetting;

class CommerceSettingsStore
{
  public const GATEWAY = 'gateway';

  public const PAYPAL_MODE = 'paypal.mode';

  public const PAYPAL_CLIENT_ID = 'paypal.client_id';

  public const PAYPAL_CLIENT_SECRET = 'paypal.client_secret';

  public const PAYPAL_WEBHOOK_ID = 'paypal.webhook_id';

  public const SUMUP_MODE = 'sumup.mode';

  public const SUMUP_API_KEY = 'sumup.api_key';

  public const SUMUP_MERCHANT_CODE = 'sumup.merchant_code';

  /** @var array<string, string> */
  private const CONFIG_PATHS = [
    self::GATEWAY => 'webblocks-commerce.gateway',
    self::PAYPAL_MODE => 'webblocks-commerce.paypal.mode',
    self::PAYPAL_CLIENT_ID => 'webblocks-commerce.paypal.client_id',
    self::PAYPAL_CLIENT_SECRET => 'webblocks-commerce.paypal.client_secret',
    self::PAYPAL_WEBHOOK_ID => 'webblocks-commerce.paypal.webhook_id',
    self::SUMUP_MODE => 'webblocks-commerce.sumup.mode',
    self::SUMUP_API_KEY => 'webblocks-commerce.sumup.api_key',
    self::SUMUP_MERCHANT_CODE => 'webblocks-commerce.sumup.merchant_code',
  ];

  /** @var array<string, string> */
  private const DEFAULTS = [
    self::GATEWAY => 'paypal',
    self::PAYPAL_MODE => 'sandbox',
    self::SUMUP_MODE => 'sandbox',
  ];

  public function value(string $key): ?string
  {
    $environment = $this->environmentValue($key);

    if ($environment !== null) {
      return $environment;
    }

    return $this->storedValue($key) ?? self::DEFAULTS[$key] ?? null;
  }

  public function source(string $key): string
  {
    if ($this->environmentValue($key) !== null) {
      return 'environment';
    }

    if ($this->storedValue($key) !== null) {
      return 'stored';
    }

    return array_key_exists($key, self::DEFAULTS) ? 'default' : 'missing';
  }

  public function isConfigured(string $key): bool
  {
    return $this->value($key) !== null;
  }

  public function isEnvironmentManaged(string $key): bool
  {
    return $this->environmentValue($key) !== null;
  }

  public function isStored(string $key): bool
  {
    return $this->storedValue($key) !== null;
  }

  public function isReady(): bool
  {
    try {
      return Schema::hasTable('webblocks_commerce_settings');
    } catch (Throwable) {
      return false;
    }
  }

  /**
   * Blank values preserve an existing credential. Environment-managed keys are
   * never written by the admin form and always win at runtime.
   *
   * @param  array<string, string|null>  $values
   * @param  array<int, string>  $clear
   */
  public function save(array $values, array $clear = []): void
  {
    if (! $this->isReady()) {
      throw new \RuntimeException('Commerce settings storage is missing. Run plugin setup before saving settings.');
    }

    DB::transaction(function () use ($values, $clear): void {
      foreach ($values as $key => $value) {
        if (! array_key_exists($key, self::CONFIG_PATHS) || $this->isEnvironmentManaged($key)) {
          continue;
        }

        if (in_array($key, $clear, true)) {
          CommerceSetting::query()->where('key', $key)->delete();

          continue;
        }

        $normalized = $this->normalize($value);

        if ($normalized === null) {
          continue;
        }

        CommerceSetting::query()->updateOrCreate(
          ['key' => $key],
          ['value' => $normalized],
        );
      }
    });
  }

  private function environmentValue(string $key): ?string
  {
    $path = self::CONFIG_PATHS[$key] ?? null;

    return $path === null ? null : $this->normalize(config($path));
  }

  private function storedValue(string $key): ?string
  {
    if (! $this->isReady()) {
      return null;
    }

    try {
      $setting = CommerceSetting::query()->where('key', $key)->first();

      return $this->normalize($setting?->value);
    } catch (Throwable) {
      return null;
    }
  }

  private function normalize(mixed $value): ?string
  {
    if (! is_string($value)) {
      return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
  }
}
