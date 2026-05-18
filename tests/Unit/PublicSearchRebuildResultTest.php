<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Search\PublicSearchRebuildResult;

class PublicSearchRebuildResultTest extends TestCase
{
    #[Test]
    public function it_tracks_indexed_and_skipped_counts(): void
    {
        $result = new PublicSearchRebuildResult;

        $this->assertSame(0, $result->indexed);
        $this->assertSame(0, $result->skipped);

        $returned = $result->addIndexed(2)->addSkipped(3);

        $this->assertSame($result, $returned);
        $this->assertSame(2, $result->indexed);
        $this->assertSame(3, $result->skipped);
    }
}
