<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalCheckResult;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalDoctor;

class DoctorNativeLocalCommand extends Command
{
  protected $signature = 'webblocks:doctor-native-local';

  protected $description = 'Check native HTTPS .test local development readiness without mutating the system';

  public function handle(NativeLocalDoctor $doctor): int
  {
    $this->line('WebBlocks CMS native local doctor');
    $this->line('Mode: read-only checks only; no installs, service starts, file writes, or hosts changes.');
    $this->newLine();

    $checks = $doctor->checks();

    foreach ($checks as $check) {
      $this->line(sprintf('[%s] %s: %s', $this->statusLabel($check), $check->label, $check->message));

      if ($check->recommendation !== null) {
        $this->line('      '.$check->recommendation);
      }
    }

    $summary = $doctor->summary($checks);

    $this->newLine();
    $this->line('Summary');
    $this->line('Passed: '.$summary['passed']);
    $this->line('Warnings: '.$summary['warnings']);
    $this->line('Failed: '.$summary['failed']);

    return $doctor->hasCriticalFailures($checks) ? self::FAILURE : self::SUCCESS;
  }

  private function statusLabel(NativeLocalCheckResult $check): string
  {
    return match ($check->status) {
      'pass' => 'PASS',
      'warn' => 'WARN',
      default => 'FAIL',
    };
  }
}
