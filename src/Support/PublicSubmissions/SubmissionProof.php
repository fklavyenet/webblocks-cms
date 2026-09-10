<?php

namespace WebBlocks\Cms\Support\PublicSubmissions;

final class SubmissionProof
{
  public function fieldName(string $surface, string|int $form): string
  {
    return 'form_check_'.substr(hash_hmac('sha256', $this->subject($surface, $form), $this->key()), 0, 32);
  }

  public function signedFieldName(string $surface, string|int $form): string
  {
    $field = $this->fieldName($surface, $form);

    return $field.'|'.$this->sign('field:'.$field);
  }

  public function issueStamp(string $surface, string|int $form): string
  {
    $timestamp = (string) now()->timestamp;
    $subject = $this->subject($surface, $form);

    return $timestamp.'|'.$this->sign('stamp:'.$subject.':'.$timestamp);
  }

  public function elapsedSeconds(?string $stamp, string $surface, string|int $form): ?int
  {
    [$timestamp, $signature] = array_pad(explode('|', (string) $stamp, 2), 2, '');

    if (! ctype_digit($timestamp)) {
      return null;
    }

    $expected = $this->sign('stamp:'.$this->subject($surface, $form).':'.$timestamp);

    if (! hash_equals($expected, $signature)) {
      return null;
    }

    $elapsed = now()->timestamp - (int) $timestamp;

    return $elapsed >= 0 ? $elapsed : null;
  }

  public function trapWasTriggered(array $input, string $surface, string|int $form): bool
  {
    [$field, $signature] = array_pad(explode('|', trim((string) ($input['_form_check_name'] ?? '')), 2), 2, '');
    $expectedField = $this->fieldName($surface, $form);

    if ($field !== $expectedField || ! hash_equals($this->sign('field:'.$field), $signature)) {
      return true;
    }

    return trim((string) ($input[$field] ?? '')) !== '';
  }

  private function subject(string $surface, string|int $form): string
  {
    return 'public-submission:'.$surface.':'.((string) $form);
  }

  private function sign(string $value): string
  {
    return hash_hmac('sha256', $value, $this->key());
  }

  private function key(): string
  {
    return (string) config('app.key');
  }
}
