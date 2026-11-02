<?php

namespace WebBlocks\Cms\Support\EditorRecovery;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Policies\EditorFormRecoveryPolicy;
use WebBlocks\Cms\Queries\EditorRecovery\EditorRecoveryQuery;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

final class EditorFormRecovery
{
  public function __construct(private EditorFormRecoveryPolicy $policy, private EditorRecoveryQuery $query, private RecoveryFields $fields, private WriteEditorRecovery $write) {}

  public function context($user, array $data): RecoveryFormContext
  {
    return $this->policy->context($user, $data);
  }

  public function form($user, array $data): ?array
  {
    if (! Schema::hasColumn('wbcms_editor_recoveries', 'form_kind')) {
      return null;
    }
    if (! empty($data['page_id'])) {
      $page = Page::query()->find($data['page_id']);
      if ($page && ($page->isSharedSlotSourcePage() || $page->isRevisionRestoreCandidate())) {
      return null;
      }
    }
    try {
      $context = $this->context($user, $data);
    } catch (HttpExceptionInterface|ModelNotFoundException) {
      if (! in_array($data['form_kind'] ?? null, ['new-page', 'new-block'], true)) {
      return null;
      }

      return ['endpoint' => route('admin.editor-forms.recovery.show', ['editingSession' => 'SESSION']), 'identity' => $user->id.':'.$data['form_kind'], 'block_id' => null, 'locale' => $data['locale'], 'base' => '', 'roots' => $data['form_kind'] === 'new-page' ? RecoveryFields::PAGE_FIELDS : RecoveryFields::BLOCK_FIELDS, 'editor_url' => route('admin.pages.index'), 'context' => array_filter($data, fn ($value) => $value !== null), 'context_fields' => $data['form_kind'] === 'new-page' ? 'site_id' : 'page_id block_type_id slot_type_id parent_id'];
    }

    return ['endpoint' => route('admin.editor-forms.recovery.show', ['editingSession' => 'SESSION']),
      'identity' => $user->id.':'.$context->kind, 'block_id' => null, 'locale' => $context->locale->code,
      'base' => old('_editor_recovery_base', ''), 'roots' => $context->fields(), 'editor_url' => $context->page ? route('admin.pages.edit', $context->page) : route('admin.pages.index', ['site' => $context->site->id]),
      'context' => $context->transport(), 'context_fields' => match ($context->kind) {
        'new-page' => 'site_id', 'new-block' => 'page_id block_type_id slot_type_id parent_id', default => '',
      }];
  }

  public function authorizeEntries($user, RecoveryFormContext $context, array $entries): void
  {
    $input = $this->fields->input($entries);
    app(CmsResourcePolicy::class)->authorizeFields($input, $user, $context->site->id);
    if ($context->kind === 'new-block') {
      foreach (['block_type_id' => $context->type->id, 'slot_type_id' => $context->slot->id, 'parent_id' => $context->parent?->id] as $key => $value) {
        if (array_key_exists($key, $input)) {
          abort_unless((int) $input[$key] === (int) $value, 422);
        }
      }
    }
  }

  public function current($user, RecoveryFormContext $context, string $session): array
  {
    $row = $this->query->findForm($user, $context, $session);
    abort_if($row && $row->expires_at <= now()->toDateTimeString(), 410);
    $base = $context->fingerprint();
    if (! $row) {
    return ['exists' => false, 'base_fingerprint' => $base];
    }
    if ($row->cleared) {
      return ['exists' => false, 'completed' => true, 'redirect' => $this->completedUrl($user, $context, $row)];
    }
    $entries = $this->query->entries($row->id);
    $this->authorizeEntries($user, $context, $entries);

    return ['exists' => true, 'base_fingerprint' => $row->base_fingerprint, 'conflict' => ! hash_equals($row->base_fingerprint, $base),
      'sequence' => $row->sequence, 'saved_at' => $row->updated_at, 'expires_at_ms' => Carbon::parse($row->expires_at)->getTimestamp() * 1000,
      'entries' => array_map(fn ($entry) => ['name' => $entry['name'], 'present' => $entry['value'] !== null, 'value' => $entry['value'] === null ? null : base64_encode($entry['value'])], $entries)];
  }

  private function completedUrl($user, RecoveryFormContext $context, object $row): string
  {
    abort_unless($row->completed_page_id, 410);
    $page = Page::query()->findOrFail($row->completed_page_id);
    abort_unless((int) $page->site_id === (int) $context->site->id, 404);
    abort_unless(app(CmsAuthorization::class)->allows($user, 'cms.pages.read', $page->site_id), 403);
    if ($row->completed_block_id) {
      $block = Block::query()->where('page_id', $page->id)->findOrFail($row->completed_block_id);

      return route('admin.blocks.edit', ['block' => $block, 'locale' => $context->locale->code]);
    }
    if ($row->completed_translation_id) {
      $translation = PageTranslation::query()->where('page_id', $page->id)->where('locale_id', $context->locale->id)->findOrFail($row->completed_translation_id);

      return route('admin.pages.translations.edit', [$page, $translation]);
    }

    return route('admin.pages.edit', $page);
  }

  public function restore(Request $request, RecoveryFormContext $context, string $session, bool $reviewed): string
  {
    return DB::transaction(function () use ($request, $context, $session, $reviewed) {
      $context = $this->write->lockForm($request->user(), $context);
      $row = $this->query->findForm($request->user(), $context, $session, true);
      abort_unless($row && ! $row->cleared && $row->expires_at > now()->toDateTimeString(), 410);
      $base = $context->fingerprint();
      abort_if(! hash_equals($row->base_fingerprint, $base) && ! $reviewed, 409);
      $this->authorizeEntries($request->user()->fresh(), $context, $this->query->entries($row->id));
      $ticket = Str::random(64);
      DB::table('wbcms_editor_recoveries')->where('id', $row->id)->update(['base_fingerprint' => $base, 'sequence' => $row->sequence + 1, 'restore_token_hash' => hash('sha256', $ticket), 'restore_expires_at' => now()->addMinute()]);
      $url = $context->editorUrl();

      return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['_editor_form_restore' => $session, '_editor_recovery_ticket' => $ticket]);
    });
  }

  public function consumeRestore(Request $request): void
  {
    $session = $request->query('_editor_form_restore');
    $ticket = $request->query('_editor_recovery_ticket');
    abort_unless(is_string($session) && Str::isUuid($session) && is_string($ticket) && strlen($ticket) === 64, 404);
    $row = DB::table('wbcms_editor_recoveries')->where('user_id', $request->user()->id)->where('editing_session', $session)->whereNotNull('form_kind')->first();
    abort_unless($row, 410);
    $context = $this->context($request->user(), ['form_kind' => $row->form_kind, 'site_id' => $row->site_id, 'page_id' => $row->page_id, 'locale' => $row->locale,
      'block_type_id' => $row->block_type_id, 'slot_type_id' => $row->slot_type_id, 'parent_id' => $row->parent_block_id, 'translation_id' => $row->translation_id]);
    $url = $context->editorUrl();
    abort_unless(parse_url($url, PHP_URL_PATH) === $request->getPathInfo(), 404);
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
    foreach ($query as $key => $value) {
    abort_unless((string) $request->query($key) === (string) $value, 404);
    }
    DB::transaction(function () use ($request, $context, $session, $ticket): void {
      $context = $this->write->lockForm($request->user(), $context);
      $row = $this->query->findForm($request->user(), $context, $session, true);
      abort_unless($row && ! $row->cleared && $row->expires_at > now()->toDateTimeString() && $row->restore_expires_at > now()->toDateTimeString()
        && hash_equals($row->restore_token_hash ?? '', hash('sha256', $ticket)), 410);
      abort_unless(hash_equals($row->base_fingerprint, $context->fingerprint()), 409);
      $entries = $this->query->entries($row->id);
      $this->authorizeEntries($request->user()->fresh(), $context, $entries);
      $input = $this->fields->input($entries);
      $input += ['site_id' => $context->site->id, 'page_id' => $context->page?->id, 'locale' => $context->locale->code,
        '_editor_recovery_session' => $session, '_editor_recovery_base' => $row->base_fingerprint, '_editor_form_kind' => $context->kind];
      if ($context->kind === 'new-block') {
      $input += ['_slot_block_mode' => 'create', 'block_type_id' => $context->type->id, 'slot_type_id' => $context->slot->id, 'parent_id' => $context->parent?->id];
      }
      $request->session()->now('_old_input', $input);
      $request->session()->now('status', __('webblocks-cms::recovery.restored'));
      DB::table('wbcms_editor_recoveries')->where('id', $row->id)->update(['restore_token_hash' => null, 'restore_expires_at' => null]);
    });
  }

  public function guardSave(Request $request, string $kind, array $identity): ?RecoveryFormContext
  {
    $session = $request->input('_editor_recovery_session');
    if (! $session) {
    return null;
    }
    abort_unless(is_string($session) && Str::isUuid($session) && $request->input('_editor_form_kind') === $kind, 422);
    $context = $this->context($request->user(), ['form_kind' => $kind, ...$identity]);
    $context = $this->write->lockForm($request->user(), $context, true);
    app(CmsResourcePolicy::class)->authorizeFields($request->all(), $request->user()->fresh(), $context->site->id);
    $row = $this->query->findForm($request->user(), $context, $session, true);
    abort_unless($row && ! $row->cleared && $row->expires_at > now()->toDateTimeString(), 410);
    $base = $request->input('_editor_recovery_base');
    if (! is_string($base) || ! hash_equals($base, $row->base_fingerprint) || ! hash_equals($base, $context->fingerprint())) {
      throw ValidationException::withMessages(['_editor_recovery_base' => __('webblocks-cms::recovery.conflict')]);
    }

    return $context;
  }

  public function completeSave(Request $request, ?RecoveryFormContext $context, Page $page, ?Block $block = null, ?PageTranslation $translation = null): void
  {
    if ($context) {
    $this->write->clearForm($request->user(), $context, $request->input('_editor_recovery_session'),
      ['completed_page_id' => $page->id, 'completed_block_id' => $block?->id, 'completed_translation_id' => $translation?->id]);
    }
  }
}
