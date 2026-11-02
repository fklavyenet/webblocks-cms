<?php

declare(strict_types=1);

return static function (string $version, ?string $requested = null): string {
  // The bridge itself must remain reachable by archive-based updaters.
  $floor = version_compare($version, '1.112.2', '>') ? '1.112.2' : '1.112.1';
  $minimum = $requested ?: $floor;
  if (preg_match('/^\d+\.\d+\.\d+$/D', $minimum) !== 1
    || version_compare($minimum, $floor, '<') || version_compare($minimum, $version, '>')) {
    throw new RuntimeException('Minimum updater version must preserve the applicable bridge floor and not exceed the release.');
  }

  return $minimum;
};
