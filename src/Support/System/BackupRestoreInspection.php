<?php

namespace WebBlocks\Cms\Support\System;

class BackupRestoreInspection
{
  public function __construct(
    public readonly array $manifest,
    public readonly bool $includesDatabase,
    public readonly bool $includesUploads,
    public readonly string $databaseSqlPath,
    public readonly ?string $uploadsRootPath,
    public readonly bool $includesSitePublic = false,
    public readonly ?string $sitePublicRootPath = null,
  ) {}

  public function restoredParts(): array
  {
    return array_values(array_filter([
      $this->includesDatabase ? 'database' : null,
      $this->includesUploads ? 'uploads' : null,
      $this->includesSitePublic ? 'site_public' : null,
    ]));
  }
}
