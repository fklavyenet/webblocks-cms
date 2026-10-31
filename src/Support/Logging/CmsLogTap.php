<?php

namespace WebBlocks\Cms\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;

final class CmsLogTap
{
  public function __invoke(Logger $logger): void
  {
    foreach ($logger->getHandlers() as $handler) {
      if ($handler instanceof FormattableHandlerInterface && ! $handler->getFormatter() instanceof SelectableLogFormatter) {
        $handler->setFormatter(new SelectableLogFormatter($handler->getFormatter()));
      }
    }
  }
}
