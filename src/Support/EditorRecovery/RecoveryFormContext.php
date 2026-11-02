<?php

namespace WebBlocks\Cms\Support\EditorRecovery;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final readonly class RecoveryFormContext
{
  public const KINDS = ['new-page', 'new-block', 'new-translation', 'translation'];

  public function __construct(public string $kind, public Site $site, public Locale $locale, public ?Page $page = null, public ?BlockType $type = null, public ?SlotType $slot = null, public ?Block $parent = null, public ?PageTranslation $translation = null) {}

  public function attributes(): array
  {
    return ['form_kind' => $this->kind, 'site_id' => $this->site->id, 'page_id' => $this->page?->id,
      'block_id' => null, 'locale' => $this->locale->code, 'block_type_id' => $this->type?->id,
      'slot_type_id' => $this->slot?->id, 'parent_block_id' => $this->parent?->id, 'translation_id' => $this->translation?->id];
  }

  public function transport(): array
  {
    return array_filter(['form_kind' => $this->kind, 'site_id' => $this->kind === 'new-page' ? $this->site->id : null,
      'page_id' => $this->page?->id, 'locale' => $this->locale->code, 'block_type_id' => $this->type?->id,
      'slot_type_id' => $this->slot?->id, 'parent_id' => $this->parent?->id, 'translation_id' => $this->translation?->id], fn ($value) => $value !== null);
  }

  public function fields(): string
  {
    return match ($this->kind) {
      'new-page' => RecoveryFields::PAGE_FIELDS,
      'new-block' => RecoveryFields::BLOCK_FIELDS,
      default => RecoveryFields::TRANSLATION_FIELDS,
    };
  }

  public function fingerprint(): string
  {
    return $this->page ? app(EditorRecovery::class)->fingerprint($this->page)
      : SnapshotContent::hash(['site_id' => $this->site->id, 'locale' => $this->locale->code]);
  }

  public function editorUrl(): string
  {
    if ($this->kind === 'new-page') {
      return route('admin.pages.create', ['site' => $this->site->id]);
    }
    if ($this->kind === 'new-block') {
      $slot = PageSlot::query()->where('page_id', $this->page->id)->where('slot_type_id', $this->slot->id)->first();

      return route('admin.pages.slots.blocks', array_filter(['page' => $this->page->id, 'slot' => $slot->id,
        'block_type_id' => $this->type->id, 'parent_id' => $this->parent?->id, 'locale' => $this->locale->code], fn ($value) => $value !== null));
    }

    return $this->translation
      ? route('admin.pages.translations.edit', [$this->page, $this->translation])
      : route('admin.pages.translations.create', [$this->page, $this->locale]);
  }
}
