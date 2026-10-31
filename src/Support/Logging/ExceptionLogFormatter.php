<?php

namespace WebBlocks\Cms\Support\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Throwable;

final class ExceptionLogFormatter extends LineFormatter
{
  public function __construct(private readonly ?int $frames = 2)
  {
    parent::__construct(null, 'Y-m-d H:i:s', true, true, false);
  }

  private function cause(Throwable $exception): Throwable
  {
    for ($depth = 0; $depth < 20 && $exception->getPrevious() !== null; $depth++) {
      $exception = $exception->getPrevious();
    }

    return $exception;
  }

  public function format(LogRecord $record): string
  {
    $exception = $record->context['exception'] ?? null;
    if ($this->frames !== null && $exception instanceof Throwable && $record->message === $exception->getMessage()) {
      $record = $record->with(message: $this->cause($exception)->getMessage());
    }

    return parent::format($record);
  }

  protected function normalizeException(Throwable $exception, int $depth = 0): string
  {
    if ($this->frames !== null) {
      return $this->describe($this->cause($exception));
    }
    $text = $this->describe($exception);
    while ($exception->getPrevious() !== null && ++$depth <= $this->maxNormalizeDepth) {
      $exception = $exception->getPrevious();
      $text .= "\n[previous exception] ".$this->describe($exception);
    }

    return $text;
  }

  private function describe(Throwable $exception): string
  {
    $text = sprintf('%s: %s at %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine());
    $trace = $this->frames === null ? $exception->getTrace() : array_slice($exception->getTrace(), 0, $this->frames);
    foreach ($trace as $index => $frame) {
      // Both modes omit arguments; full mode retains the complete call chain.
      $text .= sprintf("\n#%d %s:%d %s%s%s()", $index, $frame['file'] ?? '[internal]', $frame['line'] ?? 0,
        $frame['class'] ?? '', $frame['type'] ?? '', $frame['function'] ?? '[unknown]');
    }

    return $text;
  }
}
