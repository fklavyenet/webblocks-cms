<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebBlocks\Cms\Actions\Notes\ManageNote;
use WebBlocks\Cms\Http\Requests\NoteRequest;
use WebBlocks\Cms\Policies\NotePolicy;
use WebBlocks\Cms\Queries\NoteQuery;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;
use WebBlocks\Cms\Support\NotePayload;

final class InternalNoteController
{
  public function __construct(
    private readonly NotePolicy $policy,
    private readonly NoteQuery $notes,
    private readonly ManageNote $actions,
    private readonly InternalApiResponseMetadata $metadata,
  ) {}

  public function index(NoteRequest $request): JsonResponse
  {
    $filters = $request->validated();

    return $this->ok(NotePayload::listing($this->notes->listing($this->actor($request), $filters),
      ($filters['audience'] ?? null) === 'administration'));
  }

  public function recipients(Request $request): JsonResponse
  {
    $this->actor($request);

    return $this->ok(['recipients' => $this->notes->recipients()->map->only(['id', 'name'])->all()]);
  }

  public function show(Request $request, string $note): JsonResponse
  {
    $actor = $this->actor($request);
    $record = $this->notes->details($this->notes->find($actor, $note, 'view'), $actor);

    return $this->ok(['note' => NotePayload::record($record, includeBody: true)]);
  }

  public function edit(Request $request, string $note): JsonResponse
  {
    $actor = $this->actor($request);
    $record = $this->notes->details($this->notes->find($actor, $note, 'update'));

    return $this->ok(['note' => NotePayload::record($record, includeBody: true, editContext: true)]);
  }

  public function store(NoteRequest $request): JsonResponse
  {
    $record = $this->actions->save($this->actor($request), $request->validated());

    return $this->ok(['note' => NotePayload::record($record, editContext: true)], 201);
  }

  public function update(NoteRequest $request, string $note): JsonResponse
  {
    $record = $this->actions->save($this->actor($request), $request->validated(), $note);

    return $this->ok(['note' => NotePayload::record($record, editContext: true)]);
  }

  public function destroy(Request $request, string $note): JsonResponse
  {
    $this->actions->delete($this->actor($request), $note);

    return $this->ok(['deleted' => true]);
  }

  public function readState(NoteRequest $request, string $note): JsonResponse
  {
    $read = (bool) $request->validated('read');
    $this->actions->readState($this->actor($request), $note, $read);

    return $this->ok(['id' => (int) $note, 'read' => $read]);
  }

  private function actor(Request $request): User
  {
    $actor = $request->attributes->get('cms_api_user');
    abort_unless($actor instanceof User && $this->policy->access($actor), 403);

    return $actor;
  }

  private function ok(array $data, int $status = 200): JsonResponse
  {
    return response()->json($this->metadata->merge(['ok' => true] + $data), $status, ['Cache-Control' => 'private, no-store']);
  }
}
