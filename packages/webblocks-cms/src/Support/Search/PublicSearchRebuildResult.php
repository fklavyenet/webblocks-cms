<?php

namespace WebBlocks\Cms\Support\Search;

class PublicSearchRebuildResult
{
    public function __construct(
        public int $indexed = 0,
        public int $skipped = 0,
    ) {}

    public function addIndexed(int $count = 1): self
    {
        $this->indexed += $count;

        return $this;
    }

    public function addSkipped(int $count = 1): self
    {
        $this->skipped += $count;

        return $this;
    }
}
