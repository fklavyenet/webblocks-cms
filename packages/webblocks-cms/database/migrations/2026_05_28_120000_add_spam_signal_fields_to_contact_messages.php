<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('contact_messages', function (Blueprint $table): void {
      if (! Schema::hasColumn('contact_messages', 'spam_score')) {
        $table->unsignedSmallInteger('spam_score')->default(0)->after('referer');
        $table->index(['spam_score', 'created_at']);
      }

      if (! Schema::hasColumn('contact_messages', 'spam_reasons')) {
        $table->json('spam_reasons')->nullable()->after('spam_score');
      }
    });
  }

  public function down(): void
  {
    Schema::table('contact_messages', function (Blueprint $table): void {
      if (Schema::hasColumn('contact_messages', 'spam_score')) {
        $table->dropIndex(['spam_score', 'created_at']);
        $table->dropColumn('spam_score');
      }

      if (Schema::hasColumn('contact_messages', 'spam_reasons')) {
        $table->dropColumn('spam_reasons');
      }
    });
  }
};
