<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\Block;

final class ContactFormFields
{
  public const LABELS = ['name_label', 'email_label', 'subject_label', 'message_label'];

  public const VISIBILITY = ['name' => 'show_name', 'subject' => 'show_subject'];

  public static function visible(?Block $block, string $field): bool
  {
    return ! isset(self::VISIBILITY[$field]) || (bool) ($block?->setting(self::VISIBILITY[$field], true) ?? true);
  }

  public static function validLabel(mixed $value): bool
  {
    return $value === null || (is_string($value) && mb_strlen($value) <= 255);
  }
}
