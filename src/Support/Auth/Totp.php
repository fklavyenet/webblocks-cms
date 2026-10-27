<?php

namespace WebBlocks\Cms\Support\Auth;

use InvalidArgumentException;

/** CMS TOTP profile: RFC 6238, HMAC-SHA-1, six digits, 30-second steps. */
final class Totp
{
  private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

  public function generateSecret(): string
  {
    $secret = '';
    foreach (str_split(random_bytes(32)) as $byte) {
      // 256 is divisible by 32: taking five random bits introduces no bias.
      $secret .= self::ALPHABET[ord($byte) & 31];
    }

    return $secret;
  }

  public function code(#[\SensitiveParameter] string $secret, int $step, int $digits = 6): string
  {
    $key = $this->decodeSecret($secret);
    if ($key === null || $step < 0 || ! in_array($digits, [6, 8], true)) {
      throw new InvalidArgumentException('Invalid TOTP parameters.');
    }

    return $this->hotp($key, $step, $digits);
  }

  public function verify(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, int $lastStep, int $unixTime): int|false
  {
    $key = $this->decodeSecret($secret);
    if ($key === null || $unixTime < 0 || ! preg_match('/^[0-9]{6}$/D', $code)) {
      return false;
    }
    $currentStep = intdiv($unixTime, 30);
    // Check a fixed window; never search forward from an untrusted stored step.
    for ($step = max(0, $currentStep - 1); $step <= $currentStep + 1; $step++) {
      if ($step > $lastStep && hash_equals($this->hotp($key, $step, 6), $code)) {
        return $step;
      }
    }

    return false;
  }

  private function hotp(#[\SensitiveParameter] string $key, int $step, int $digits): string
  {
    $counter = pack('N2', $step >> 32, $step & 0xFFFFFFFF);
    $mac = hash_hmac('sha1', $counter, $key, true);
    $offset = ord($mac[19]) & 15;
    $value = unpack('N', substr($mac, $offset, 4))[1] & 0x7FFFFFFF;

    return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
  }

  private function decodeSecret(#[\SensitiveParameter] string $secret): ?string
  {
    // Exactly 160 bits, encoded as 32 unpadded Base32 characters.
    if (! preg_match('/^[A-Z2-7]{32}$/D', $secret)) {
      return null;
    }
    $decoded = '';
    $buffer = 0;
    $bits = 0;
    foreach (str_split($secret) as $character) {
      $buffer = ($buffer << 5) | strpos(self::ALPHABET, $character);
      $bits += 5;
      if ($bits >= 8) {
        $bits -= 8;
        $decoded .= chr(($buffer >> $bits) & 255);
      }
      $buffer &= (1 << $bits) - 1;
    }

    return $decoded;
  }
}
