<?php

namespace WebBlocks\Cms\Support\SharedSlots;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotRevision;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Contact\ContactConsentRequirements;
use WebBlocks\Cms\Support\Media\LegacyAssetPayloadNormalizer;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RestoreWriteCancelledException;
use WebBlocks\Cms\Support\Revisions\RevisionCaptureRecorder;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;

class SharedSlotRevisionManager
{
  private const SNAPSHOT_SCHEMA_VERSION = 2;

  public function __construct(
    private readonly SharedSlotSourcePageManager $sourcePages,
    private readonly BlockTranslationWriter $blockTranslationWriter,
    private readonly SharedSlotSchema $schema,
    private readonly CurrentActorResolver $currentActorResolver,
    private readonly LegacyAssetPayloadNormalizer $legacyAssetPayloadNormalizer,
    private readonly RevisionCaptureRecorder $recorder,
  ) {}

  public function canView(User $user, SharedSlot $sharedSlot): bool
  {
    $acl = app(CmsAuthorization::class);
    if ($acl->ready()) {
    return $acl->allows($user, 'cms.shared-slots.read', $sharedSlot->site_id);
    }

    return $user->canAccessAdmin()
      && $user->hasSiteAccess($sharedSlot->site_id)
      && ($user->isSuperAdmin() || $user->isSiteAdmin() || $user->isEditor());
  }

  public function canRestore(User $user, SharedSlot $sharedSlot): bool
  {
    $acl = app(CmsAuthorization::class);
    if ($acl->ready()) {
    return $acl->allows($user, 'cms.shared-slots.restore', $sharedSlot->site_id);
    }

    return ($user->isSuperAdmin() || $user->isSiteAdmin())
      && $user->hasSiteAccess($sharedSlot->site_id);
  }

  public function revisionsTableExists(): bool
  {
    return $this->schema->revisionsTableExists();
  }

  public function capture(
    SharedSlot $sharedSlot,
    ?User $actor = null,
    string $sourceEvent = 'metadata_updated',
    ?string $label = null,
    ?string $summary = null,
    ?SharedSlotRevision $restoredFrom = null,
    bool $force = false,
    ?string $source = null,
  ): SharedSlotRevision {
    if (! $this->revisionsTableExists()) {
      throw new \RuntimeException('Shared Slot revisions are not ready. Run the latest migrations before using revisions.');
    }

    $resolvedActor = $this->currentActorResolver->resolve($actor, $source);

    return $this->recorder->record($sharedSlot, SharedSlotRevision::class, 'shared_slot_id', fn (SharedSlot $lockedSlot): array => $this->snapshot($lockedSlot), [
      'user_id' => $resolvedActor['user_id'],
      'created_by_user_id' => $resolvedActor['user_id'],
      'source' => $resolvedActor['source'],
      'event' => $sourceEvent,
      'source_event' => $sourceEvent,
      'label' => $label,
      'summary' => $summary,
      'restored_from_shared_slot_revision_id' => $restoredFrom?->id,
    ], $force || $restoredFrom !== null);
  }

  public function restore(SharedSlot $sharedSlot, SharedSlotRevision $revision, User $actor): void
  {
    if (! $this->revisionsTableExists()) {
      throw new \RuntimeException('Shared Slot revisions are not ready. Run the latest migrations before using revisions.');
    }

    abort_unless($sharedSlot->id === $revision->shared_slot_id, 404);
    abort_unless((int) $sharedSlot->site_id === (int) $revision->site_id, 404);

    app(RevisionContentUpdates::class)->during(function () use ($sharedSlot, $revision, $actor): void {
      $lockedSharedSlot = SharedSlot::query()->lockForUpdate()->findOrFail($sharedSlot->id);
      $lockedRevision = SharedSlotRevision::query()->findOrFail($revision->id);

      $this->capture(
        $lockedSharedSlot,
        $actor,
        'pre_restore_safety_snapshot',
        'Pre-restore safety snapshot',
        'Current Shared Slot state was saved before restore.',
        force: true,
        source: 'restore',
      );

      $this->applySnapshot($lockedSharedSlot, $lockedRevision->snapshot ?? []);
      if ($lockedSharedSlot->fresh()->is_active) {
        app(ContactConsentRequirements::class)->assertSharedSlot($lockedSharedSlot->fresh());
      }

      $this->capture(
        $lockedSharedSlot->fresh(),
        $actor,
        'restored',
        'Revision restored',
        'Shared Slot content was restored from revision #'.$lockedRevision->id.'.',
        $lockedRevision,
        true,
        'restore',
      );
    });
  }

  private function snapshot(SharedSlot $sharedSlot): array
  {
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $sourcePage->loadMissing([
      'slots.slotType',
      'blocks' => fn ($query) => $query
        ->with([
          'blockAssets.galleryItemTranslations',
          'textTranslations',
          'buttonTranslations',
          'imageTranslations',
          'contactFormTranslations',
      'pluginTranslations',
        ])
        ->orderBy('sort_order')
        ->orderBy('id'),
    ]);

    $blocks = $sourcePage->blocks
      ->sortBy(fn (Block $block) => sprintf('%04d-%04d', $block->parent_id ?? 0, $block->sort_order))
      ->values();

    return [
      'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
      'captured_at' => now()->toIso8601String(),
      'shared_slot' => [
        'name' => $sharedSlot->name,
        'handle' => $sharedSlot->handle,
        'slot_name' => $sharedSlot->slot_name,
        'public_shell' => $sharedSlot->public_shell,
        'is_active' => (bool) $sharedSlot->is_active,
      ],
      'source_page' => [
        'slug' => $sourcePage->slug,
        'page_type' => $sourcePage->page_type,
        'settings' => $sourcePage->serializedSettings(),
        'slot_type_id' => $sourcePage->slots->first()?->slot_type_id,
      ],
      'blocks' => $blocks
        ->map(fn (Block $block) => [
          'snapshot_id' => $block->id,
          'revision_key' => $block->revision_key,
          'parent_snapshot_id' => $block->parent_id,
          'type' => $block->type,
          'block_type_id' => $block->block_type_id,
          'source_type' => $block->source_type,
          'slot' => $block->slot,
          'slot_type_id' => $block->slot_type_id,
          'sort_order' => $block->sort_order,
          'title' => $block->getRawOriginal('title'),
          'subtitle' => $block->getRawOriginal('subtitle'),
          'content' => $block->getRawOriginal('content'),
          'url' => $block->url,
          'media_id' => $block->media_id,
          'variant' => $block->variant,
          'meta' => $block->meta,
          'settings' => $block->serializedSettings(),
          'status' => $block->status,
          'is_system' => $block->is_system,
          'block_media' => $block->blockAssets
            ->sortBy('position')
            ->values()
            ->map(fn ($asset) => [
              'snapshot_id' => $asset->id,
              'revision_key' => $asset->revision_key,
              'media_id' => $asset->media_id,
              'role' => $asset->role,
              'position' => $asset->position,
              'gallery_item_translations' => $asset->galleryItemTranslations
                ->sortBy('locale_id')
                ->values()
                ->map(fn (BlockGalleryItemTranslation $translation) => [
                  'locale_id' => $translation->locale_id,
                  'alt_text' => $translation->alt_text,
                  'caption' => $translation->caption,
                  'overlay_title' => $translation->overlay_title,
                  'overlay_text' => $translation->overlay_text,
                ])
                ->all(),
            ])
            ->all(),
          'plugin_translations' => $block->pluginTranslations->sortBy(fn ($row) => sprintf('%010d:%s', $row->locale_id, $row->field))->values()->map(fn ($row) => ['locale_id' => $row->locale_id, 'field' => $row->field, 'value' => $row->value])->all(),
          'text_translations' => $block->textTranslations
            ->sortBy('locale_id')
            ->values()
            ->map(fn ($translation) => [
              'locale_id' => $translation->locale_id,
              'title' => $translation->title,
              'eyebrow' => $translation->eyebrow,
              'subtitle' => $translation->subtitle,
              'content' => $translation->content,
              'meta' => $translation->meta,
            ])
            ->all(),
          'button_translations' => $block->buttonTranslations
            ->sortBy('locale_id')
            ->values()
            ->map(fn ($translation) => [
              'locale_id' => $translation->locale_id,
              'title' => $translation->title,
            ])
            ->all(),
          'image_translations' => $block->imageTranslations
            ->sortBy('locale_id')
            ->values()
            ->map(fn ($translation) => [
              'locale_id' => $translation->locale_id,
              'caption' => $translation->caption,
              'alt_text' => $translation->alt_text,
            ])
            ->all(),
          'contact_form_translations' => $block->contactFormTranslations
            ->sortBy('locale_id')
            ->values()
            ->map(fn ($translation) => [
              'locale_id' => $translation->locale_id,
              'title' => $translation->title,
              'content' => $translation->content,
              'submit_label' => $translation->submit_label,
              'success_message' => $translation->success_message,
              'consent_label' => $translation->consent_label,
            ])
            ->all(),
        ])
        ->all(),
    ];
  }

  private function applySnapshot(SharedSlot $sharedSlot, array $snapshot): void
  {
    $snapshot = $this->legacyAssetPayloadNormalizer->normalizeRevisionSnapshot($snapshot);
    $sharedSlotData = Arr::get($snapshot, 'shared_slot', []);

    $sharedSlot->forceFill([
      'name' => Arr::get($sharedSlotData, 'name', $sharedSlot->name),
      'handle' => Arr::get($sharedSlotData, 'handle', $sharedSlot->handle),
      'slot_name' => Arr::get($sharedSlotData, 'slot_name', $sharedSlot->slot_name),
      'public_shell' => Arr::get($sharedSlotData, 'public_shell', $sharedSlot->public_shell),
      'is_active' => (bool) Arr::get($sharedSlotData, 'is_active', $sharedSlot->is_active),
    ])->save();

    $sharedSlot = $sharedSlot->fresh();
    $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
    $sourcePageData = Arr::get($snapshot, 'source_page', []);

    if (is_string(Arr::get($sourcePageData, 'slug')) && trim((string) Arr::get($sourcePageData, 'slug')) !== '') {
      $sourcePage->forceFill([
        'slug' => Arr::get($sourcePageData, 'slug'),
      ])->save();
    }

    $sourcePage->blocks()->delete();

    $snapshotBlocks = collect(Arr::get($snapshot, 'blocks', []));
    $idMap = [];
    $remaining = $snapshotBlocks->values();

    while ($remaining->isNotEmpty()) {
      $createdThisPass = 0;

      $nextRemaining = $remaining->reject(function (array $block) use ($sourcePage, &$idMap, &$createdThisPass) {
        $parentSnapshotId = $block['parent_snapshot_id'] ?? null;

        if ($parentSnapshotId !== null && ! array_key_exists($parentSnapshotId, $idMap)) {
          return false;
        }

        $created = $sourcePage->blocks()->make([
          'parent_id' => $parentSnapshotId === null ? null : $idMap[$parentSnapshotId],
          'type' => $block['type'],
          'block_type_id' => $block['block_type_id'],
          'source_type' => $block['source_type'],
          'slot' => $block['slot'],
          'slot_type_id' => $block['slot_type_id'],
          'sort_order' => $block['sort_order'],
          'title' => $block['title'],
          'subtitle' => $block['subtitle'],
          'content' => $block['content'],
          'url' => $block['url'],
          'media_id' => $block['media_id'] ?? null,
          'variant' => $block['variant'],
          'meta' => $block['meta'],
          'settings' => $block['settings'],
          'status' => $block['status'],
          'is_system' => $block['is_system'],
        ]);

        $created->withRevisionIdentity($block['revision_key'] ?? null);
        if (! app(PersistentDocuments::class)->createRestored($created)) {
          throw new RestoreWriteCancelledException;
        }

        $idMap[$block['snapshot_id']] = $created->id;
        $createdThisPass++;

        return true;
      })->values();

      if ($createdThisPass === 0) {
        throw new \RuntimeException('Shared Slot revision restore could not resolve block parent relationships.');
      }

      $remaining = $nextRemaining;
    }

    app(RestoredBlockReads::class)->each($snapshotBlocks, $idMap, function (Block $restoredBlock, array $block): void {
      foreach ($block['block_media'] ?? [] as $asset) {
        $restoredBlockAsset = $restoredBlock->blockAssets()->make([
          'media_id' => $asset['media_id'] ?? null,
          'role' => $asset['role'],
          'position' => $asset['position'],
        ]);

        $restoredBlockAsset->withRevisionIdentity($asset['revision_key'] ?? null)->save();

        foreach ($asset['gallery_item_translations'] ?? [] as $translation) {
          $restoredBlockAsset->galleryItemTranslations()->create($translation);
        }
      }

      foreach ($block['plugin_translations'] ?? [] as $translation) {
        $restoredBlock->pluginTranslations()->create($translation);
      }

      foreach ($block['text_translations'] ?? [] as $translation) {
        $row = $restoredBlock->textTranslations()->make($translation);
        if (! app(PersistentDocuments::class)->createRestored($row)) {
          throw new RestoreWriteCancelledException;
        }
      }

      foreach ($block['button_translations'] ?? [] as $translation) {
        $restoredBlock->buttonTranslations()->create($translation);
      }

      foreach ($block['image_translations'] ?? [] as $translation) {
        $restoredBlock->imageTranslations()->create($translation);
      }

      foreach ($block['contact_form_translations'] ?? [] as $translation) {
        $restoredBlock->contactFormTranslations()->create($translation);
      }

      $this->normalizeRestoredBlock($restoredBlock, $block);
    });

    $this->sourcePages->ensureFor($sharedSlot->fresh());
    $this->sourcePages->rebuildAssignments($sharedSlot->fresh());
  }

  private function normalizeRestoredBlock(Block $block, array $snapshotBlock): void
  {
    $legacyFallbackUsed = $this->ensureLegacySnapshotFallbackTranslations($block, $snapshotBlock);

    $target = app(RestoredBlockReads::class)->forNormalization($block, $legacyFallbackUsed);
    if ($target !== null) {
      $this->blockTranslationWriter->normalizeCanonicalStorage($target);
    }
  }

  private function ensureLegacySnapshotFallbackTranslations(Block $block, array $snapshotBlock): bool
  {
    if (
      ($snapshotBlock['text_translations'] ?? []) !== []
      || ($snapshotBlock['button_translations'] ?? []) !== []
      || ($snapshotBlock['image_translations'] ?? []) !== []
      || ($snapshotBlock['contact_form_translations'] ?? []) !== []
    ) {
      return false;
    }

    $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

    if (! $defaultLocaleId) {
      return false;
    }

    $rawTitle = $snapshotBlock['title'] ?? null;
    $rawSubtitle = $snapshotBlock['subtitle'] ?? null;
    $rawContent = $snapshotBlock['content'] ?? null;
    $rawSettings = $snapshotBlock['settings'] ?? null;

    return match ($block->type) {
      'header', 'plain_text', 'content_header', 'button_link', 'card', 'column_item', 'feature-grid', 'feature-item', 'link-list', 'link-list-item', 'section', 'cta', 'text', 'rich-text', 'callout', 'quote', 'faq' => $this->seedLegacyTextSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawSubtitle, $rawContent),
      'button' => $this->seedLegacyButtonSnapshotFallback($block, $defaultLocaleId, $rawTitle),
      'image' => $this->seedLegacyImageSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawSubtitle),
      'contact_form' => $this->seedLegacyContactFormSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawContent, $rawSettings),
      default => false,
    };
  }

  private function seedLegacyTextSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $subtitle, mixed $content): bool
  {
    if (! is_string($title) && ! is_string($subtitle) && ! is_string($content)) {
      return false;
    }

    $block->textTranslations()->updateOrCreate(
      ['locale_id' => $defaultLocaleId],
      [
        'title' => $title,
        'subtitle' => $subtitle,
        'content' => $content,
      ],
    );

    return true;
  }

  private function seedLegacyButtonSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title): bool
  {
    if (! is_string($title) || trim($title) === '') {
      return false;
    }

    $block->buttonTranslations()->updateOrCreate(
      ['locale_id' => $defaultLocaleId],
      ['title' => $title],
    );

    return true;
  }

  private function seedLegacyImageSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $subtitle): bool
  {
    if (! is_string($title) && ! is_string($subtitle)) {
      return false;
    }

    $block->imageTranslations()->updateOrCreate(
      ['locale_id' => $defaultLocaleId],
      [
        'caption' => $title,
        'alt_text' => $subtitle,
      ],
    );

    return true;
  }

  private function seedLegacyContactFormSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $content, mixed $settings): bool
  {
    $decodedSettings = is_array($settings)
      ? $settings
      : (is_string($settings) && trim($settings) !== '' ? (json_decode($settings, true) ?: []) : []);

    if (! is_string($title) && ! is_string($content) && ! isset($decodedSettings['submit_label'], $decodedSettings['success_message'])) {
      return false;
    }

    $block->contactFormTranslations()->updateOrCreate(
      ['locale_id' => $defaultLocaleId],
      [
        'title' => $title,
        'content' => $content,
        'submit_label' => trim((string) ($decodedSettings['submit_label'] ?? '')) ?: 'Send message',
        'success_message' => trim((string) ($decodedSettings['success_message'] ?? '')) ?: config('contact.success_message'),
      ],
    );

    return true;
  }

  private function dateOrNull(mixed $value): ?Carbon
  {
    if (! is_string($value) || trim($value) === '') {
      return null;
    }

    return Carbon::parse($value);
  }
}
