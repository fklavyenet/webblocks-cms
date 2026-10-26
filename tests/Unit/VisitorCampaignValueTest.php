<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Visitors\VisitorCampaignValue;

class VisitorCampaignValueTest extends TestCase
{
  #[Test]
  #[DataProvider('values')]
  public function markup_classification_preserves_plain_campaign_names(?string $value, bool $flagged): void
  {
    $this->assertSame($flagged, VisitorCampaignValue::containsMarkup($value));
  }

  public static function values(): array
  {
    return [
      [null, false], ['', false], ['chatgpt.com', false],
      ['Summer Sale 2026', false], ['Bahar – München 日本語', false],
      ['AT&T / R&D + email', false], ['"Quoted" campaign', false],
      ['Savings < 50% > last year', false], ['We <3 customers', false],
      ['100% organic', false], ['https://example.test/a?b=c&d=e', false],
      ['script and svg onload=notes', false], ['Spring &amp; Summer', false],
      ['</script><svg/onload=confirm("cms-probe")>', true],
      ['<IMG SRC=x ONERROR=confirm(1)>', true], ['<b>Sale</b>', true],
      ['<custom-element data-value="sale">', true], ['<svg', true],
      ['</script', true], ['<!--probe-->', true], ['<?probe?>', true],
      ['&lt;svg/onload=confirm(1)&gt;', true],
      ['&#60;svg/onload=confirm(1)&#62;', true],
      ['&#x3c;svg/onload=confirm(1)&#x3e;', true],
      ['%3Csvg%2Fonload%3Dconfirm(1)%3E', true],
      ['%253Csvg%252Fonload%253Dconfirm(1)%253E', true],
      ['%26lt%3Bscript%26gt%3Bprobe', true],
      ['&amp;lt;script&amp;gt;probe', true],
      [rawurlencode(rawurlencode(rawurlencode('<svg>'))), true],
    ];
  }
}
