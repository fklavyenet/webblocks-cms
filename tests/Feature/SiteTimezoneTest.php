<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Per-site timezone. The column is nullable and null means "follow the
 * install", so the resolver — not the raw attribute — is what anything
 * time-bound should read.
 */
class SiteTimezoneTest extends TestCase
{
  #[Test]
  public function an_explicit_site_timezone_is_used_as_is(): void
  {
    $this->bindSystemTimezone('UTC');

    $site = new Site;
    $site->timezone = 'Europe/Istanbul';

    $this->assertSame('Europe/Istanbul', $site->resolvedTimezone());
  }

  #[Test]
  public function a_site_without_a_timezone_follows_the_system_setting(): void
  {
    $this->bindSystemTimezone('Europe/Berlin');

    $this->assertSame('Europe/Berlin', (new Site)->resolvedTimezone());
  }

  #[Test]
  public function a_blank_site_timezone_is_treated_as_unset(): void
  {
    $this->bindSystemTimezone('Europe/Berlin');

    $site = new Site;
    $site->timezone = '   ';

    $this->assertSame('Europe/Berlin', $site->resolvedTimezone());
  }

  #[Test]
  public function the_raw_attribute_stays_null_so_the_operator_choice_is_still_readable(): void
  {
    $this->bindSystemTimezone('Europe/Berlin');

    $site = new Site;

    $this->assertNull($site->timezone);
    $this->assertSame('Europe/Berlin', $site->resolvedTimezone());
  }

  #[Test]
  public function the_site_timezone_is_fillable(): void
  {
    $site = new Site;
    $site->fill(['timezone' => 'Europe/Istanbul']);

    $this->assertSame('Europe/Istanbul', $site->timezone);
  }

  private function bindSystemTimezone(string $timezone): void
  {
    $settings = $this->createStub(SystemSettings::class);
    $settings->method('timezone')->willReturn($timezone);

    $this->app->instance(SystemSettings::class, $settings);
  }
}
