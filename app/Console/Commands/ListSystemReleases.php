<?php

namespace App\Console\Commands;

use App\Models\SystemRelease;
use Illuminate\Console\Command;

class ListSystemReleases extends Command
{
    protected $signature = 'system-release:list {--product= : Filter by product} {--channel= : Filter by channel}';

    protected $description = 'List published and draft system releases';

    public function handle(): int
    {
        $query = SystemRelease::query()->orderBy('product')->orderBy('channel')->orderByDesc('version_normalized');

        if (is_string($this->option('product')) && $this->option('product') !== '') {
            $query->where('product', $this->option('product'));
        }

        if (is_string($this->option('channel')) && $this->option('channel') !== '') {
            $query->where('channel', $this->option('channel'));
        }

        $releases = $query->get(['product', 'channel', 'version', 'published_at', 'is_critical', 'is_security']);

        if ($releases->isEmpty()) {
            $this->warn('No releases found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Product', 'Channel', 'Version', 'Published At', 'Critical', 'Security'],
            $releases->map(fn (SystemRelease $release): array => [
                $release->product,
                $release->channel,
                $release->version,
                $release->published_at?->toIso8601String() ?? 'draft',
                $release->is_critical ? 'yes' : 'no',
                $release->is_security ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
