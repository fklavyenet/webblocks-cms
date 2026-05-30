<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use RuntimeException;
use WebBlocks\Cms\Support\System\Updates\Publishing\UpdatePublisher;

final class PublishUpdateCommand extends Command
{
  protected $signature = 'webblocks:publish-update
    {--release-version= : Release version to publish, for example 1.32.90}
    {--channel= : Update channel, defaults to publisher payload or stable}
    {--artifact= : Path to the prepared WebBlocks CMS package zip}
    {--payload= : Path to the update-server publisher payload JSON}
    {--dry-run : Validate artifact, checksum, metadata, endpoint, and token configuration without publishing}';

  protected $description = 'Publish a prepared WebBlocks CMS package artifact and metadata to the update server.';

  public function handle(UpdatePublisher $publisher): int
  {
    try {
      $result = $publisher->publish([
        'version' => $this->option('release-version'),
        'channel' => $this->option('channel'),
        'artifact' => $this->option('artifact'),
        'payload' => $this->option('payload'),
        'dry_run' => (bool) $this->option('dry-run'),
      ]);
    } catch (RuntimeException $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $this->info('WebBlocks CMS update publisher');
    $this->table(
      ['Field', 'Value'],
      [
        ['Product', $result->product],
        ['Channel', $result->channel],
        ['Version', $result->version],
        ['Artifact', $result->artifactPath],
        ['Payload', $result->payloadPath],
        ['SHA-256', $result->checksumSha256],
        ['Token configured', $result->tokenConfigured ? 'yes' : 'no'],
        ['Published', $result->published ? 'yes' : 'no'],
        ['Latest verified', $result->verified ? 'yes' : 'no'],
      ]
    );

    if ($result->status === 'skipped') {
      $this->warn($result->message);

      return self::FAILURE;
    }

    if ($result->status === 'dry_run') {
      $this->line($result->message);
      $this->line('Dry-run did not upload or publish anything.');

      return self::SUCCESS;
    }

    $this->info($result->message);

    return self::SUCCESS;
  }
}
