<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use WebBlocks\Cms\Actions\EditorRecovery\WriteEditorRecovery;
use WebBlocks\Cms\Http\Requests\Admin\EditorFormRecoveryRequest;
use WebBlocks\Cms\Support\EditorRecovery\EditorFormRecovery;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;

class EditorFormRecoveryController extends Controller
{
  public function __construct(private EditorFormRecovery $recovery, private WriteEditorRecovery $write, private RecoveryFields $fields) {}

  public function show(EditorFormRecoveryRequest $request, string $editingSession)
  {
    $context = $this->recovery->context($request->user(), $request->validated());

    return response()->json([...$this->recovery->current($request->user(), $context, $editingSession), 'csrf_token' => $request->session()->token()])->header('Cache-Control', 'private, no-store');
  }

  public function store(EditorFormRecoveryRequest $request, string $editingSession)
  {
    $context = $this->recovery->context($request->user(), $request->validated());
    abort_unless($request->validated('_checkpoint') === '1', 422);
    $entries = $this->fields->decode($request->validated('entries'), $context->fields());
    $this->recovery->authorizeEntries($request->user(), $context, $entries);
    $row = $this->write->checkpointForm($request->user(), $context, $editingSession, $request->validated(), $entries);

    return response()->json(['sequence' => $row->sequence, 'saved_at' => $row->updated_at, 'expires_at_ms' => Carbon::parse($row->expires_at)->getTimestamp() * 1000])->header('Cache-Control', 'private, no-store');
  }

  public function destroy(EditorFormRecoveryRequest $request, string $editingSession)
  {
    $this->write->clearForm($request->user(), $this->recovery->context($request->user(), $request->validated()), $editingSession);

    return response()->json(['cleared' => true])->header('Cache-Control', 'private, no-store');
  }

  public function restore(EditorFormRecoveryRequest $request, string $editingSession)
  {
    return response()->json(['redirect' => $this->recovery->restore($request, $this->recovery->context($request->user(), $request->validated()), $editingSession, $request->boolean('reviewed_conflict'))])->header('Cache-Control', 'private, no-store');
  }
}
