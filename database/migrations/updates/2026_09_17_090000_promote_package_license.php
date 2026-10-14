<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
  public function up(): void
  {
    $root = dirname(__DIR__, 3);
    if (File::isFile($root.'/LICENSE')) {
      return;
    }
    if (! File::isFile($root.'/docs/LICENSE') || ! File::copy($root.'/docs/LICENSE', $root.'/LICENSE')) {
      throw new RuntimeException('The package MIT license could not be restored.');
    }
  }

  public function down(): void
  {
    // The MIT notice remains part of the installed package on rollback.
  }
};
