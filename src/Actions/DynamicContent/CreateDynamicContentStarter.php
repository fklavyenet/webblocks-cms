<?php

namespace WebBlocks\Cms\Actions\DynamicContent;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

final class CreateDynamicContentStarter
{
  public function handle(Page $page, int $slotId, User $actor): Block
  {
    return DB::transaction(function () use ($page, $slotId, $actor): Block {
      $page = Page::query()->lockForUpdate()->findOrFail($page->id);
      app(AdminAuthorization::class)->abortUnlessSiteAccess($actor, $page);
      abort_unless($page->status === Page::STATUS_DRAFT, 409);
      $slot = $page->slots()->whereKey($slotId)->firstOrFail();
      abort_unless($slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_PAGE, 422);
      $types = BlockType::query()->whereIn('slug', ['grid', 'card', 'card_body', 'card_footer', 'image', 'header', 'plain_text', 'button_link'])->get()->keyBy('slug');
      abort_unless($types->count() === 8, 422);
      $text = fn ($key) => app(CmsTranslator::class)->get('dynamic-authoring.'.$key);
      $make = function ($type, $parent = null, $order = 0, $extra = []) use ($page, $slot, $types): Block {
        return Block::query()->create(array_merge(['page_id' => $page->id, 'block_type_id' => $types[$type]->id, 'type' => $type, 'source_type' => 'static', 'slot' => $slot->slotType->slug, 'slot_type_id' => $slot->slot_type_id, 'parent_id' => $parent?->id, 'sort_order' => $order, 'status' => 'published'], $extra));
      };
      $grid = $make('grid', null, (int) $page->blocks()->where('slot_type_id', $slot->slot_type_id)->max('sort_order') + 1, ['settings' => ['columns' => '3']]);
      $card = $make('card', $grid);
      $body = $make('card_body', $card);
      $make('image', $body);
      $make('header', $body, 1, ['title' => $text('starter_title'), 'settings' => ['level' => 'h3']]);
      $make('plain_text', $body, 2, ['content' => $text('starter_summary')]);
      $footer = $make('card_footer', $card, 1);
      $make('button_link', $footer, 0, ['title' => $text('starter_link'), 'url' => '#']);
      $page->forceFill(['updated_by_user_id' => $actor->id])->touch();
      app(PageRevisionManager::class)->capture($page, $actor, event: 'dynamic_content_starter_created');

      return $grid;
    });
  }
}
