<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Schema\Blueprint;

final class SettingScalar
{
  public static function columns(Blueprint $table, string $name, string $type): void
  {
    match ($type) {
      'integer' => $table->bigInteger($name)->nullable(),
      'boolean' => $table->boolean($name)->nullable(),
      default => $table->longText($name)->nullable(),
    };
    $table->string($name.'_kind', 12)->nullable();
    $table->longText($name.'_text')->nullable();
    if ($type === 'variant') {
      $table->bigInteger($name.'_integer')->nullable();
      $table->double($name.'_decimal')->nullable();
      $table->boolean($name.'_boolean')->nullable();
    }
  }

  public static function pack(string $name, string $type, mixed $value): ?array
  {
    $kind = match (true) {
      $value === null => 'null', is_string($value) => 'string', is_int($value) => 'integer',
      is_bool($value) => 'boolean', is_float($value) && is_finite($value) => 'decimal', default => null,
    };
    if ($kind === null) {
      return null;
    }
    $row = [$name => null, $name.'_kind' => $kind, $name.'_text' => null];
    if ($kind === 'null') {
      return $row;
    }
    if ($type === 'variant') {
      $column = match ($kind) {
        'integer' => $name.'_integer', 'decimal' => $name.'_decimal', 'boolean' => $name.'_boolean', default => $name,
      };
      $row[$column] = $value;

      return $row;
    }
    if ($type === 'text' && $kind === 'string') {
      $row[$name] = $value;
    } elseif ($type === 'integer' && ($kind === 'integer' || ($kind === 'string' && filter_var($value, FILTER_VALIDATE_INT) !== false))) {
      $row[$name] = (int) $value;
      $row[$name.'_text'] = $kind === 'string' ? $value : null;
    } elseif ($type === 'boolean' && $kind === 'boolean') {
      $row[$name] = $value;
    } else {
      return null;
    }

    return $row;
  }

  public static function unpack(object $row, string $name, string $type): mixed
  {
    $kind = $row->{$name.'_kind'};
    if ($kind === null || $kind === 'null') {
      return null;
    }
    $column = $type === 'variant' ? match ($kind) {
      'integer' => $name.'_integer', 'decimal' => $name.'_decimal', 'boolean' => $name.'_boolean', default => $name,
    } : $name;
    $value = $row->{$column};

    return match ($kind) {
      'boolean' => (bool) $value, 'integer' => (int) $value, 'decimal' => (float) $value,
      default => $row->{$name.'_text'} ?? (string) $value,
    };
  }
}
