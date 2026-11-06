<?php

namespace WebBlocks\Cms\Actions\Mcp;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Formatting\SafeRichTextRenderer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Mcp\McpBlockFields;
use WebBlocks\Cms\Support\Mcp\McpMedia;
use WebBlocks\Cms\Support\Mcp\McpPages;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

final class UpdateDraftBlock
{
  public function __construct(
    private readonly McpPages $pages,
    private readonly McpPagePolicy $policy,
    private readonly InternalContentPlanService $plans,
    private readonly BlockTranslationWriter $translations,
    private readonly McpBlockFields $fields,
    private readonly McpMedia $media,
    private readonly SafeRichTextRenderer $richText,
    private readonly PageRevisionManager $revisions,
  ) {}

  public function handle(User $user, int $siteId, array $input, bool $headingOnly = false): array
  {
    if (! $this->policy->write($user, $siteId)) {
      throw new McpToolException('forbidden');
    }

    return DB::transaction(function () use ($user, $siteId, $input, $headingOnly): array {
      $source = $this->pages->page($siteId, $input, lock: true);
      $draft = $this->pages->draft($source, lock: true);
      if ($draft && $draft->status !== Page::STATUS_DRAFT) {
        throw new McpToolException('conflict');
      }

      $this->pages->lockBlocks($source, $draft);
      $this->pages->assertEditableType($source);
      $locale = $this->pages->locale($source, $input['locale']);
      $sourceFingerprint = $this->pages->fingerprint($source);
      if (! hash_equals($this->pages->version($source, $sourceFingerprint, $draft, $locale), $input['version'])) {
        throw new McpToolException('conflict');
      }
      if ($draft && ! $this->pages->hasVerifiedBase($draft, $sourceFingerprint)) {
        throw new McpToolException('unverified_base');
      }

      $sourceBlock = $this->candidates($source, $headingOnly)->get($input['block_id']);
      $slot = $sourceBlock?->slotType?->slug;
      $unavailable = $headingOnly ? 'heading_unavailable' : 'block_unavailable';
      if (! $slot || $this->pages->hasHumanOnlyBlocks($source, $draft)) {
        throw new McpToolException($unavailable);
      }

      $draft ??= $this->plans->prepareAuthoringDraft($source, $user, [$slot], 'mcp_draft_prepared');
      $translated = $locale->id !== app(LocaleResolver::class)->default()->id;
      if (! $this->policy->editDraft($user, $draft, $translated)) {
        throw new McpToolException('forbidden');
      }

      $this->pages->locale($draft, $locale->code);
      $matches = $this->pages->matchingBlocks($sourceBlock, $this->candidates($draft, $headingOnly));
      $managed = $draft->setting('staged_update.managed_slots', []);
      if ($matches->count() !== 1 || ! in_array($slot, $managed, true)
        || ! in_array($matches->first()->slotType?->slug, $managed, true)) {
        throw new McpToolException($unavailable);
      }

      $block = $matches->first();
      $changes = $headingOnly ? ['title' => $input['title']] : ($input['text'] ?? []);
      if (array_diff(array_keys($changes), $this->fields->textFields($block)) !== []
        || (isset($changes['title']) && trim($changes['title']) === '')) {
        throw new McpToolException('invalid_request');
      }
      if ($block->typeSlug() === 'rich-text' && isset($changes['content'])) {
        $changes['content'] = $this->richText->sanitize($changes['content']);
      }

      $media = null;
      if (isset($input['media_id'])) {
        if ($translated || ! $this->fields->acceptsImage($block)) {
          throw new McpToolException('media_unavailable');
        }
        $media = $this->media->selectedImage($user, $siteId, $input['media_id']);
      }

      $event = $headingOnly ? 'mcp_heading' : 'mcp_block';
      $this->revisions->capture($draft, $user, event: $event.'_before');
      if ($changes !== []) {
        $payload = $this->fields->translationPayload($block, $locale, $changes);
        $this->translations->sync($block, $payload, $locale->code);
      }
      if ($media) {
        $block->forceFill(['media_id' => $media->id])->save();
      }
      $draft->forceFill(['updated_by_user_id' => $user->id])->touch();
      $this->revisions->capture($draft, $user, event: $event.'_updated');

      return $this->pages->previewLink($source, $draft, $locale)
        + ['block_id' => $block->id]
        + ($headingOnly ? ['title' => $input['title']] : ['source_block_id' => $sourceBlock->id, 'updated_fields' => array_keys($changes), 'media_id' => $media?->id]);
    }, attempts: 3);
  }

  private function candidates(Page $page, bool $headingOnly): Collection
  {
    return $headingOnly ? $this->pages->headings($page) : $this->pages->staticBlocks($page)->filter($this->fields->supports(...));
  }
}
