<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\PageConverter\PageConversionPlanSigner;

class PageConversionPlanSignerTest extends TestCase
{
  #[Test]
  public function it_accepts_original_payload_signature_and_rejects_tampered_payload(): void
  {
    $signer = new PageConversionPlanSigner;
    $payload = base64_encode('{"version":1,"blocks":[]}');
    $signature = $signer->sign($payload);

    $this->assertTrue($signer->verify($payload, $signature));
    $this->assertFalse($signer->verify($payload.'tampered', $signature));
    $this->assertFalse($signer->verify($payload, str_repeat('0', 64)));
  }
}
