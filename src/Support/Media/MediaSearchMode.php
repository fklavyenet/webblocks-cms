<?php

namespace WebBlocks\Cms\Support\Media;

enum MediaSearchMode: string
{
  case Literal = 'literal';
  case Pattern = 'pattern';

  public static function fromInput(mixed $value): ?self
  {
    return is_string($value) ? self::tryFrom($value) : null;
  }
}
