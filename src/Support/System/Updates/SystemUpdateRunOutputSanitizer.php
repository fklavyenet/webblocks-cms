<?php

namespace WebBlocks\Cms\Support\System\Updates;

class SystemUpdateRunOutputSanitizer
{
  public function sanitize(?string $value, int $maxLength = 4000): string
  {
    $sanitized = trim((string) $value);

    if ($sanitized === '') {
      return '';
    }

    $sanitized = str_replace(base_path(), '[base_path]', $sanitized);
    $sanitized = str_replace(storage_path(), '[storage_path]', $sanitized);
    $sanitized = preg_replace('/([?&](?:password|passwd|pwd|token|api[_-]?key|secret)=)[^&\s]+/i', '$1[redacted]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\b(password|passwd|pwd|token|api[_-]?key|secret)=\S+/i', '$1=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/--defaults-extra-file=\S+/i', '--defaults-extra-file=[redacted]', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\b([A-Z_]*(?:PASSWORD|TOKEN|SECRET|APP_KEY))=[^\s]+/i', '$1=[redacted]', $sanitized) ?? $sanitized;

    $lines = collect(preg_split('/\r\n|\r|\n/', $sanitized) ?: [])
      ->reject(fn (string $line): bool => preg_match('/^\s*#\d+\s+/', $line) === 1)
      ->reject(fn (string $line): bool => str_contains($line, 'Stack trace:'))
      ->values()
      ->implode(PHP_EOL);

    if (mb_strlen($lines) > $maxLength) {
      return mb_substr($lines, 0, $maxLength).'...';
    }

    return $lines;
  }
}
