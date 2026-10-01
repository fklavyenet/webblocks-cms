<?php

namespace WebBlocks\Cms\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Tests\TestCase;

class PublicMountTest extends TestCase
{
  public static function mounts(): array
  {
    return [[null, ''], ['', ''], ['//', ''], ['wb', 'wb'], ['/wb', 'wb'], ['wb/', 'wb'], [' /wb/ ', 'wb'], ['docs/site', 'docs/site']];
  }

  #[DataProvider('mounts')]
  public function test_normalization_and_round_trip(?string $value, string $expected): void
  {
    config()->set('webblocks-cms.public.mount', $value);
    $mount = app(PublicMount::class);
    $this->assertSame($expected, $mount->prefix());
    $this->assertSame('/de/about', $mount->unmount($mount->path('/de/about')));
    $this->assertSame($expected === '' ? '/' : '/'.$expected, $mount->path('/'));
    if ($expected !== '') {
      $this->assertNull($mount->unmount('/'.$expected.'-other/about'));
    }
  }

  public function test_missing_published_config_key_preserves_integrated_paths(): void
  {
    config()->set('webblocks-cms.public', ['load_routes' => true]);
    $this->assertSame('/about', app(PublicMount::class)->path('/about'));
  }

  public function test_invalid_mount_is_rejected_instead_of_silently_claiming_root(): void
  {
    config()->set('webblocks-cms.public.mount', '../webadmin');
    $this->expectException(InvalidArgumentException::class);
    app(PublicMount::class)->prefix();
  }
}
