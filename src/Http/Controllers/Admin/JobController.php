<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Actions\Jobs\TransitionJobRun;
use WebBlocks\Cms\Http\Requests\Admin\JobRequest;
use WebBlocks\Cms\Models\JobRun;
use WebBlocks\Cms\Queries\JobQuery;
use WebBlocks\Cms\Support\Jobs\JobAutomation;
use WebBlocks\Cms\Support\Jobs\JobProgress;
use WebBlocks\Cms\Support\Jobs\JobRegistry;
use WebBlocks\Cms\Support\Jobs\JobRunner;
use WebBlocks\Cms\Support\Jobs\JobSchema;

final class JobController
{
  public function seo(JobRequest $request, JobQuery $query)
  {
    return response()->view('webblocks-cms::admin.sites.seo-index', $query->read($request))->header('Cache-Control', 'private, no-store');
  }

  public function index(JobRequest $request, JobQuery $query, JobAutomation $automation)
  {
    $data = $query->read($request);

    return response()->view('webblocks-cms::admin.system.jobs', [...$data, 'automation' => $data['system'] ? $automation->status() : null])->header('Cache-Control', 'private, no-store');
  }

  public function store(JobRequest $request, JobRegistry $registry, JobRunner $runner)
  {
    abort_unless(JobSchema::ready(), 409);
    $definition = $registry->get((string) $request->validated('job_key'));
    abort_unless($definition && $definition->key !== 'seo', 422);
    abort_if($definition->effects && ! $request->boolean('confirmed'), 422);
    if (! $definition->web) {
      return redirect()->route('admin.jobs.index')->withErrors(['job_key' => __('webblocks-cms::jobs.web_unavailable')]);
    }
    $run = $runner->request($definition->key, actor: $request->user()->id);

    return redirect()->route('admin.jobs.index')->with('status', __('webblocks-cms::jobs.queued', ['id' => $run->id]))->with('job_manual_run', $run->id);
  }

  public function step(JobRequest $request, JobRun $jobRun, JobRunner $runner)
  {
    abort_unless(app(JobRegistry::class)->get($jobRun->job_key)?->web, 409);
    $run = $runner->advance($jobRun);

    return response()->json(JobProgress::payload($run))->header('Cache-Control', 'private, no-store');
  }

  public function progress(JobRequest $request, JobRun $jobRun)
  {
    return response()->json(JobProgress::payload($jobRun))->header('Cache-Control', 'private, no-store');
  }

  public function transition(JobRequest $request, JobRun $jobRun)
  {
    app(TransitionJobRun::class)->execute($jobRun, (string) $request->validated('action'));
    if ($request->expectsJson()) {
      return response()->json(JobProgress::payload($jobRun->refresh()))->header('Cache-Control', 'private, no-store');
    }

    return redirect()->route('admin.jobs.index')->with('job_manual_run', $request->validated('action') === 'resume' ? $jobRun->id : null);
  }

  public function automation(JobRequest $request, string $operation, JobAutomation $automation)
  {
    abort_unless(JobSchema::ready() && $request->boolean('confirmed'), 422);
    if ($operation === 'rotate') {
      // One response only: never persist the plaintext token in session flash data.
      return response()->view('webblocks-cms::admin.system.job-token', ['token' => $automation->rotate()])->header('Cache-Control', 'private, no-store');
    }
    if ($operation === 'schedule') {
      $definition = app(JobRegistry::class)->get((string) $request->validated('job_key'));
      abort_unless($definition && $definition->key !== 'probe' && $request->has('automatic'), 422);
      DB::table('wbcms_job_settings')->updateOrInsert(['job_key' => $definition->key], ['automatic' => $request->boolean('automatic')]);

      return redirect()->route('admin.jobs.index', ['tab' => 'jobs']);
    }
    if ($operation === 'disable') {
      $automation->disable();
      $message = __('webblocks-cms::jobs.disabled');
    } else {
      abort(404);
    }

    return redirect()->route('admin.jobs.index', ['tab' => 'automation'])->with('status', $message);
  }
}
