<?php

use Symfony\Component\Process\Process;

// Composer's default subprocess timeout is shorter than the complete test gate.
// Disable only that outer limit in this script's alias, then retain a finite
// bound around the unchanged single-hook push and remote verification flow.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$process = new Process(
  ['bash', __DIR__.'/push.sh'], dirname(__DIR__, 2), timeout: 900,
);
exit($process->run(static function (string $type, string $buffer): void {
  fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
}));
