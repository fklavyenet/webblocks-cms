<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Support\Jobs\JobSchema;

return new class extends Migration
{
  public function up(): void
  {
    JobSchema::up();
  }

  public function down(): void
  {
    if (Schema::hasColumn('wbcms_job_runs', 'policy_hash')) {
      Schema::table('wbcms_job_runs', fn (Blueprint $t) => $t->dropColumn('policy_hash'));
    }
  }
};
