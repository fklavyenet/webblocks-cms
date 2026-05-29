<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalDoctor;
use WebBlocks\Cms\Support\NativeLocal\NativeLocalProbe;

class SmokeNativeLocalCommand extends Command
{
  protected $signature = 'webblocks:smoke-native-local';

  protected $description = 'Run a read-only native HTTPS .test local smoke check';

  public function handle(NativeLocalDoctor $doctor, NativeLocalProbe $probe): int
  {
    $this->line('WebBlocks CMS native local smoke');
    $this->line('Mode: read-only checks only; no installs, service starts, file writes, or hosts changes.');
    $this->newLine();

    $checks = $doctor->checks();
    $summary = $doctor->summary($checks);

    $this->line(sprintf(
      'Doctor: %d passed, %d warnings, %d failed.',
      $summary['passed'],
      $summary['warnings'],
      $summary['failed']
    ));

    $appUrl = (string) config('app.url', env('APP_URL', ''));
    $this->line('APP_URL: '.($appUrl !== '' ? $appUrl : '<missing>'));

    $statusCode = $appUrl !== '' ? $probe->httpsStatusCode($appUrl) : null;

    if (in_array($statusCode, [200, 302], true)) {
      $this->line('[PASS] Local HTTPS curl smoke: '.$statusCode.' from APP_URL.');
    } else {
      $this->line('[FAIL] Local HTTPS curl smoke: expected 200 or 302 from APP_URL.');
      $this->line('      Confirm native Nginx owns ports 80/443 and APP_URL points at the HTTPS .test host.');
    }

    if ($summary['failed'] > 0 || ! in_array($statusCode, [200, 302], true)) {
      return self::FAILURE;
    }

    return self::SUCCESS;
  }
}
