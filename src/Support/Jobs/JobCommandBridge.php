<?php

namespace WebBlocks\Cms\Support\Jobs;

use Closure;

/** Existing CLI entry points share the same run admission as scheduler adapters. */
final class JobCommandBridge
{
  public static function run(string $key, Closure $work): int
  {
    if (JobRunner::executing($key) || ! JobSchema::ready()) {
      return $work();
    }
    if (! app(JobRegistry::class)->get($key)) {
      return 0; // Disabled plugin commands must not create or dispatch work.
    }
    $runner = app(JobRunner::class);
    $run = $runner->request($key, source: 'cli');
    $run = $runner->advance($run, 'cli', step: fn () => ['state' => $work() === 0 ? 'complete' : 'failed']);

    return $run->state === 'complete' ? 0 : 1;
  }
}
