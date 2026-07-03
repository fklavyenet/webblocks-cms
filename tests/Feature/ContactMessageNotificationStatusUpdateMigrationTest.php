<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactMessageNotificationStatusUpdateMigrationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function update_migration_adds_contact_message_notification_status_columns(): void
  {
    foreach (['notification_reason', 'notification_status', 'notification_recipient_source'] as $column) {
      if (Schema::hasColumn('wbcms_contact_messages', $column)) {
        Schema::table('wbcms_contact_messages', fn ($table) => $table->dropColumn($column));
      }
    }

    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_06_25_120000_ensure_contact_message_notification_status_fields.php');
    $migration->up();

    foreach (['notification_recipient_source', 'notification_status', 'notification_reason'] as $column) {
      $this->assertTrue(Schema::hasColumn('wbcms_contact_messages', $column), 'Missing wbcms_contact_messages column: '.$column);
    }
  }
}
