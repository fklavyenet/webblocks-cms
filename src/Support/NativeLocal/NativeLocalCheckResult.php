<?php

namespace WebBlocks\Cms\Support\NativeLocal;

final class NativeLocalCheckResult
{
  public function __construct(
    public readonly string $status,
    public readonly string $label,
    public readonly string $message,
    public readonly ?string $recommendation = null,
    public readonly bool $critical = false,
  ) {}

  public static function pass(string $label, string $message, ?string $recommendation = null): self
  {
    return new self('pass', $label, $message, $recommendation);
  }

  public static function warn(string $label, string $message, ?string $recommendation = null): self
  {
    return new self('warn', $label, $message, $recommendation);
  }

  public static function fail(string $label, string $message, ?string $recommendation = null, bool $critical = true): self
  {
    return new self('fail', $label, $message, $recommendation, $critical);
  }
}
