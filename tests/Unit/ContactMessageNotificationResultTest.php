<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Contact\ContactMessageNotificationResult;

class ContactMessageNotificationResultTest extends TestCase
{
    #[Test]
    public function it_builds_skipped_sent_and_failed_results_without_changing_values(): void
    {
        $skipped = ContactMessageNotificationResult::skipped();
        $sent = ContactMessageNotificationResult::sent('notify@example.com');
        $failed = ContactMessageNotificationResult::failed('notify@example.com', 'Delivery failed.');

        $this->assertFalse($skipped->enabled);
        $this->assertNull($skipped->recipient);
        $this->assertNull($skipped->error);
        $this->assertFalse($skipped->sent);

        $this->assertTrue($sent->enabled);
        $this->assertSame('notify@example.com', $sent->recipient);
        $this->assertNull($sent->error);
        $this->assertTrue($sent->sent);

        $this->assertTrue($failed->enabled);
        $this->assertSame('notify@example.com', $failed->recipient);
        $this->assertSame('Delivery failed.', $failed->error);
        $this->assertFalse($failed->sent);
    }
}
