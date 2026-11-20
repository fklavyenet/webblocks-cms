<?php

namespace WebBlocks\Cms\Support\Translations;

use PhpToken;
use UnexpectedValueException;

/** Reads the single array literal emitted by the translation package builder. */
final class LanguageArrayLiteral
{
  /** @var list<PhpToken> */
  private array $tokens;

  private int $position = 0;

  public function read(string $contents): array
  {
    $this->tokens = array_values(array_filter(PhpToken::tokenize($contents), fn (PhpToken $token) => $token->id !== T_WHITESPACE));
    $this->position = 0;
    $this->take(T_OPEN_TAG);
    $this->take(T_RETURN);
    $lines = $this->readArray(0);
    $this->take(';');

    if (isset($this->tokens[$this->position])) {
      throw new UnexpectedValueException('Unexpected translation file content.');
    }

    return $lines;
  }

  private function readArray(int $depth): array
  {
    if ($depth >= 12) {
      throw new UnexpectedValueException('Translation array is too deeply nested.');
    }

    $this->take(T_ARRAY);
    $this->take('(');
    $lines = [];
    while (! ($this->tokens[$this->position] ?? null)?->is(')')) {
      $keyToken = $this->tokens[$this->position] ?? null;
      $key = $keyToken?->is(T_LNUMBER)
        ? $this->take(T_LNUMBER)->text
        : $this->readString();
      if (array_key_exists($key, $lines)) {
        throw new UnexpectedValueException('Duplicate translation key.');
      }

      $this->take(T_DOUBLE_ARROW);
      $lines[$key] = ($this->tokens[$this->position] ?? null)?->is(T_ARRAY)
        ? $this->readArray($depth + 1)
        : $this->readString();
      $this->take(',');
    }
    $this->take(')');

    return $lines;
  }

  private function readString(): string
  {
    $literal = $this->take(T_CONSTANT_ENCAPSED_STRING)->text;
    if ($literal[0] !== "'") {
      throw new UnexpectedValueException('Unexpected translation string encoding.');
    }

    return strtr(substr($literal, 1, -1), ['\\\\' => '\\', "\\'" => "'"]);
  }

  private function take(int|string $expected): PhpToken
  {
    $token = $this->tokens[$this->position++] ?? null;
    if (! $token?->is($expected)) {
      throw new UnexpectedValueException('Unexpected translation array token.');
    }

    return $token;
  }
}
