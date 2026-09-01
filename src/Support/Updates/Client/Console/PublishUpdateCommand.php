<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use RuntimeException;
use WebBlocks\Cms\Support\Updates\Client\Publishing\UpdatePublisher;

final class PublishUpdateCommand extends Command
{
    protected $signature = 'publisher:publish-update
        {--release-version= : Explicit release version}
        {--artifact= : Path to the package artifact ZIP}
        {--payload= : Path to the prepared publisher payload JSON}
        {--dry-run : Validate everything without publishing}';

    protected $description = 'Sign and publish a prepared release artifact to the Publisher (owner-side).';

    public function handle(UpdatePublisher $publisher): int
    {
        try {
            $result = $publisher->publish(array_filter([
                'version' => $this->option('release-version'),
                'artifact' => $this->option('artifact'),
                'payload' => $this->option('payload'),
                'dry_run' => (bool) $this->option('dry-run'),
            ], fn ($value): bool => $value !== null && $value !== false));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['Status', $result->status],
            ['Product', $result->product],
            ['Channel', $result->channel],
            ['Version', $result->version],
            ['Checksum', $result->checksumSha256],
            ['Token configured', $result->tokenConfigured ? 'yes' : 'no'],
            ['Published', $result->published ? 'yes' : 'no'],
            ['Latest verified', $result->verified ? 'yes' : 'no'],
        ]);

        $this->line($result->message);

        return $result->status === 'skipped' ? self::FAILURE : self::SUCCESS;
    }
}
