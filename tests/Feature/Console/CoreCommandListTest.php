<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoreCommandListTest extends TestCase
{
    #[Test]
    public function core_command_list_does_not_expose_removed_ui_docs_generators(): void
    {
        $removedSyncCommand = 'webblocks:'.implode('-', ['sync', 'ui', 'docs', 'pilot']);
        $removedRebuildCommand = 'webblocks:'.implode('-', ['rebuild', 'ui', 'docs']);

        $this->artisan('list')
            ->doesntExpectOutputToContain($removedSyncCommand)
            ->doesntExpectOutputToContain($removedRebuildCommand)
            ->assertExitCode(0);
    }
}
