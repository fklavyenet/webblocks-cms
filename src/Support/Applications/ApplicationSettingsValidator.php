<?php

namespace WebBlocks\Cms\Support\Applications;

class ApplicationSettingsValidator
{
  public function normalize(ApplicationDefinition $definition, mixed $settings, string $path, array &$errors): array
  {
    if (! is_array($settings)) {
      $errors[] = $this->error($path, 'Application settings must be an object.', 'application_setting_invalid');

      return [];
    }

    $unknown = array_values(array_diff(array_keys($settings), array_keys($definition->settingsSchema)));
    foreach ($unknown as $key) {
      $errors[] = $this->error($path.'.'.$key, 'Application setting is not declared by the selected application.', 'application_setting_unknown');
    }

    $normalized = [];

    foreach ($definition->settingsSchema as $key => $schema) {
      $hasValue = array_key_exists($key, $settings);
      $value = $hasValue ? $settings[$key] : ($schema['default'] ?? null);

      if (! $hasValue && ! array_key_exists('default', $schema)) {
        continue;
      }

      $valid = true;
      $normalizedValue = match ($schema['type']) {
        'boolean' => $this->boolean($value, $valid),
        'enum' => $this->enum($value, $schema['values'] ?? [], $valid),
        'integer' => $this->integer($value, $schema, $valid),
        'string' => $this->string($value, $schema, $valid),
        default => null,
      };

      if (! $valid) {
        $errors[] = $this->error($path.'.'.$key, 'Application setting does not match its declared schema.', 'application_setting_invalid');

        continue;
      }

      $normalized[$key] = $normalizedValue;
    }

    return $normalized;
  }

  private function boolean(mixed $value, bool &$valid): bool
  {
    $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $valid = $parsed !== null;

    return $parsed ?? false;
  }

  private function enum(mixed $value, array $values, bool &$valid): mixed
  {
    $index = array_search((string) $value, array_map(fn ($option): string => (string) $option, $values), true);
    $valid = $index !== false;

    return $valid ? $values[$index] : $value;
  }

  private function integer(mixed $value, array $schema, bool &$valid): int
  {
    $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
    $integer = (int) $value;

    if (isset($schema['min']) && $integer < (int) $schema['min']) {
      $valid = false;
    }

    if (isset($schema['max']) && $integer > (int) $schema['max']) {
      $valid = false;
    }

    return $integer;
  }

  private function string(mixed $value, array $schema, bool &$valid): string
  {
    $valid = is_string($value) || is_numeric($value);
    $string = trim((string) $value);

    if (isset($schema['max_length']) && mb_strlen($string) > (int) $schema['max_length']) {
      $valid = false;
    }

    return $string;
  }

  private function error(string $path, string $message, string $code): array
  {
    return compact('path', 'message', 'code');
  }
}
