<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\Block;

class ContactFormCheck
{
  public function fieldName(Block|int $block): string
  {
    $blockId = $block instanceof Block ? $block->id : $block;
    $token = substr(hash_hmac('sha256', 'form-check-name:'.((int) $blockId), $this->key()), 0, 32);

    return 'form_check_'.$token;
  }

  public function signedFieldName(Block|int $block): string
  {
    $fieldName = $this->fieldName($block);

    return $fieldName.'|'.$this->sign($fieldName);
  }

  public function isFilled(array $input, Block|int $block): bool
  {
    $signedFieldName = trim((string) ($input['_form_check_name'] ?? ''));

    if ($signedFieldName === '') {
      return false;
    }

    $fieldName = $this->verifiedFieldName($signedFieldName, $block);

    if ($fieldName === null) {
      return true;
    }

    return trim((string) ($input[$fieldName] ?? '')) !== '';
  }

  private function verifiedFieldName(string $signedFieldName, Block|int $block): ?string
  {
    [$fieldName, $signature] = array_pad(explode('|', $signedFieldName, 2), 2, '');

    if ($fieldName !== $this->fieldName($block)) {
      return null;
    }

    if (! preg_match('/\Aform_check_[a-f0-9]{32}\z/', $fieldName)) {
      return null;
    }

    return hash_equals($this->sign($fieldName), $signature) ? $fieldName : null;
  }

  private function sign(string $fieldName): string
  {
    return hash_hmac('sha256', $fieldName, $this->key());
  }

  private function key(): string
  {
    return (string) config('app.key');
  }
}
