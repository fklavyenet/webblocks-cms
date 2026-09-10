<?php

namespace WebBlocks\Cms\Support\PublicSubmissions;

use Illuminate\Support\Facades\Cache;

final class PublicSubmissionProtection
{
  private const COMMERCIAL_PATTERNS = [
    'backlink', 'casino', 'content marketing', 'crypto', 'digital marketing',
    'guest post', 'increase your traffic', 'lead generation', 'link building',
    'marketing agency', 'rank higher', 'sales outreach', 'sponsored content',
    'virtual assistant', 'we can help you grow', 'we noticed your website',
  ];

  /**
   * @param  array<string, mixed>  $answers
   * @return array{score: int, reasons: list<string>, decision: string, is_spam: bool}
   */
  public function inspect(int $siteId, string $surface, string|int $form, array $answers, ?string $ip, ?int $elapsedSeconds): array
  {
    $text = $this->normalize($answers);
    $score = 0;
    $reasons = [];
    $links = preg_match_all('/https?:\/\/|www\.|[a-z0-9.-]+\.[a-z]{2,}(?:\/|\b)/i', $text);

    if ($links >= 3) {
      $score += 40;
      $reasons[] = 'high_link_density';
    } elseif ($links >= 2) {
      $score += 25;
      $reasons[] = 'multiple_links';
    }

    if ($surface === 'comment' && $links > 0) {
      $score += 50;
      $reasons[] = 'links_not_allowed';
    }

    if ($surface === 'comment' && preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $text) === 1) {
      $score += 40;
      $reasons[] = 'contact_details';
    }

    if ($surface === 'comment' && preg_match('/(?:\+?\d[\s().-]?){7,}/', $text) === 1) {
      $score += 40;
      $reasons[] = 'contact_details';
    }

    if (preg_match('/\[url[=\]]|<a\s+href/i', $text) === 1) {
      $score += 60;
      $reasons[] = 'markup_links';
    }

    if ($this->containsAny($text, self::COMMERCIAL_PATTERNS)) {
      $score += 40;
      $reasons[] = 'commercial_language';
    }

    if (preg_match('/\S{100,}/u', $text) === 1) {
      $score += 20;
      $reasons[] = 'unbroken_run';
    }

    if ($elapsedSeconds !== null && $elapsedSeconds < 8) {
      $score += 10;
      $reasons[] = 'fast_submit';
    }

    $scope = 'wbcms:submission:'.$siteId.':';
    $fingerprintText = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '', $text) ?? $text;
    $fingerprintText = trim(preg_replace('/\s+/u', ' ', $fingerprintText) ?? '');
    $contentCount = $fingerprintText === '' ? 0 : $this->increment(
      $scope.'content:'.$this->hash($fingerprintText),
      (int) config('public-submissions.fingerprint_window_seconds', 86400),
    );

    if ($fingerprintText !== '' && $contentCount >= 2) {
      $score += $contentCount >= 4 ? 60 : 30;
      $reasons[] = 'repeated_content';
    }

    if ($ip) {
      $sourceCount = $this->increment($scope.'source:'.$this->hash($ip), (int) config('public-submissions.source_window_seconds', 3600));

      if ($sourceCount >= 4) {
        $score += $sourceCount >= 8 ? 40 : 20;
        $reasons[] = 'source_burst';
      }
    }

    $formCount = $this->increment($scope.'form:'.$surface.':'.$this->hash((string) $form), 60);

    if ($formCount >= 8) {
      $score += 20;
      $reasons[] = 'form_burst';
    }

    $score = min(100, $score);
    $decision = SubmissionDecision::forScore($score);

    return [
      'score' => $score,
      'reasons' => array_values(array_unique($reasons)),
      'decision' => $decision,
      'is_spam' => $decision === SubmissionDecision::SPAM,
    ];
  }

  private function normalize(array $answers): string
  {
    $parts = [];
    array_walk_recursive($answers, static function ($value) use (&$parts): void {
      if (is_scalar($value)) {
        $parts[] = (string) $value;
      }
    });

    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? ''));
  }

  private function containsAny(string $text, array $patterns): bool
  {
    foreach ($patterns as $pattern) {
      if (str_contains($text, $pattern)) {
        return true;
      }
    }

    return false;
  }

  private function increment(string $key, int $seconds): int
  {
    Cache::add($key, 0, now()->addSeconds($seconds));

    return (int) Cache::increment($key);
  }

  private function hash(string $value): string
  {
    return substr(hash_hmac('sha256', $value, (string) config('app.key')), 0, 32);
  }
}
