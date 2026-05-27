<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Console;

use Illuminate\Console\Command;
use Throwable;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePreparer;

class PrepareWebBlocksUiReleaseCommand extends Command
{
  protected $signature = 'webblocks-ui-manager:prepare-release
    {version : WebBlocks UI version, for example v2.7.9}
    {--artifact=* : Local artifact file to checksum and include in the manifest}
    {--write-manifest : Write manifest.json under the local public CDN target path}';

  protected $description = 'Prepare local WebBlocks UI release artifact metadata and checksums without publishing to production CDN';

  public function __construct(
    private readonly WebBlocksUiReleasePreparer $preparer,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $artifacts = array_values(array_filter(
      (array) $this->option('artifact'),
      fn ($path): bool => is_string($path) && trim($path) !== ''
    ));

    if ($artifacts === []) {
      $this->error('At least one --artifact file is required.');

      return self::FAILURE;
    }

    try {
      $result = $this->preparer->prepare(
        version: (string) $this->argument('version'),
        artifactPaths: $artifacts,
        writeManifest: (bool) $this->option('write-manifest')
      );
    } catch (Throwable $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $release = $result['release'];
    $manifest = $result['manifest'];

    $this->line('Release: '.$release->version);
    $this->line('Status: '.$release->status);
    $this->line('Artifacts: '.count($manifest['artifacts']));
    $this->line('Manifest written: '.($result['manifest_written'] ? 'yes' : 'no'));

    foreach ($manifest['artifacts'] as $artifact) {
      $this->line('- '.$artifact['handle'].' '.$artifact['checksum_sha256']);
    }

    return self::SUCCESS;
  }
}
