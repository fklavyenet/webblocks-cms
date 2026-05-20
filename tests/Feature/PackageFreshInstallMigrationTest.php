<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageFreshInstallMigrationTest extends TestCase
{
    #[Test]
    public function shared_slot_revision_restored_from_foreign_key_uses_an_explicit_short_name(): void
    {
        $migration = (string) file_get_contents(base_path('packages/webblocks-cms/database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php'));

        $this->assertStringContainsString("foreign('restored_from_shared_slot_revision_id', 'ss_revisions_restored_from_fk')", $migration);
        $this->assertStringNotContainsString('shared_slot_revisions_restored_from_shared_slot_revision_id_foreign', $migration);
    }
}
