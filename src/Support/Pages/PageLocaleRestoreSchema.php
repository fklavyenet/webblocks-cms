<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

final class PageLocaleRestoreSchema
{
  public static function ensure(): void
  {
    PersistentSchema::ensure();
    $name = 'wbcms_page_revision_candidates';
    if (! Schema::hasColumn($name, 'restore_scope')) {
      Schema::table($name, fn (Blueprint $table) => $table->string('restore_scope', 24)->default('full_page'));
    }
    if (! Schema::hasColumn($name, 'restore_locale_id')) {
      // Historical audit identity survives locale deletion; apply validates it.
      Schema::table($name, fn (Blueprint $table) => $table->unsignedBigInteger('restore_locale_id')->nullable());
    }
    foreach (['source_fingerprint', 'revision_fingerprint', 'preview_fingerprint'] as $field) {
      if (! Schema::hasColumn($name, $field)) {
        Schema::table($name, fn (Blueprint $table) => $table->char($field, 64)->nullable());
      }
    }
  }
}
