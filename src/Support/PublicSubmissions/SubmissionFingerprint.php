<?php

namespace WebBlocks\Cms\Support\PublicSubmissions;

final class SubmissionFingerprint
{
  public function exact(string $text): string
  {
    return hash_hmac('sha256', $text, (string) config('app.key'));
  }

  public function similar(string $text): string
  {
    $tokens = preg_split('/[^\pL\pN]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $weights = array_fill(0, 64, 0);

    foreach (array_unique(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3)) as $token) {
      $bits = hex2bin(substr(hash('sha256', $token), 0, 16));

      if ($bits === false) {
        continue;
      }

      for ($byte = 0; $byte < 8; $byte++) {
        $value = ord($bits[$byte]);

        for ($bit = 0; $bit < 8; $bit++) {
          $index = ($byte * 8) + $bit;
          $weights[$index] += ($value & (1 << $bit)) !== 0 ? 1 : -1;
        }
      }
    }

    $hex = '';

    for ($nibble = 0; $nibble < 16; $nibble++) {
      $value = 0;

      for ($bit = 0; $bit < 4; $bit++) {
        if ($weights[($nibble * 4) + $bit] >= 0) {
          $value |= 1 << $bit;
        }
      }

      $hex .= dechex($value);
    }

    return $hex;
  }

  public function distance(string $left, string $right): int
  {
    $bits = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
    $distance = 0;

    for ($index = 0; $index < 16; $index++) {
      $distance += $bits[hexdec($left[$index] ?? '0') ^ hexdec($right[$index] ?? '0')];
    }

    return $distance;
  }
}
