<?php

namespace WebBlocks\Cms\Support\InternalApiTokens;

use Illuminate\Http\Request;
use WebBlocks\Cms\Models\CmsApiToken;

class CmsApiTokenAuthenticator
{
  public function __construct(private readonly CmsApiTokenIssuer $issuer) {}

  public function authenticate(string $plainToken, Request $request): ?CmsApiToken
  {
    $plainToken = trim($plainToken);

    if ($plainToken === '') {
      return null;
    }

    $candidateHash = $this->issuer->hash($plainToken);

    /** @var CmsApiToken|null $token */
    $token = CmsApiToken::query()
      ->active()
      ->where('token_hash', $candidateHash)
      ->first();

    if (! $token || ! hash_equals($token->token_hash, $candidateHash)) {
      return null;
    }

    $token->forceFill([
      'last_used_at' => now(),
      'last_used_ip' => $request->ip(),
    ])->save();

    return $token;
  }
}
