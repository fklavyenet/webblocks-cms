<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Chains;

use WebBlocks\Cms\Support\Updates\Client\Contracts\ChainStepExecutor;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/** Product adapters own durable receipts, a chain lease and per-step recovery. */
class ChainCoordinator
{
    public function run(UpdatePlan $plan, ChainStepExecutor $executor): string
    {
        $current = $plan->installedVersion;
        foreach ($plan->steps as $index => $step) {
            $verified = $executor->execute($index + 1, $step);
            if ($verified !== $step['version'] || version_compare(ltrim($verified, 'v'), ltrim($current, 'v'), '<=')) {
                throw new UpdateException('The update step did not verify its installed version.');
            }
            $current = $verified;
        }
        if ($current !== $plan->targetVersion) {
            throw new UpdateException('The update chain did not reach its approved target.');
        }
        return $current;
    }
}
