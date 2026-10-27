<?php

namespace WebBlocks\Cms\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Support\Auth\Totp;

class TotpTest extends TestCase
{
  // Public RFC 4226/6238 fixture: ASCII "12345678901234567890", in Base32.
  private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

  public static function referenceVectors(): array
  {
    $vectors = [];
    // RFC 4226 Appendix D: six-digit HOTP values for counters 0 through 9.
    foreach (['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'] as $step => $code) {
      $vectors['HOTP '.$step] = [$step, 6, $code];
    }
    // RFC 6238 Appendix B: SHA-1 values, including times beyond 2038.
    foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $code) {
      $vectors['TOTP '.$time] = [intdiv($time, 30), 8, $code];
    }

    return $vectors;
  }

  #[DataProvider('referenceVectors')]
  public function test_matches_the_published_reference_vectors(int $step, int $digits, string $expected): void
  {
    $this->assertSame($expected, (new Totp)->code(self::SECRET, $step, $digits));
  }

  public function test_accepts_only_a_single_step_of_clock_drift_and_rejects_replay(): void
  {
    $totp = new Totp;
    $this->assertSame(0, $totp->verify(self::SECRET, '755224', -1, 0));
    $this->assertFalse($totp->verify(self::SECRET, '755224', 0, 0));
    $this->assertSame(1, $totp->verify(self::SECRET, '287082', -1, 0));
    $this->assertSame(0, $totp->verify(self::SECRET, '755224', -1, 30));
    $this->assertFalse($totp->verify(self::SECRET, '755224', -1, 60));
    $this->assertFalse($totp->verify(self::SECRET, '287082', -1, 90));
    $this->assertFalse($totp->verify(self::SECRET, '287082', 2, 30));
    $this->assertFalse($totp->verify(self::SECRET, '287082', PHP_INT_MAX, 30));
    $this->assertFalse($totp->verify(self::SECRET, '287082', -1, -1));
  }

  public function test_rejects_malformed_secrets_and_codes_without_coercion(): void
  {
    $totp = new Totp;
    foreach (['', str_repeat('A', 16), str_repeat('A', 31), str_repeat('A', 33), str_repeat('0', 32), strtolower(self::SECRET), self::SECRET.'=', self::SECRET."\n"] as $secret) {
      $this->assertFalse($totp->verify($secret, '287082', -1, 30));
    }
    foreach (['', '28708', '0287082', '287082 ', "287082\n", '２８７０８２', '+287082', '28708x'] as $code) {
      $this->assertFalse($totp->verify(self::SECRET, $code, -1, 30));
    }
  }

  public function test_generates_a_160_bit_base32_setup_key(): void
  {
    $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/D', (new Totp)->generateSecret());
  }

  public function test_counter_encoding_includes_the_high_32_bits(): void
  {
    // Independently calculated with Python's standard hmac/struct modules.
    $this->assertSame('999456', (new Totp)->code(self::SECRET, 4294967296));
  }

  public function test_invalid_generation_parameters_do_not_disclose_the_secret(): void
  {
    try {
      (new Totp)->code(self::SECRET, -1);
      $this->fail('A negative counter must be rejected.');
    } catch (InvalidArgumentException $exception) {
      $this->assertStringNotContainsString(self::SECRET, $exception->getMessage());
    }
  }

  public function test_runs_without_composer_or_any_third_party_autoloader(): void
  {
    $source = dirname(__DIR__, 2).'/src/Support/Auth/Totp.php';
    $process = new Process([PHP_BINARY, '-n', '-r', 'require $argv[1]; echo (new WebBlocks\\Cms\\Support\\Auth\\Totp)->code("'.self::SECRET.'", 1);', $source]);
    $process->mustRun();
    $this->assertSame('287082', $process->getOutput());
  }
}
