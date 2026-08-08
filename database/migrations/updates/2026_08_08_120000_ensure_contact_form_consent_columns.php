<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The native contact form stores submissions but had no way to record that the
 * visitor agreed to that storage. Sites that need the agreement — a German site
 * keeping submissions is the ordinary case — had to demote the wording into the
 * form's intro prose, which is text beside the form rather than a fact attached
 * to the submission.
 *
 * consent_label on the translation table carries the per-locale wording the
 * visitor actually saw. The pair on the submission records the decision: when
 * it was given, and a copy of the exact wording it was given against, so the
 * record stays provable after the block's copy is edited.
 */
return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_block_contact_form_translations')) {
      Schema::table('wbcms_block_contact_form_translations', function (Blueprint $table) {
        if (! Schema::hasColumn('wbcms_block_contact_form_translations', 'consent_label')) {
          $table->longText('consent_label')->nullable();
        }
      });
    }

    if (Schema::hasTable('wbcms_contact_messages')) {
      Schema::table('wbcms_contact_messages', function (Blueprint $table) {
        if (! Schema::hasColumn('wbcms_contact_messages', 'consent_accepted_at')) {
          $table->timestamp('consent_accepted_at')->nullable();
        }

        if (! Schema::hasColumn('wbcms_contact_messages', 'consent_label')) {
          $table->longText('consent_label')->nullable();
        }
      });
    }
  }

  public function down(): void {}
};
