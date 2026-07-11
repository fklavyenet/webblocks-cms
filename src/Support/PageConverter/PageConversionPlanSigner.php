<?php

namespace WebBlocks\Cms\Support\PageConverter;

class PageConversionPlanSigner
{
  public function sign(string $payload): string
  {
    return hash_hmac('sha256', $payload, $this->key());
  }

  public function verify(string $payload, string $signature): bool
  {
    if ($payload === '' || $signature === '') {
      return false;
    }

    return hash_equals($this->sign($payload), $signature);
  }

  private function key(): string
  {
    return (string) config('app.key');
  }
}
