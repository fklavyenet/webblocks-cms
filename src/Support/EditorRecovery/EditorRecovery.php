<?php

namespace WebBlocks\Cms\Support\EditorRecovery;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Policies\EditorRecoveryPolicy;
use WebBlocks\Cms\Queries\EditorRecovery\EditorRecoveryQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;

final class EditorRecovery
{
  public function __construct(private EditorRecoveryPolicy $policy, private EditorRecoveryQuery $query, private RecoveryFields $fields, private WriteEditorRecovery $write) {}

  public function context($user, Page $page, ?int $blockId, string $locale): ?Block
  {
    return $this->policy->authorizeContext($user, Page::query()->findOrFail($page->id), $blockId, $locale);
  }

  public function fingerprint(Page $page): string
  {
    $fresh = Page::query()->findOrFail($page->id);

    return SnapshotContent::hash(['identity' => ['page_id' => $fresh->id, 'site_id' => $fresh->site_id], ...app(PageRevisionManager::class)->snapshotForInspection($fresh)]);
  }

  public function form($user, Page $page, ?Block $block = null, ?string $locale = null): ?array
  {
    if (! Schema::hasTable('wbcms_editor_recoveries') || ! $page->exists || ! $this->policy->edit($user, $page)) {
      return null;
    }
    $locale ??= $page->availableSiteLocales()->firstWhere('is_default', true)?->code;
    if (! $locale || ($block && (! $block->exists || (int) $block->page_id !== (int) $page->id))) {
      return null;
    }
    if ($block && $page->availableSiteLocales()->firstWhere('is_default', true)?->code !== $locale && ! app(CmsAuthorization::class)->allows($user, 'cms.translations.update', $page->site_id)) {
      return null;
    }

    return [
      'endpoint' => route('admin.pages.recovery.show', ['page' => $page, 'editingSession' => 'SESSION']),
      'identity' => implode(':', [$user->id, $page->site_id, $page->id, $block?->id ?? 'page', $locale]),
      'block_id' => $block?->id, 'locale' => $locale,
      'base' => old('_editor_recovery_base', $this->fingerprint($page)),
      'roots' => $block ? RecoveryFields::BLOCK_FIELDS : RecoveryFields::PAGE_FIELDS,
    ];
  }

  public function current($user, Page $page, string $session, ?int $blockId, string $locale): array
  {
    $this->context($user, $page, $blockId, $locale);
    $row = $this->query->find($user, $page, $session, $blockId, $locale);
    abort_if($row && ($row->cleared || $row->expires_at <= now()->toDateTimeString()), 410);
    if (! $row) {
      return ['exists' => false];
    }
    $entries = $this->query->entries($row->id);
    app(CmsResourcePolicy::class)->authorizeFields($this->fields->input($entries), $user, $page->site_id);

    return ['exists' => true, 'conflict' => $row->base_fingerprint !== $this->fingerprint($page), 'sequence' => $row->sequence, 'saved_at' => $row->updated_at, 'expires_at_ms' => Carbon::parse($row->expires_at)->getTimestamp() * 1000, 'entries' => array_map(fn ($entry) => ['name' => $entry['name'], 'present' => $entry['value'] !== null, 'value' => $entry['value'] === null ? null : base64_encode($entry['value'])], $entries)];
  }

  public function restore(Request $request, Page $page, string $session, ?int $blockId, string $locale, bool $reviewed): string
  {
    $block = $this->context($request->user(), $page, $blockId, $locale);

    return DB::transaction(function () use ($request, $page, $session, $blockId, $locale, $reviewed, $block) {
      $this->write->authorizeLocked($request->user(), $page, $blockId, $locale);
      $row = $this->query->find($request->user(), $page, $session, $blockId, $locale, true);
      abort_unless($row && ! $row->cleared && $row->expires_at > now()->toDateTimeString(), 410);
      $base = $this->fingerprint($page);
      abort_if($row->base_fingerprint !== $base && ! $reviewed, 409, __('webblocks-cms::recovery.conflict'));
      $input = $this->fields->input($this->query->entries($row->id));
      app(CmsResourcePolicy::class)->authorizeFields($input, $request->user(), $page->site_id);
      $ticket = Str::random(64);
      DB::table('wbcms_editor_recoveries')->where('id', $row->id)->update(['base_fingerprint' => $base, 'sequence' => $row->sequence + 1, 'restore_token_hash' => hash('sha256', $ticket), 'restore_expires_at' => now()->addMinute()]);
      $parameters = ['_editor_recovery_restore' => $session, '_editor_recovery_ticket' => $ticket, '_editor_recovery_block' => $blockId];
      if ($block) {
        $slotId = PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $block->slot_type_id)->value('id');

        return $slotId ? route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $slotId, 'edit' => $block->id, 'locale' => $locale, ...$parameters]) : route('admin.blocks.edit', ['block' => $block, 'locale' => $locale, ...$parameters]);
      }

      return route('admin.pages.edit', ['page' => $page, 'tab' => 'settings', ...$parameters]);
    });
  }

  /** Apply values only to this authorized GET request, never to cross-tab session flash. */
  public function consumeRestore(Request $request, Page $page, ?int $blockId): void
  {
    $session = $request->query('_editor_recovery_restore');
    $ticket = $request->query('_editor_recovery_ticket');
    abort_unless(is_string($session) && Str::isUuid($session) && is_string($ticket) && strlen($ticket) === 64, 404);
    $locale = (string) ($request->query('locale') ?: $page->availableSiteLocales()->firstWhere('is_default', true)?->code);
    $this->context($request->user(), $page, $blockId, $locale);
    DB::transaction(function () use ($request, $page, $blockId, $session, $ticket, $locale): void {
      $this->write->authorizeLocked($request->user(), $page, $blockId, $locale);
      $row = $this->query->find($request->user(), $page, $session, $blockId, $locale, true);
      abort_unless($row && ! $row->cleared && $row->expires_at > now()->toDateTimeString() && $row->restore_expires_at > now()->toDateTimeString() && hash_equals($row->restore_token_hash ?? '', hash('sha256', $ticket)), 410);
      abort_unless(hash_equals($row->base_fingerprint, $this->fingerprint($page)), 409, __('webblocks-cms::recovery.conflict'));
      $input = $this->fields->input($this->query->entries($row->id));
      app(CmsResourcePolicy::class)->authorizeFields($input, $request->user(), $page->site_id);
      $input += ['_editor_recovery_session' => $session, '_editor_recovery_base' => $row->base_fingerprint, 'site_id' => $page->site_id];
      if ($blockId) {
        $input += ['page_id' => $page->id, 'locale' => $locale, '_slot_block_mode' => 'edit', '_slot_block_id' => $blockId];
      }
      $request->session()->now('_old_input', $input);
      $request->session()->now('status', __('webblocks-cms::recovery.restored'));
      DB::table('wbcms_editor_recoveries')->where('id', $row->id)->update(['restore_token_hash' => null, 'restore_expires_at' => null]);
    });
  }

  public function guardSave(Request $request, Page $page, ?Block $block = null): void
  {
    $session = $request->input('_editor_recovery_session', '');
    if ($session === '' || $session === null) {
      return;
    }
    abort_unless(is_string($session) && Str::isUuid($session), 422);
    $locale = (string) ($request->input('locale') ?: $page->availableSiteLocales()->firstWhere('is_default', true)?->code);
    $this->write->authorizeLocked($request->user(), $page, $block?->id, $locale, true);
    $this->context($request->user(), $page, $block?->id, $locale);
    $this->query->find($request->user(), $page, $session, $block?->id, $locale);
    $base = $request->input('_editor_recovery_base');
    if (! is_string($base) || ! hash_equals($this->fingerprint($page), $base)) {
      throw ValidationException::withMessages(['_editor_recovery_base' => __('webblocks-cms::recovery.conflict')]);
    }
  }

  public function completeSave(Request $request, Page $page, ?Block $block = null): void
  {
    $session = (string) $request->input('_editor_recovery_session', '');
    if ($session !== '') {
      $locale = (string) ($request->input('locale') ?: $page->availableSiteLocales()->firstWhere('is_default', true)?->code);
      $this->write->clear($request->user(), $page, $session, $block?->id, $locale);
    }
  }
}
