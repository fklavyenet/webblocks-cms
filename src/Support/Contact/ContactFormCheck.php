<?php

namespace WebBlocks\Cms\Support\Contact;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\PublicSubmissions\SubmissionProof;

class ContactFormCheck
{
  public function fieldName(Block|int $block): string
  {
    return app(SubmissionProof::class)->fieldName('cms-block', $this->id($block));
  }

  public function signedFieldName(Block|int $block): string
  {
    return app(SubmissionProof::class)->signedFieldName('cms-block', $this->id($block));
  }

  public function issueStamp(Block|int $block): string
  {
    return app(SubmissionProof::class)->issueStamp('cms-block', $this->id($block));
  }

  public function elapsedSeconds(?string $stamp, Block|int $block): ?int
  {
    return app(SubmissionProof::class)->elapsedSeconds($stamp, 'cms-block', $this->id($block));
  }

  public function isFilled(array $input, Block|int $block): bool
  {
    return app(SubmissionProof::class)->trapWasTriggered($input, 'cms-block', $this->id($block));
  }

  private function id(Block|int $block): int
  {
    return $block instanceof Block ? (int) $block->id : $block;
  }
}
