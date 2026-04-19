<?php

namespace App\Console\Commands;

use App\Support\Updates\PublishReleasePayloadBuilder;
use App\Support\Updates\UpdatesServerPublisher;
use App\Support\Updates\UpdatesServerPublishException;
use Illuminate\Console\Command;

class UpdatesPublishCommand extends Command
{
    protected $signature = 'updates:publish
        {version? : Release version}
        {--notes= : Release notes}
        {--channel= : Release channel}
        {--source-url= : Source URL for the release}
        {--tag= : Git tag reference}
        {--dry-run : Preview the payload without making a request}';

    protected $description = 'Publish release metadata from WebBlocks CMS to the central Updates Server';

    public function handle(UpdatesServerPublisher $publisher, PublishReleasePayloadBuilder $payloadBuilder): int
    {
        $version = $payloadBuilder->resolveVersion($this->argument('version'));

        try {
            $result = $publisher->publish([
                'version' => $version,
                'channel' => $this->option('channel') ?: config('webblocks-updates.publish.channel', 'stable'),
                'notes' => $this->option('notes'),
                'source_url' => $this->option('source-url'),
                'tag' => $this->option('tag'),
            ], (bool) $this->option('dry-run'));

            $this->table(['Field', 'Value'], [
                ['Endpoint', $result['endpoint']],
                ['Product', config('webblocks-updates.publish.product', 'webblocks-cms')],
                ['Version', $version ?? ''],
                ['Channel', (string) ($this->option('channel') ?: config('webblocks-updates.publish.channel', 'stable'))],
                ['Result', ($this->option('dry-run') ? 'dry-run' : 'success')],
            ]);

            if ($this->option('dry-run')) {
                $this->line(json_encode($result['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (UpdatesServerPublishException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
