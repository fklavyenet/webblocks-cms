<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageTranslationParentKeyUpdateMigrationTest extends TestCase
{
    private ?string $sqlitePath = null;

    #[Test]
    public function package_update_migration_repairs_existing_pages_table_missing_site_parent_key(): void
    {
        $originalConnection = DB::getDefaultConnection();
        $this->sqlitePath = storage_path('app/testing-schema-repairs/'.Str::uuid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->sqlitePath));
        File::put($this->sqlitePath, '');

        config()->set('database.connections.schema_repair', [
            'driver' => 'sqlite',
            'database' => $this->sqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::setDefaultConnection('schema_repair');

        try {
            Schema::create('pages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
            });

            $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_05_21_213000_ensure_pages_site_parent_key.php');
            $migration->up();
            $migration->up();

            $indexes = collect(DB::select("pragma index_list('pages')"))
                ->where('name', 'pages_id_site_id_unique')
                ->values();

            $this->assertCount(1, $indexes);
            $this->assertSame(1, (int) $indexes->first()->unique);
            $this->assertSame(['id', 'site_id'], collect(DB::select("pragma index_info('pages_id_site_id_unique')"))
                ->sortBy('seqno')
                ->pluck('name')
                ->values()
                ->all());
        } finally {
            DB::disconnect('schema_repair');
            DB::setDefaultConnection($originalConnection);
        }
    }

    #[Test]
    public function package_update_migration_reads_uppercase_mysql_metadata_rows(): void
    {
        $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_05_21_213000_ensure_pages_site_parent_key.php');
        $reader = new \ReflectionMethod($migration, 'metadataValue');
        $reader->setAccessible(true);

        $this->assertSame('site_id', $reader->invoke(
            $migration,
            (object) ['COLUMN_NAME' => 'site_id'],
            'column_name',
            'COLUMN_NAME'
        ));
    }

    protected function tearDown(): void
    {
        if ($this->sqlitePath !== null) {
            File::delete($this->sqlitePath);
        }

        parent::tearDown();
    }
}
