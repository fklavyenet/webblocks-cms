<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mcp\McpPages;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

final class UpdateDraftHeading
{
  public function __construct(
    private readonly McpPages $pages,
    private readonly McpPagePolicy $policy,
    private readonly InternalContentPlanService $plans,
    private readonly BlockTypeApiAuthoringPolicy $blocks,
    private readonly BlockTranslationWriter $translations,
    private readonly BlockTranslationResolver $translationResolver,
    private readonly PageRevisionManager $revisions,
  ) {}

  public function handle(User $user, int $siteId, array $input): array
  {
    if (! $this->policy->write($user, $siteId)) {
      throw new McpToolException('forbidden');
    }

    return DB::transaction(function () use ($user, $siteId, $input): array {
      $source = $this->pages->page($siteId, $input, lock: true);
      $draft = $this->pages->draft($source, lock: true);
      if ($draft && $draft->status !== Page::STATUS_DRAFT) {
        throw new McpToolException('conflict');
      }

      $this->lockBlocks($source, $draft);
      $this->pages->assertEditableType($source);
      $locale = $this->pages->locale($source, $input['locale']);
      $sourceFingerprint = $this->pages->fingerprint($source);
      if (! hash_equals($this->pages->version($source, $sourceFingerprint, $draft, $locale), $input['version'])) {
        throw new McpToolException('conflict');
      }
      if ($draft && ! $this->pages->hasVerifiedBase($draft, $sourceFingerprint)) {
        throw new McpToolException('unverified_base');
      }

      $heading = $this->pages->headings($source)->get($input['block_id']);
      $slot = $heading?->slotType?->slug;
      if (! $slot || $this->blocks->scopeHasHumanOnlyBlock($source->blocks)
        || ($draft && $this->blocks->scopeHasHumanOnlyBlock($draft->blocks))) {
        throw new McpToolException('heading_unavailable');
      }

      $draft ??= $this->plans->prepareAuthoringDraft($source, $user, [$slot], 'mcp_draft_prepared');
      $translated = $locale->id !== app(LocaleResolver::class)->default()->id;
      if (! $this->policy->editHeading($user, $draft, $translated)) {
        throw new McpToolException('forbidden');
      }

      $this->pages->locale($draft, $locale->code);
      $matches = $this->pages->headings($draft)->filter(fn (Block $block) => $block->revision_key === $heading->revision_key
        && $block->slot_type_id === $heading->slot_type_id && $block->typeSlug() === $heading->typeSlug());
      if ($matches->count() !== 1 || ! in_array($slot, $draft->setting('staged_update.managed_slots', []), true)) {
        throw new McpToolException('heading_unavailable');
      }

      $block = $matches->first();
      $this->revisions->capture($draft, $user, event: 'mcp_heading_before');
      // A new locale row replaces the fallback for the entire text block.
      // Keep its other resolved fields, with any site variables still intact.
      $copy = $this->translationResolver->resolve($block, $locale)->only(['eyebrow', 'subtitle', 'content', 'meta']);
      $this->translations->sync($block, ['title' => $input['title']] + $copy, $locale->code);
      $draft->forceFill(['updated_by_user_id' => $user->id])->touch();
      $this->revisions->capture($draft, $user, event: 'mcp_heading_updated');

      return $this->pages->previewLink($source, $draft, $locale) + ['block_id' => $block->id, 'title' => $input['title']];
    }, attempts: 3);
  }

  private function lockBlocks(Page $source, ?Page $draft): void
  {
    $ids = Block::query()->whereIn('page_id', array_filter([$source->id, $draft?->id]))->orderBy('id')->lockForUpdate()->pluck('id');
    BlockTextTranslation::query()->whereIn('block_id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
  }
}
