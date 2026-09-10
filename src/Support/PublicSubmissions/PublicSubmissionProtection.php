<?php

namespace WebBlocks\Cms\Support\PublicSubmissions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PublicSubmissionProtection
{
  public function __construct(private readonly SubmissionFingerprint $fingerprint) {}

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
    $storedSignal = $this->storedFingerprintSignal($siteId, $fingerprintText);

    if ($storedSignal > 0) {
      $score += $storedSignal;
      $reasons[] = $storedSignal >= 50 ? 'known_spam_fingerprint' : 'similar_spam_fingerprint';
    } elseif ($storedSignal < 0) {
      $score = max(0, $score + $storedSignal);
      $reasons[] = 'known_legitimate_fingerprint';
    }
    $contentCount = $fingerprintText === '' ? 0 : $this->increment(
      $scope.'content:'.$this->hash($fingerprintText),
      (int) config('public-submissions.fingerprint_window_seconds', 86400),
    );

    if ($fingerprintText !== '' && $contentCount >= 2 && $storedSignal >= 0) {
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

  /** @param array<string, mixed> $answers */
  public function recordOutcome(int $siteId, array $answers, string $outcome): void
  {
    if (! in_array($outcome, ['spam', 'ham'], true) || ! Schema::hasTable('wbcms_submission_fingerprints')) {
      return;
    }

    $text = $this->fingerprintText($this->normalize($answers));

    if ($text === '') {
      return;
    }

    $exact = $this->fingerprint->exact($text);
    $now = now();
    DB::table('wbcms_submission_fingerprints')->upsert([[
      'site_id' => $siteId,
      'exact_hash' => $exact,
      'simhash' => $this->fingerprint->similar($text),
      'occurrences' => 0,
      'spam_count' => 0,
      'ham_count' => 0,
      'last_seen_at' => $now,
      'created_at' => $now,
      'updated_at' => $now,
    ]], ['site_id', 'exact_hash'], ['simhash', 'last_seen_at', 'updated_at']);

    DB::table('wbcms_submission_fingerprints')
      ->where('site_id', $siteId)
      ->where('exact_hash', $exact)
      ->increment($outcome.'_count', 1, ['last_seen_at' => $now, 'updated_at' => $now]);
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

  private function fingerprintText(string $text): string
  {
    $text = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '', $text) ?? $text;

    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
  }

  private function storedFingerprintSignal(int $siteId, string $text): int
  {
    if ($text === '' || ! Schema::hasTable('wbcms_submission_fingerprints')) {
      return 0;
    }

    $exact = $this->fingerprint->exact($text);
    $similar = $this->fingerprint->similar($text);
    $rows = DB::table('wbcms_submission_fingerprints')
      ->where('site_id', $siteId)
      ->where('last_seen_at', '>=', now()->subDays(90))
      ->orderByDesc('last_seen_at')
      ->limit(200)
      ->get();
    $signal = 0;
    $nearReputation = 0;

    foreach ($rows as $row) {
      $reputation = ((int) $row->spam_count) - ((int) $row->ham_count);

      if ($reputation === 0) {
        continue;
      }

      if ($row->exact_hash === $exact) {
        $signal = $reputation > 0 ? 60 : -40;
        break;
      }

      if ($this->fingerprint->distance($similar, (string) $row->simhash) <= (int) config('public-submissions.similarity_distance', 10)) {
        $nearReputation += $reputation;
      }
    }

    if ($signal === 0) {
      $signal = match (true) {
        $nearReputation > 0 => 35,
        $nearReputation < 0 => -20,
        default => 0,
      };
    }

    $now = now();
    DB::table('wbcms_submission_fingerprints')->upsert([[
      'site_id' => $siteId,
      'exact_hash' => $exact,
      'simhash' => $similar,
      'occurrences' => 0,
      'spam_count' => 0,
      'ham_count' => 0,
      'last_seen_at' => $now,
      'created_at' => $now,
      'updated_at' => $now,
    ]], ['site_id', 'exact_hash'], ['simhash', 'last_seen_at', 'updated_at']);

    DB::table('wbcms_submission_fingerprints')
      ->where('site_id', $siteId)
      ->where('exact_hash', $exact)
      ->increment('occurrences', 1, ['last_seen_at' => $now, 'updated_at' => $now]);

    return $signal;
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
