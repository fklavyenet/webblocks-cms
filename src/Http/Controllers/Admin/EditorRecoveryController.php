<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Http\Requests\Admin\EditorRecoveryRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Support\EditorRecovery\EditorRecovery;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;

class EditorRecoveryController extends Controller
{
  public function __construct(private EditorRecovery $recovery, private WriteEditorRecovery $write, private RecoveryFields $fields) {}

  public function show(EditorRecoveryRequest $request, Page $page, string $editingSession)
  {
    return response()->json($this->recovery->current($request->user(), $page, $editingSession, $request->integer('block_id') ?: null, $request->validated('locale')))->header('Cache-Control', 'private, no-store');
  }

  public function store(EditorRecoveryRequest $request, Page $page, string $editingSession)
  {
    $blockId = $request->integer('block_id') ?: null;
    $locale = $request->validated('locale');
    $this->recovery->context($request->user(), $page, $blockId, $locale);
    abort_unless($request->validated('_checkpoint') === '1', 422);
    $entries = $this->fields->decode($request->validated('entries'), $blockId !== null);
    app(CmsResourcePolicy::class)->authorizeFields($this->fields->input($entries), $request->user(), $page->site_id);
    $row = $this->write->checkpoint($request->user(), $page, $editingSession, $blockId, $locale, $request->validated(), $entries);

    return response()->json(['sequence' => $row->sequence, 'saved_at' => $row->updated_at, 'expires_at_ms' => Carbon::parse($row->expires_at)->getTimestamp() * 1000])->header('Cache-Control', 'private, no-store');
  }

  public function destroy(EditorRecoveryRequest $request, Page $page, string $editingSession)
  {
    $blockId = $request->integer('block_id') ?: null;
    $this->recovery->context($request->user(), $page, $blockId, $request->validated('locale'));
    $this->write->clear($request->user(), $page, $editingSession, $blockId, $request->validated('locale'));

    return response()->json(['cleared' => true])->header('Cache-Control', 'private, no-store');
  }

  public function restore(EditorRecoveryRequest $request, Page $page, string $editingSession)
  {
    return response()->json(['redirect' => $this->recovery->restore($request, $page, $editingSession, $request->integer('block_id') ?: null, $request->validated('locale'), $request->boolean('reviewed_conflict'))])->header('Cache-Control', 'private, no-store');
  }
}
