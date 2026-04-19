<?php

namespace Tests\Unit\Release;

use App\Support\Release\ReleaseException;
use App\Support\Release\ReleaseVersionResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleaseVersionResolverTest extends TestCase
{
    #[Test]
    public function explicit_version_takes_priority(): void
    {
        config()->set('app.version', '0.1.2');

        $this->assertSame('0.2.0', app(ReleaseVersionResolver::class)->resolve('0.2.0', '0.1.9'));
    }

    #[Test]
    public function argument_version_is_used_before_app_fallback(): void
    {
        config()->set('app.version', '0.1.2');

        $this->assertSame('0.1.9', app(ReleaseVersionResolver::class)->resolve(null, '0.1.9'));
    }

    #[Test]
    public function app_version_is_used_as_final_fallback(): void
    {
        config()->set('app.version', '0.1.2');

        $this->assertSame('0.1.2', app(ReleaseVersionResolver::class)->resolve(null, null));
    }

    #[Test]
    public function missing_version_throws_clear_exception(): void
    {
        config()->set('app.version', '');

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('No release version could be resolved. Provide --version explicitly.');

        app(ReleaseVersionResolver::class)->resolve(null, null);
    }
}
