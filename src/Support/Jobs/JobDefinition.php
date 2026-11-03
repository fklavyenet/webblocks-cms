<?php

namespace WebBlocks\Cms\Support\Jobs;

use Closure;
use Cron\CronExpression;

/** Code-owned task registration. No command or arguments are accepted from HTTP. */
final class JobDefinition
{
  public function __construct(
    public readonly string $key,
    public readonly string $label,
    public readonly Closure $step,
    public readonly ?string $cron = null,
    public readonly bool $web = false,
    public readonly bool $effects = false,
    public readonly ?string $owner = null,
    public readonly ?Closure $scopes = null,
    public readonly bool $automatic = true,
    public readonly ?string $destination = null,
  ) {
    if (! preg_match('/^[a-z0-9][a-z0-9.:-]{0,119}$/D', $key)) {
      throw new \InvalidArgumentException('Invalid job key.');
    }
    if ($cron !== null && ! CronExpression::isValidExpression($cron)) {
      throw new \InvalidArgumentException('Invalid job schedule.');
    }
  }
}
