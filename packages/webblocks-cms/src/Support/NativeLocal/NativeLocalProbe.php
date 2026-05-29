<?php

namespace WebBlocks\Cms\Support\NativeLocal;

interface NativeLocalProbe
{
  public function phpVersion(): string;

  /**
   * @return array<int, string>
   */
  public function loadedExtensions(): array;

  public function binaryPath(string $binary): ?string;

  public function databaseAccessible(): bool;

  public function databaseFailureMessage(): ?string;

  public function redisAccessible(string $host, int $port): bool;

  public function hostsFileContains(string $host): bool;

  public function fileExists(string $path): bool;

  public function isWritable(string $path): bool;
}
