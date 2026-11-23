<?php

namespace WebBlocks\Cms\Support\Extensions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class ExtensionOperation
{
  public readonly string $reference;

  private ?string $identifier = null;

  private ?string $version = null;

  private ?string $label = null;

  private ?string $preservedVersion = null;

  private ?string $cmsVersion = null;

  public function __construct(
    private readonly string $type,
    private string $operation,
    private string $stage = 'package',
  ) {
    $this->reference = (string) Str::uuid();
  }

  public static function plugin(string $operation, PluginDefinition $plugin, string $stage = 'activation'): self
  {
    $context = new self('plugin', $operation, $stage);
    try {
      $label = $plugin->labelText();
    } catch (PluginException) {
      $label = null;
    }
    $context->identify($plugin->handle(), $plugin->versionText(), $label);

    return $context;
  }

  public function identify(?string $identifier, ?string $version = null, ?string $label = null): void
  {
    $identifier = self::safeIdentifier($identifier);
    if ($identifier === null || ($this->identifier !== null && $identifier !== $this->identifier)) {
      return;
    }

    $this->identifier = $identifier;
    $this->version = self::safeVersion($version) ?? $this->version;
    $this->label = self::safeLabel($label) ?? $this->label;
  }

  public function during(string $stage): void
  {
    $this->stage = $stage;
  }

  public function operation(string $operation): void
  {
    $this->operation = $operation;
  }

  public function preserved(?string $version): void
  {
    $this->preservedVersion = self::safeVersion($version);
  }

  public function targetCms(?string $version): void
  {
    $this->cmsVersion = self::safeVersion($version);
  }

  public function context(): array
  {
    return array_filter([
      'reference' => $this->reference,
      'type' => $this->type,
      'operation' => $this->operation,
      'stage' => $this->stage,
      'identifier' => $this->identifier,
      'version' => $this->version,
      'preserved_version' => $this->preservedVersion,
      'target_cms_version' => $this->cmsVersion,
    ], static fn (?string $value) => $value !== null);
  }

  public function record(): void
  {
    try {
      Log::warning('CMS extension operation failed', $this->context());
    } catch (Throwable) {
      // Feedback and recovery must still work with an unavailable log sink.
    }
  }

  public function fail(): string
  {
    $this->record();

    return $this->message();
  }

  private function message(): string
  {
    $subject = implode(' · ', array_filter([
      $this->label !== $this->identifier ? $this->label : null,
      $this->identifier,
      $this->version,
      $this->cmsVersion !== null ? 'CMS '.$this->cmsVersion : null,
    ]));

    return implode(' ', array_filter([
      rtrim(self::translate('failed', [
        'type' => self::translate('type.'.$this->type),
        'operation' => self::translate('operation.'.$this->operation),
        'identity' => $subject !== '' ? '('.$subject.')' : '',
      ]), ' .').'.',
      self::translate('stage.'.$this->stage),
      $this->preservedVersion !== null ? self::translate('preserved', ['version' => $this->preservedVersion]) : null,
      self::translate('reference', ['reference' => $this->reference]),
    ]));
  }

  /**
   * Use with core operations whose validation messages contain only reviewed copy
   * and validated placeholders. Preserve Laravel's normal field error contract.
   */
  public function validate(callable $action, string $field = 'package'): mixed
  {
    try {
      return $action();
    } catch (ValidationException $exception) {
      throw $this->validationError($exception);
    } catch (HttpExceptionInterface $exception) {
      throw $exception;
    } catch (Throwable) {
      throw ValidationException::withMessages([$field => $this->fail()]);
    }
  }

  public static function recoveryMessage(string $handle, string $version, ?string $label, ?string $reference): string
  {
    $identity = implode(' · ', array_filter([
      $label !== $handle ? self::safeLabel($label) : null,
      self::safeIdentifier($handle),
      self::safeVersion($version),
    ]));
    $message = self::translate('quarantined', ['identity' => $identity]);

    return Str::isUuid($reference ?? '') ? $message.' '.self::translate('reference', ['reference' => $reference]) : $message;
  }

  private function validationError(ValidationException $exception): ValidationException
  {
    $messages = $exception->errors();
    $field = array_key_first($messages) ?? 'package';
    $messages[$field][0] = trim(($messages[$field][0] ?? '').' '.$this->fail());

    return ValidationException::withMessages($messages)
      ->status($exception->status)
      ->errorBag($exception->errorBag);
  }

  private static function translate(string $key, array $replace = []): string
  {
    return app(CmsTranslator::class)->admin('extension_errors.'.$key, app(AdminLocaleResolver::class)->locale(), $replace);
  }

  private static function safeIdentifier(?string $value): ?string
  {
    return $value !== null && strlen($value) <= 190 && preg_match('/\A[a-z0-9]+(?:[-_][a-zA-Z0-9]+)*\z/', $value) ? $value : null;
  }

  private static function safeVersion(?string $value): ?string
  {
    return $value !== null && strlen($value) <= 64 && preg_match('/\Av?[0-9][a-zA-Z0-9.+-]*\z/', $value) ? $value : null;
  }

  private static function safeLabel(?string $value): ?string
  {
    return $value !== null && mb_strlen($value) <= 100 && preg_match("/\\A[\\p{L}\\p{N}][\\p{L}\\p{N}\\p{M} ._'&()+-]*\\z/u", $value) ? $value : null;
  }
}
