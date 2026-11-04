<?php

namespace WebBlocks\Cms\Support\System\Updates;

class UpdateWorkerProcess
{
  /** A PID is meaningful only on the same booted host, including shared DB/cache installations. */
  public function host(): ?string
  {
    $boot = @file_get_contents('/proc/sys/kernel/random/boot_id');
    $namespace = @readlink('/proc/self/ns/pid');

    return is_string($boot) && trim($boot) !== '' && is_string($namespace) && $namespace !== ''
      ? hash('sha256', php_uname('n').'|'.trim($boot).'|'.$namespace) : null;
  }

  public function state(?int $pid, ?string $host): string
  {
    $current = $this->host();
    if (! $pid || $pid < 1 || ! $host || ! $current || ! hash_equals($current, $host) || ! function_exists('posix_kill')) {
      return 'unknown';
    }
    if (@posix_kill($pid, 0)) {
      return 'alive';
    }

    // EPERM, unsupported inspection and namespace/host uncertainty never prove death.
    return posix_get_last_error() === 3 ? 'dead' : 'unknown';
  }
}
