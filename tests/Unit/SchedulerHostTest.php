<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\System\SchedulerHost;

class SchedulerHostTest extends TestCase
{
  public function test_readable_external_definitions_are_detected_without_returning_their_contents(): void
  {
    $file = tempnam(sys_get_temp_dir(), 'wb-cron-test-');
    $host = new class($file) extends SchedulerHost
    {
      public function __construct(private readonly string $file) {}

      protected function externalFiles(): array
      {
      return [$this->file];
      }
    };
    try {
      file_put_contents($file, "# cd /srv/site && php artisan schedule:run\nMAILTO=private@example.test\n");
      $this->assertFalse($host->externalDefinition('/srv/site'));
      file_put_contents($file, "WorkingDirectory=/srv/site\nExecStart=/usr/bin/php artisan schedule:run\nEnvironment=PRIVATE=secret\n");
      $this->assertTrue($host->externalDefinition('/srv/site'));
      $this->assertFalse($host->externalDefinition('/srv/other'));
      file_put_contents($file, str_repeat('x', 262145)."\n/srv/site schedule:run\n");
      clearstatcache(true, $file);
      $this->assertFalse($host->externalDefinition('/srv/site'));
    } finally {
      unlink($file);
    }
  }
}
