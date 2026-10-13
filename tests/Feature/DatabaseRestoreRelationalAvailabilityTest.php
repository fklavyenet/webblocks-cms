<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\SystemBackupRestore;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use WebBlocks\Cms\Tests\TestCase;

class DatabaseRestoreRelationalAvailabilityTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function restoring_legacy_schema_clears_loaded_sql_authority_before_recording_outcome(): void
  {
    $record = SystemBackupRestore::create(['status' => 'completed', 'manifest' => ['before' => true], 'restored_parts' => []]);
    $store = app(PersistentDocuments::class);
    $this->assertTrue($store->retired($record, 'manifest'));
    $this->assertTrue($store->available());
    $sql = "PRAGMA foreign_keys=OFF;\n";
    foreach (['wbcms_persistent_nodes', ...array_map(fn ($name) => 'wbcms_'.$name, array_keys(PersistentDocuments::tables())), 'wbcms_persistent_documents'] as $table) {
      $sql .= 'DROP TABLE "'.$table.'";'."\n";
    }
    $sql .= "ALTER TABLE wbcms_system_backup_restores ADD COLUMN manifest TEXT;\nALTER TABLE wbcms_system_backup_restores ADD COLUMN restored_parts TEXT;\n";
    $sql .= "INSERT INTO wbcms_system_backup_restores (id, status, manifest, restored_parts) VALUES (2, 'completed', '{\"restored\":true}', '[]');\nPRAGMA foreign_keys=ON;\n";
    $path = tempnam(sys_get_temp_dir(), 'wb-schema-restore-');
    try {
      file_put_contents($path, $sql);
      $output = [];
      app(DatabaseRestoreRunner::class)->restoreFrom($path, $output);
      $this->assertFalse($store->available());
      $this->assertFalse($store->retired($record, 'manifest'));
      $this->assertSame(['restored' => true], SystemBackupRestore::findOrFail(2)->manifest);
      $outcome = SystemBackupRestore::create(['status' => 'completed', 'manifest' => ['outcome' => true], 'restored_parts' => ['database']]);
      $this->assertSame(['outcome' => true], $outcome->fresh()->manifest);
      $this->assertSame(['database'], $outcome->fresh()->restored_parts);
    } finally {
      unlink($path);
    }
  }
}
