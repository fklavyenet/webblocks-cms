<?php

namespace Tests\Unit\System\Updates;

use App\Models\SystemRelease;
use App\Support\System\Updates\VersionComparator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VersionComparatorTest extends TestCase
{
    #[Test]
    public function semantic_comparisons_are_handled_correctly(): void
    {
        $comparator = app(VersionComparator::class);

        $this->assertSame(-1, $comparator->compare('0.1.0', '0.1.1'));
        $this->assertSame(1, $comparator->compare('1.0.0', '1.0.0-beta.1'));
        $this->assertSame(0, $comparator->compare('2.3.4', '2.3.4'));
    }

    #[Test]
    public function latest_selection_uses_highest_semantic_version(): void
    {
        $comparator = app(VersionComparator::class);

        $releases = collect([
            new SystemRelease(['version' => '0.9.0']),
            new SystemRelease(['version' => '1.0.0-beta.1']),
            new SystemRelease(['version' => '1.0.0']),
        ]);

        $this->assertSame('1.0.0', $comparator->latest($releases)?->version);
    }

    #[Test]
    public function compatibility_checks_detect_platform_and_upgrade_path_issues(): void
    {
        $comparator = app(VersionComparator::class);
        $release = new SystemRelease([
            'version' => '0.2.0',
            'supported_from_version' => '0.1.0',
            'min_php_version' => '8.3.0',
            'min_laravel_version' => '13.0.0',
        ]);

        $compatible = $comparator->isCompatible($release, '0.1.5', '8.3.5', '13.2.0');
        $incompatible = $comparator->isCompatible($release, '0.0.9', '8.2.0', '12.0.0');

        $this->assertSame('compatible', $compatible->status);
        $this->assertSame('incompatible', $incompatible->status);
        $this->assertCount(3, $incompatible->reasons);
    }
}
