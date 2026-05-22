<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Console\BlockTypeContractsAuditCommand;
use WebBlocks\Cms\Console\ImportDemoMedia;
use WebBlocks\Cms\Console\ResetPrimitiveBlocksCommand;
use WebBlocks\Cms\Console\SiteCloneCommand;
use WebBlocks\Cms\Console\SiteDeleteCommand;
use WebBlocks\Cms\Console\SyncCoreBlockTypesCommand;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageWrapperCleanupTest extends TestCase
{
    #[Test]
    public function package_owned_commands_and_translation_models_no_longer_require_deleted_root_wrappers(): void
    {
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/BlockTypeContractsAuditCommand.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/ImportDemoMedia.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/ResetPrimitiveBlocksCommand.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/SiteCloneCommand.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/SiteDeleteCommand.php'));
        $this->assertFileDoesNotExist(base_path('app/Console/Commands/SyncCoreBlockTypesCommand.php'));

        $this->assertFileDoesNotExist(base_path('app/Models/BlockButtonTranslation.php'));
        $this->assertFileDoesNotExist(base_path('app/Models/BlockContactFormTranslation.php'));
        $this->assertFileDoesNotExist(base_path('app/Models/BlockGalleryItemTranslation.php'));
        $this->assertFileDoesNotExist(base_path('app/Models/BlockImageTranslation.php'));

        $this->assertContains(BlockTypeContractsAuditCommand::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertContains(ImportDemoMedia::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertContains(ResetPrimitiveBlocksCommand::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertContains(SiteCloneCommand::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertContains(SiteDeleteCommand::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);
        $this->assertContains(SyncCoreBlockTypesCommand::class, WebBlocksCmsServiceProvider::PACKAGE_CONSOLE_COMMANDS);

        $this->assertTrue(class_exists(BlockButtonTranslation::class));
        $this->assertTrue(class_exists(BlockContactFormTranslation::class));
        $this->assertTrue(class_exists(BlockGalleryItemTranslation::class));
        $this->assertTrue(class_exists(BlockImageTranslation::class));
    }
}
