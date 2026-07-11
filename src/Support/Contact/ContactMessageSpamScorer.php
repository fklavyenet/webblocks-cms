<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\ContactMessage;

class ContactMessageSpamScorer
{
  private const FREE_MAIL_DOMAINS = [
    'gmail.com',
    'hotmail.com',
    'icloud.com',
    'outlook.com',
    'proton.me',
    'protonmail.com',
    'yahoo.com',
  ];

  private const COMMERCIAL_PATTERNS = [
    'backlink',
    'content marketing',
    'digital marketing',
    'guest post',
    'increase your traffic',
    'lead generation',
    'link building',
    'marketing agency',
    'rank higher',
    'sales outreach',
    'sponsored content',
    'sponsored post',
    'we can help you grow',
    'we noticed your website',
  ];

  private const GENERIC_SUBJECTS = [
    'business proposal',
    'collaboration',
    'partnership',
    'partnership request',
    'quick question',
  ];

  public function score(array $payload, ?string $ipAddress): array
  {
    $score = 0;
    $reasons = [];
    $text = $this->normalizedText([
      $payload['name'] ?? '',
      $payload['email'] ?? '',
      $payload['subject'] ?? '',
      $payload['message'] ?? '',
    ]);
    $message = $this->normalizedText([$payload['message'] ?? '']);
    $subject = $this->normalizedText([$payload['subject'] ?? '']);

    $commercialMatches = collect(self::COMMERCIAL_PATTERNS)
      ->filter(fn (string $pattern): bool => str_contains($text, $pattern))
      ->values();

    if ($commercialMatches->isNotEmpty()) {
      $score += 40;
      $reasons[] = 'Commercial outreach language';
    }

    $linkCount = preg_match_all('/https?:\/\/|www\.|[a-z0-9.-]+\.[a-z]{2,}(?:\/|\b)/i', $message);

    if ($linkCount >= 3) {
      $score += 40;
      $reasons[] = 'High link density';
    } elseif ($linkCount >= 2) {
      $score += 25;
      $reasons[] = 'Multiple links';
    }

    if ($this->usesGenericFreeMailSalesCombination($payload, $subject, $message)) {
      $score += 30;
      $reasons[] = 'Free-mail sales pitch with generic subject';
    }

    if ($ipAddress && ContactMessage::query()
      ->where('ip_address', $ipAddress)
      ->where('created_at', '>=', now()->subDay())
      ->count() >= 2) {
      $score += 20;
      $reasons[] = 'Repeated submissions from the same IP';
    }

    return [
      'score' => min($score, 100),
      'reasons' => array_values(array_unique($reasons)),
      'is_spam' => $score >= 60,
    ];
  }

  private function usesGenericFreeMailSalesCombination(array $payload, string $subject, string $message): bool
  {
    $domain = strtolower((string) str($payload['email'] ?? '')->after('@'));

    if (! in_array($domain, self::FREE_MAIL_DOMAINS, true)) {
      return false;
    }

    if (! in_array($subject, self::GENERIC_SUBJECTS, true)) {
      return false;
    }

    return str_contains($message, 'service')
      || str_contains($message, 'offer')
      || str_contains($message, 'sales')
      || str_contains($message, 'marketing')
      || str_contains($message, 'traffic')
      || str_contains($message, 'leads');
  }

  private function normalizedText(array $values): string
  {
    return strtolower(implode(' ', array_map(fn ($value): string => (string) $value, $values)));
  }
}
