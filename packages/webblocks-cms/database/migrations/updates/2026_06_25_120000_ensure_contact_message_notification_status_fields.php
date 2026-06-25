<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('contact_messages', function (Blueprint $table): void {
      if (! Schema::hasColumn('contact_messages', 'notification_recipient_source')) {
        $table->string('notification_recipient_source')->nullable()->after('notification_recipient');
      }

      if (! Schema::hasColumn('contact_messages', 'notification_status')) {
        $table->string('notification_status')->nullable()->after('notification_recipient_source');
      }

      if (! Schema::hasColumn('contact_messages', 'notification_reason')) {
        $table->text('notification_reason')->nullable()->after('notification_error');
      }
    });
  }

  public function down(): void
  {
    Schema::table('contact_messages', function (Blueprint $table): void {
      if (Schema::hasColumn('contact_messages', 'notification_reason')) {
        $table->dropColumn('notification_reason');
      }

      if (Schema::hasColumn('contact_messages', 'notification_status')) {
        $table->dropColumn('notification_status');
      }

      if (Schema::hasColumn('contact_messages', 'notification_recipient_source')) {
        $table->dropColumn('notification_recipient_source');
      }
    });
  }
};
