<?php

namespace Tests\Unit\System;

use WebBlocks\Cms\Models\SystemBackup;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\Updates\UpdateResult;

class UpdateResultCompatibilityTest extends TestCase
{
    #[Test]
    public function update_result_accepts_a_root_system_backup_wrapper_for_pre_update_backup(): void
    {
        $backup = new SystemBackup;
        $backup->forceFill([
            'id' => 42,
            'type' => SystemBackup::TYPE_PRE_UPDATE,
            'status' => SystemBackup::STATUS_COMPLETED,
            'archive_filename' => 'pre-update-backup.zip',
        ]);

        $result = new UpdateResult(
            fromVersion: '1.32.5',
            toVersion: '1.32.6',
            status: 'success',
            summary: 'Updated successfully.',
            output: 'Installed version persisted as 1.32.6',
            warningCount: 0,
            startedAt: CarbonImmutable::parse('2026-05-20 10:00:00'),
            finishedAt: CarbonImmutable::parse('2026-05-20 10:01:00'),
            durationMs: 60000,
            preUpdateBackup: $backup,
        );

        $this->assertInstanceOf(SystemBackup::class, $result->preUpdateBackup);
        $this->assertSame(42, $result->preUpdateBackup?->getKey());
        $this->assertSame(SystemBackup::TYPE_PRE_UPDATE, $result->preUpdateBackup?->type);
    }
}
