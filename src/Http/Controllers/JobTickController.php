<?php

namespace WebBlocks\Cms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Support\Jobs\JobRunner;
use WebBlocks\Cms\Support\Jobs\JobSchema;

final class JobTickController
{
  public function __invoke(Request $request, JobRunner $runner)
  {
    abort_unless($request->isSecure() && JobSchema::ready(), 404);
    $automation = DB::table('wbcms_job_automation')->where('id', 1)->first();
    $token = $request->bearerToken();
    abort_unless($automation?->http_enabled && is_string($token) && strlen($token) === 64 && $automation->token_hash && hash_equals($automation->token_hash, hash('sha256', $token)), 401);
    $runner->tick('http');

    return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
  }
}
