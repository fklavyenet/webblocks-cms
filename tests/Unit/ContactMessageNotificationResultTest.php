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
    $skipped = ContactMessageNotificationResult::skipped('Notification disabled.');
    $notConfigured = ContactMessageNotificationResult::notConfigured('Mail transport is not configured.');
    $sent = ContactMessageNotificationResult::sent('notify@example.com', 'block');
    $failed = ContactMessageNotificationResult::failed('notify@example.com', 'Delivery failed.', 'site');

    $this->assertFalse($skipped->enabled);
    $this->assertNull($skipped->recipient);
    $this->assertNull($skipped->error);
    $this->assertFalse($skipped->sent);
    $this->assertSame('skipped', $skipped->status);
    $this->assertSame('Notification disabled.', $skipped->reason);

    $this->assertTrue($notConfigured->enabled);
    $this->assertNull($notConfigured->recipient);
    $this->assertNull($notConfigured->error);
    $this->assertFalse($notConfigured->sent);
    $this->assertSame('not_configured', $notConfigured->status);
    $this->assertSame('Mail transport is not configured.', $notConfigured->reason);

    $this->assertTrue($sent->enabled);
    $this->assertSame('notify@example.com', $sent->recipient);
    $this->assertNull($sent->error);
    $this->assertTrue($sent->sent);
    $this->assertSame('sent', $sent->status);
    $this->assertSame('block', $sent->recipientSource);

    $this->assertTrue($failed->enabled);
    $this->assertSame('notify@example.com', $failed->recipient);
    $this->assertSame('Delivery failed.', $failed->error);
    $this->assertFalse($failed->sent);
    $this->assertSame('failed', $failed->status);
    $this->assertSame('site', $failed->recipientSource);
  }
}
