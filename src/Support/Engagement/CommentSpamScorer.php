<?php

namespace WebBlocks\Cms\Support\Engagement;

use WebBlocks\Cms\Models\CommentEntry;

class CommentSpamScorer
{
  public function score(array $payload, ?string $ipHash): array
  {
    $score = 0;
    $reasons = [];
    $body = trim((string) ($payload['body'] ?? ''));
    $text = mb_strtolower($body.' '.($payload['author_name'] ?? ''));

    $linkCount = preg_match_all('/https?:\/\/|www\.|[a-z0-9.-]+\.[a-z]{2,}(?:\/|\b)/i', $body);

    if ($linkCount > 0) {
      $score += 50;
      $reasons[] = 'Links are not allowed in comments';
    }

    if (preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $body) === 1) {
      $score += 40;
      $reasons[] = 'Possible email address';
    }

    if (preg_match('/(?:\+?\d[\s().-]?){7,}/', $body) === 1) {
      $score += 40;
      $reasons[] = 'Possible phone number';
    }

    if (mb_strlen($body) > 1200) {
      $score += 20;
      $reasons[] = 'Very long comment';
    }

    foreach (['address', 'whatsapp', 'telegram', 'call me', 'follow me'] as $pattern) {
      if (str_contains($text, $pattern)) {
        $score += 20;
        $reasons[] = 'Personal contact language';
        break;
      }
    }

    if ($ipHash && CommentEntry::query()
      ->where('ip_hash', $ipHash)
      ->where('created_at', '>=', now()->subHour())
      ->count() >= 3) {
      $score += 25;
      $reasons[] = 'Repeated comments from the same source';
    }

    return [
      'score' => min($score, 100),
      'reasons' => array_values(array_unique($reasons)),
      'is_spam' => $score >= 60,
    ];
  }
}
