<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Actions\Notes\ManageNote;
use WebBlocks\Cms\Http\Requests\NoteRequest;
use WebBlocks\Cms\Models\Note;
use WebBlocks\Cms\Policies\NotePolicy;
use WebBlocks\Cms\Queries\NoteQuery;

final class NoteController
{
  public function __construct(
    private readonly NotePolicy $policy,
    private readonly NoteQuery $notes,
    private readonly ManageNote $actions,
  ) {}

  public function index(NoteRequest $request): Response
  {
    return $this->listing($request);
  }

  public function show(NoteRequest $request, string $note): Response
  {
    $actor = $request->user();
    $record = $this->notes->details($this->notes->find($actor, $note, 'view'));

    return $this->listing($request, reading: $record);
  }

  public function open(Request $request, string $note): RedirectResponse
  {
    $this->actions->readState($request->user(), $note, true);

    return redirect()->route('admin.notes.show', ['note' => $note] + $request->query(), 303);
  }

  public function edit(NoteRequest $request, string $note): Response
  {
    return $this->listing($request, $this->notes->find($request->user(), $note, 'update'));
  }

  public function store(NoteRequest $request): RedirectResponse
  {
    return $this->save($request);
  }

  public function update(NoteRequest $request, string $note): RedirectResponse
  {
    return $this->save($request, $note);
  }

  public function destroy(Request $request, string $note): RedirectResponse
  {
    $this->actions->delete($request->user(), $note);

    return redirect()->route('admin.notes.index', $request->query())->with('note_status', 'deleted');
  }

  public function readState(NoteRequest $request, string $note): RedirectResponse
  {
    $read = (bool) $request->validated('read');
    $this->actions->readState($request->user(), $note, $read);

    return redirect()->route('admin.notes.show', ['note' => $note] + $request->query())
      ->with('note_status', $read ? 'marked_read' : 'marked_unread');
  }

  public function bulkReadState(NoteRequest $request): RedirectResponse
  {
    $read = (bool) $request->validated('read');
    $this->actions->bulkReadState($request->user(), $request->validated('note_ids'), $read);

    return redirect()->route('admin.notes.index', $request->query())
      ->with('note_status', $read ? 'bulk_marked_read' : 'bulk_marked_unread');
  }

  private function listing(NoteRequest $request, ?Note $editing = null, ?Note $reading = null): Response
  {
    $actor = $request->user();
    $filters = $request->validated();
    $notes = $this->notes->listing($actor, $filters);
    if ($notes->currentPage() > $notes->lastPage()) {
      $filters['page'] = $notes->lastPage();
      $notes = $this->notes->listing($actor, $filters);
    }
    $notes->appends($filters);
    $administer = $this->policy->administer($actor);
    $administration = ($filters['audience'] ?? null) === 'administration';
    $recipients = $this->notes->recipients();
    $permissions = $notes->getCollection()->mapWithKeys(fn (Note $note) => [
      $note->id => $administration
        ? ['view' => false, 'update' => true, 'delete' => false]
        : $this->permissions($actor, $note),
    ]);

    return response()->view('webblocks-cms::admin.notes.index', compact(
      'notes', 'filters', 'administer', 'administration', 'recipients', 'editing', 'reading', 'permissions',
    ))->header('Cache-Control', 'private, no-store');
  }

  private function permissions(User $actor, Note $note): array
  {
    return [
      'view' => $this->policy->view($actor, $note),
      'update' => $this->policy->update($actor, $note),
      'delete' => $this->policy->delete($actor, $note),
    ];
  }

  private function save(NoteRequest $request, ?string $note = null): RedirectResponse
  {
    try {
      $this->actions->save($request->user(), $request->validated(), $note);
    } catch (ValidationException $exception) {
      $exception->errorBag('note')->redirectTo($note === null
        ? route('admin.notes.index', $request->query())
        : route('admin.notes.edit', ['note' => $note] + $request->query()));
      throw $exception;
    }

    return redirect()->route('admin.notes.index', $request->query())->with('note_status', 'saved');
  }
}
