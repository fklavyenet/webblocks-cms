<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (Schema::hasTable('wbcms_submission_daily_totals')) {
      return;
    }

    Schema::create('wbcms_submission_daily_totals', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->date('date');
      $table->string('surface', 40);
      $table->unsignedInteger('allowed')->default(0);
      $table->unsignedInteger('quarantined')->default(0);
      $table->unsignedInteger('spam')->default(0);
      $table->timestamps();

      $table->unique(['site_id', 'date', 'surface'], 'wbcms_sub_day_site_date_surface_uq');
      $table->index(['site_id', 'date'], 'wbcms_sub_day_site_date_idx');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('wbcms_submission_daily_totals');
  }
};
