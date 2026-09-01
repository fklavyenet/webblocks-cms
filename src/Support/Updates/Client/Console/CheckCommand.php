<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;

final class CheckCommand extends Command
{
    protected $signature = 'publisher:check';

    protected $description = 'Check the Publisher for the latest release of this product.';

    public function handle(UpdateServerClient $client): int
    {
        $result = $client->check();

        $this->table(['Field', 'Value'], [
            ['State', $result->state],
            ['Label', $result->label],
            ['Installed', $result->installedVersion],
            ['Latest', $result->latestVersion ?? '—'],
            ['Update available', $result->updateAvailable ? 'yes' : 'no'],
            ['Compatibility', $result->compatibility['status'] ?? 'unknown'],
        ]);

        $this->line($result->message);

        return self::SUCCESS;
    }
}
