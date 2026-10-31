<?php

namespace WebBlocks\Cms\Support\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

final class SelectableLogFormatter implements FormatterInterface
{
  public function __construct(private readonly FormatterInterface $original) {}

  private function formatter(): FormatterInterface
  {
    return match (app(LogFormatSettings::class)->mode()) {
      'compact' => new ExceptionLogFormatter,
      'full' => new ExceptionLogFormatter(null),
      default => $this->original,
    };
  }

  public function format(LogRecord $record): mixed
  {
    return $this->formatter()->format($record);
  }

  public function formatBatch(array $records): mixed
  {
    return $this->formatter()->formatBatch($records);
  }
}
