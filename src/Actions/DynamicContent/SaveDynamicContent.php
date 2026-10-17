<?php

namespace WebBlocks\Cms\Actions\DynamicContent;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoring;
use WebBlocks\Cms\Support\ContentSources\ContentSourceAuthoringException;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\DynamicContentDesign;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class SaveDynamicContent
{
  public function __construct(private readonly DynamicContentDesign $design, private readonly ContentSourceAuthoring $authoring, private readonly ContentSourceEditor $editor) {}

  public function configure(Page $page, array $input): array
  {
    $blocks = $this->design->blocks($page);
    $block = $blocks->get((int) ($input['block_id'] ?? 0));
    abort_unless($block, 404);
    $original = $blocks->map(fn ($node) => $node->decodedSettings());
    $collection = null;
    if ($this->editor->supportsCollection($block) && isset($input['collection'])) {
      $collection = array_replace((array) $block->setting('content_collection', []), $input['collection']);
      $collection['source'] = $input['source'];
      $collection['paginate'] = (bool) ($collection['paginate'] ?? false);
      $settings = $block->decodedSettings();
      $settings['content_collection'] = $collection;
      $block->settings = $settings;
    }
    $allowed = $this->design->mappingBlocks($block, $collection ? (int) ($collection['template_block_id'] ?? 0) : null);
    $changed = collect();
    try {
      foreach (($input['mappings'] ?? []) as $id => $selections) {
        $targetBlock = $allowed->get((int) $id);
        if (! $targetBlock) {
          throw new ContentSourceAuthoringException('mappings', 'Mapping block outside the selected template.');
        }
        $bindings = (array) $targetBlock->setting('content_bindings', []);
        foreach ($selections as $target => $selection) {
          if (! array_key_exists($target, $this->editor->targets($targetBlock))) {
            throw new ContentSourceAuthoringException('mappings', 'Invalid target.');
          }
          if ($selection === '' || $selection === null) {
            unset($bindings[$target]);
          } else {
            $parts = explode('|', $selection);
            if (count($parts) !== 3) {
              throw new ContentSourceAuthoringException('mappings', 'Incomplete field selection.');
            }
            $bindings[$target] = array_replace($bindings[$target] ?? [], ['source' => $parts[0], 'record' => $parts[1], 'field' => $parts[2]]);
          }
        }
        $targetBlock->settings = $this->authoring->merge($targetBlock, $targetBlock->decodedSettings(), $bindings, true, null, false);
        $changed->put($targetBlock->id, $targetBlock);
      }
      if ($collection !== null) {
        // Validate the proposed template and all remaining descendant mappings together.
        $configuredSettings = $block->decodedSettings();
        $block->settings = $original->get($block->id);
        $block->settings = $this->authoring->merge($block, $configuredSettings, null, false, $collection, true);
        $changed->put($block->id, $block);
      }
    } catch (ContentSourceAuthoringException $exception) {
      throw ValidationException::withMessages(['configuration' => app(CmsTranslator::class)->get('dynamic-authoring.'.$exception->reason, app(AdminLocaleResolver::class)->locale())]);
    }

    return [$block, $changed];
  }

  public function handle(Page $page, array $input, User $actor): void
  {
    DB::transaction(function () use ($page, $input, $actor): void {
      $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
      app(AdminAuthorization::class)->abortUnlessSiteAccess($actor, $locked);
      abort_unless($locked->status === Page::STATUS_DRAFT, 409);
      $version = $this->design->fingerprint($locked);
      if (! hash_equals($version, (string) ($input['version'] ?? ''))) {
        throw ValidationException::withMessages(['version' => app(CmsTranslator::class)->get('dynamic-authoring.stale', app(AdminLocaleResolver::class)->locale())]);
      }
      [, $changed] = $this->configure($locked, $input);
      foreach ($changed as $block) {
        $block->save();
      }
      $locked->forceFill(['updated_by_user_id' => $actor->id])->touch();
      app(PageRevisionManager::class)->capture($locked, $actor, event: 'dynamic_content_draft_saved');
    });
  }
}
