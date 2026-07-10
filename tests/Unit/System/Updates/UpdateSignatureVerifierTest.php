<?php

namespace Tests\Unit\System\Updates;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdateSignatureVerifier;

class UpdateSignatureVerifierTest extends TestCase
{
  private UpdateSignatureVerifier $verifier;

  private string $checksum;

  private string $publicKeyBase64;

  private string $secretKey;

  protected function setUp(): void
  {
    parent::setUp();

    $this->verifier = new UpdateSignatureVerifier;
    $this->checksum = hash('sha256', 'demo-artifact-contents');

    $keyPair = sodium_crypto_sign_keypair();
    $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
    $this->publicKeyBase64 = base64_encode(sodium_crypto_sign_publickey($keyPair));
  }

  private function signWith(string $secretKey, string $checksum): string
  {
    return base64_encode(sodium_crypto_sign_detached(strtolower($checksum), $secretKey));
  }

  private function assertBlocks(array $release): void
  {
    $this->expectException(UpdateException::class);
    $output = [];
    $this->verifier->verify($this->checksum, $release, $output);
  }

  #[Test]
  public function it_skips_verification_when_no_public_key_is_configured(): void
  {
    config(['webblocks-updates.signature.public_key' => '']);

    $output = [];
    $this->verifier->verify($this->checksum, ['signature' => null], $output);

    $this->assertNotContains('Release signature verified.', $output);
  }

  #[Test]
  public function it_accepts_a_valid_signature(): void
  {
    config(['webblocks-updates.signature.public_key' => $this->publicKeyBase64]);

    $output = [];
    $this->verifier->verify($this->checksum, ['signature' => $this->signWith($this->secretKey, $this->checksum)], $output);

    $this->assertContains('Release signature verified.', $output);
  }

  #[Test]
  public function it_rejects_a_missing_signature_when_a_key_is_configured(): void
  {
    config(['webblocks-updates.signature.public_key' => $this->publicKeyBase64]);

    $this->assertBlocks(['signature' => null]);
  }

  #[Test]
  public function it_rejects_a_signature_made_over_a_different_checksum(): void
  {
    config(['webblocks-updates.signature.public_key' => $this->publicKeyBase64]);

    $this->assertBlocks(['signature' => $this->signWith($this->secretKey, hash('sha256', 'a-different-artifact'))]);
  }

  #[Test]
  public function it_rejects_a_signature_from_a_different_key(): void
  {
    $otherSecret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
    config(['webblocks-updates.signature.public_key' => $this->publicKeyBase64]);

    $this->assertBlocks(['signature' => $this->signWith($otherSecret, $this->checksum)]);
  }

  #[Test]
  public function it_rejects_a_misconfigured_public_key(): void
  {
    config(['webblocks-updates.signature.public_key' => 'this-is-not-a-valid-key']);

    $this->assertBlocks(['signature' => $this->signWith($this->secretKey, $this->checksum)]);
  }
}
