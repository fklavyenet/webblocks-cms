<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Relational\PersistentSchema;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    $translations = 'wbcms_block_contact_form_translations';
    $labels = array_diff(['name_label', 'email_label', 'subject_label', 'message_label'], Schema::getColumnListing($translations));
    if ($labels !== []) {
      Schema::table($translations, function (Blueprint $table) use ($labels): void {
        foreach ($labels as $label) {
          $table->string($label)->nullable();
        }
      });
    }

    $messages = 'wbcms_contact_messages';
    if (! collect(Schema::getColumns($messages))->firstWhere('name', 'name')['nullable']) {
      Schema::table($messages, fn (Blueprint $table) => $table->string('name')->nullable()->change());
    }

    StructuredSettingsSchema::ensure();
    PersistentSchema::ensure();
  }

  public function down(): void
  {
    // Keep authored labels, snapshots and nameless submissions on rollback.
  }
};
