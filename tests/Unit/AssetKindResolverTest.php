<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Support\Assets\AssetKindResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetKindResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_image_assets(): void
    {
        $this->assertSame(Asset::KIND_IMAGE, AssetKindResolver::resolve('image/png', 'png'));
    }

    #[Test]
    public function it_resolves_video_assets(): void
    {
        $this->assertSame(Asset::KIND_VIDEO, AssetKindResolver::resolve('video/mp4', 'mp4'));
    }

    #[Test]
    public function it_resolves_document_assets(): void
    {
        $this->assertSame(Asset::KIND_DOCUMENT, AssetKindResolver::resolve('application/pdf', 'pdf'));
    }

    #[Test]
    public function it_falls_back_to_other_assets(): void
    {
        $this->assertSame(Asset::KIND_OTHER, AssetKindResolver::resolve('application/octet-stream', 'bin'));
    }
}
