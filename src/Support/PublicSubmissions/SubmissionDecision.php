<?php

namespace WebBlocks\Cms\Support\PublicSubmissions;

final class SubmissionDecision
{
  public const ALLOW = 'allow';

  public const QUARANTINE = 'quarantine';

  public const SPAM = 'spam';

  public static function forScore(int $score): string
  {
    return match (true) {
      $score >= (int) config('public-submissions.spam_score', 60) => self::SPAM,
      $score >= (int) config('public-submissions.quarantine_score', 20) => self::QUARANTINE,
      default => self::ALLOW,
    };
  }
}
