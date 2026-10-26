<?php

namespace WebBlocks\Cms\Support\Contact;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class ContactConsentRequirements
{
  public const CODE = 'contact_consent_notice_required';

  public function missing(bool $required, mixed $notice): bool
  {
    return $required && (! is_string($notice) || trim($notice) === '');
  }

  public function message(): string
  {
    return app(CmsTranslator::class)->admin('blocks.contact_form.consent_notice_required', app(AdminLocaleResolver::class)->locale());
  }

  public function noticeForEdit(?Block $block, string $locale, array $copy, bool $writesTranslation = true): mixed
  {
    if (array_key_exists('consent_label', $copy)) {
      return $copy['consent_label'];
    }
    $rows = $block?->contactFormTranslations()->with('locale')->get() ?? collect();
    $row = $rows->first(fn ($row) => $row->locale?->code === $locale);
    if ($row || $writesTranslation) {
      return $row?->consent_label;
    }

    return $rows->firstWhere('locale_id', app(LocaleResolver::class)->default()->id)?->consent_label;
  }

  public function payloadErrors(string $type, array $settings, array $copy, string $path): array
  {
    if ($type !== 'contact_form') {
      return [];
    }
    $notice = array_key_exists('consent_label', $copy) ? $copy['consent_label'] : ($settings['consent_label'] ?? null);

    return $this->missing((bool) ($settings['consent_required'] ?? false), $notice)
      ? [['path' => $path.'.translations.consent_label', 'message' => $this->message(), 'code' => self::CODE]] : [];
  }

  /** Validate the actual publication scope, including visible shared content. */
  public function publicationErrors(Page $page, bool $includeUnpublished = false, ?Page $replacement = null, array $replaceSlots = [], ?Block $override = null): array
  {
    $page->loadMissing(['site.locales', 'slots.slotType', 'slots.sharedSlot']);
    $locales = $page->availableSiteLocales();
    $statuses = $includeUnpublished ? ['published', 'draft', 'in_review'] : ['published'];
    $own = $page->slots->filter(fn (PageSlot $slot) => $slot->usesPageOwnedBlocks());
    $replacedIds = $own->filter(fn ($slot) => in_array($slot->slotType?->slug, $replaceSlots, true))->pluck('slot_type_id')->all();
    $errors = $this->pageScopeErrors($page, $own->pluck('slot_type_id')->diff($replacedIds)->all(), $statuses, $locales, $override);
    if ($replacement && $replacedIds !== []) {
      // Promotion publishes every copied block, regardless of its draft status.
      $errors = [...$errors, ...$this->pageScopeErrors($replacement, $replacedIds, null, $locales)];
    }
    $sharedIds = $page->slots->filter(fn ($slot) => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT && $slot->sharedSlot?->is_active)->pluck('shared_slot_id')->unique();
    if ($sharedIds->isNotEmpty()) {
      $blocks = SharedSlotBlock::whereIn('shared_slot_id', $sharedIds)->with(['block.blockType', 'block.contactFormTranslations.locale'])->get()->pluck('block')->filter();
      $errors = [...$errors, ...$this->scopeErrors($blocks, ['published'], $locales)];
    }

    return $errors;
  }

  public function assertPage(Page $page, bool $includeUnpublished = false, ?Block $override = null): void
  {
    $this->assertErrors($this->publicationErrors($page, $includeUnpublished, override: $override));
  }

  public function assertBlock(Block $block, Page $page): void
  {
    $this->assertErrors($this->scopeErrors(collect([$block]), null, $page->availableSiteLocales(), false));
  }

  public function assertSharedSlot(SharedSlot $sharedSlot, bool $publishAll = false): void
  {
    $blocks = SharedSlotBlock::where('shared_slot_id', $sharedSlot->id)->with(['block.blockType', 'block.contactFormTranslations.locale'])->get()->pluck('block')->filter();
    $this->assertErrors($this->scopeErrors($blocks, $publishAll ? null : ['published'], $sharedSlot->site->enabledLocales()->get()));
  }

  public function assertErrors(array $errors): void
  {
    if ($errors !== []) {
      throw ContactConsentValidationException::withMessages(collect($errors)->mapWithKeys(fn ($error) => [$error['path'] => $error['message']])->all());
    }
  }

  private function pageScopeErrors(Page $page, array $slotIds, ?array $statuses, Collection $locales, ?Block $override = null): array
  {
    if ($slotIds === [] || ! $page->blocks()->whereIn('slot_type_id', $slotIds)->where(function ($query): void {
      $query->where('type', 'contact_form')->orWhereHas('blockType', fn ($query) => $query->where('slug', 'contact_form'));
    })->exists()) {
      return [];
    }
    $blocks = $page->blocks()->whereIn('slot_type_id', $slotIds)->with(['blockType', 'contactFormTranslations.locale'])->get();
    if ($override) {
      $blocks = $blocks->map(fn ($block) => $block->id === $override->id ? $override : $block);
    }

    return $this->scopeErrors($blocks, $statuses, $locales);
  }

  private function scopeErrors(Collection $blocks, ?array $statuses, Collection $locales, bool $respectAncestors = true): array
  {
    $byId = $blocks->keyBy('id');
    $default = app(LocaleResolver::class)->default();
    $defaultId = $default->id;
    if ($locales->isEmpty()) {
      $locales = collect([$default]);
    }
    $errors = [];
    foreach ($blocks as $block) {
      if ($block->typeSlug() !== 'contact_form' || ! $block->setting('consent_required', false)) {
        continue;
      }
      $current = $block;
      $visited = [];
      while ($respectAncestors && $current) {
        if (isset($visited[$current->id]) || ($statuses !== null && ! in_array($current->status, $statuses, true))) {
          continue 2;
        }
        $visited[$current->id] = true;
        if ($current->parent_id && ! $byId->has($current->parent_id)) {
          continue 2;
        }
        $current = $current->parent_id ? $byId->get($current->parent_id) : null;
      }
      $rows = $block->contactFormTranslations;
      foreach ($locales as $locale) {
        $row = $rows->firstWhere('locale_id', $locale->id) ?? $rows->firstWhere('locale_id', $defaultId);
        if ($this->missing(true, $row?->consent_label)) {
          $errors[] = [
            'path' => 'blocks.'.$block->id.'.translations.'.$locale->code.'.consent_label',
            'message' => app(CmsTranslator::class)->admin('blocks.contact_form.consent_publish_missing', app(AdminLocaleResolver::class)->locale(), ['block' => $block->id, 'locale' => $locale->code]),
            'code' => self::CODE,
          ];
        }
      }
    }

    return $errors;
  }
}
