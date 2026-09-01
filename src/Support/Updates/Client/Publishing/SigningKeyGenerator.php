<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Publishing;

/**
 * Generates an Ed25519 keypair for release signing (§7.8). The owner keeps the
 * secret (WEBBLOCKS_PUBLISHER_SIGNING_KEY, never shipped) and pins the public key
 * in every product (publisher-client.signature.public_key). One org-wide keypair
 * for now; per-product later.
 */
final class SigningKeyGenerator
{
    /**
     * @return array{signing_key: string, public_key: string} base64-encoded
     */
    public function generate(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'signing_key' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }
}
