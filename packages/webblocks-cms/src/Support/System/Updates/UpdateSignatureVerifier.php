<?php

namespace WebBlocks\Cms\Support\System\Updates;

/**
 * Verifies the Ed25519 signature over a release's SHA-256 checksum.
 *
 * A checksum alone protects against a corrupted or tampered download, but it
 * travels with the artifact from the same update service, so it cannot protect
 * against a compromised update service. Signing the checksum with a maintainer-
 * held private key, and verifying it here against a public key pinned in the CMS
 * code, means an install rejects any release that was not signed by the real key.
 */
class UpdateSignatureVerifier
{
  public function verify(string $checksumSha256, array $release, array &$output): void
  {
    $publicKey = $this->publicKey();

    if ($publicKey === null) {
      // No public key is pinned yet, so signature verification is not enforced.
      // Checksum verification still protects the download.
      return;
    }

    $signature = $this->decodeBase64((string) ($release['signature'] ?? ''));

    if ($signature === null) {
      throw new UpdateException(
        'The update was blocked because the release is not signed.',
        'A signing public key is configured but the release metadata has no valid signature.'
      );
    }

    $message = $this->normalizeChecksum($checksumSha256);

    if (! sodium_crypto_sign_verify_detached($signature, $message, $publicKey)) {
      throw new UpdateException(
        'The update was blocked because the release signature could not be verified.',
        'Ed25519 signature verification failed for the release checksum.'
      );
    }

    $output[] = 'Release signature verified.';
  }

  private function publicKey(): ?string
  {
    $configured = trim((string) config('webblocks-updates.signature.public_key', ''));

    if ($configured === '') {
      return null;
    }

    $key = $this->decodeBase64($configured);

    if ($key === null || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
      throw new UpdateException(
        'The update signing public key is misconfigured.',
        'webblocks-updates.signature.public_key is not a valid base64 Ed25519 public key.'
      );
    }

    return $key;
  }

  private function normalizeChecksum(string $checksum): string
  {
    return strtolower(trim($checksum));
  }

  private function decodeBase64(string $value): ?string
  {
    $value = trim($value);

    if ($value === '') {
      return null;
    }

    $decoded = base64_decode($value, true);

    return $decoded === false ? null : $decoded;
  }
}
