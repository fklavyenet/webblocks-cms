<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * @var list<string>
   */
  private array $colourColumns = [
    'brand_accent',
    'brand_accent_secondary',
    'brand_surface',
    'brand_text',
  ];

  /**
   * @var list<string>
   */
  private array $fontColumns = [
    'brand_font_heading',
    'brand_font_body',
  ];

  public function up(): void
  {
    if (! Schema::hasTable('wbcms_sites')) {
      return;
    }

    Schema::table('wbcms_sites', function (Blueprint $table) {
      foreach ($this->colourColumns as $column) {
        if (! Schema::hasColumn('wbcms_sites', $column)) {
          $table->string($column, 7)->nullable();
        }
      }

      foreach ($this->fontColumns as $column) {
        if (! Schema::hasColumn('wbcms_sites', $column)) {
          $table->string($column, 180)->nullable();
        }
      }
    });
  }

  public function down(): void {}
};
