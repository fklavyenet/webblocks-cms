<?php

namespace WebBlocks\Cms\Actions\Notes;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Note;
use WebBlocks\Cms\Policies\NotePolicy;
use WebBlocks\Cms\Queries\NoteQuery;

final class ManageNote
{
  public function __construct(
    private readonly NotePolicy $policy,
    private readonly NoteQuery $notes,
  ) {}

  public function save(User $actor, array $input, int|string|null $id = null): Note
  {
    abort_unless($this->policy->access($actor), 403);

    return DB::transaction(function () use ($actor, $input, $id): Note {
      $note = $id === null ? new Note(['author_id' => $actor->id]) : $this->notes->find($actor, $id, 'update', lock: true);
      $recipientSpecified = array_key_exists('recipient_id', $input);
      $recipient = $recipientSpecified ? $input['recipient_id'] : $note->recipient_id;
      $private = match ($input['visibility'] ?? null) {
        'general' => false,
        'private' => true,
        default => $recipient !== null || $note->is_private === true,
      };

      if ($recipientSpecified && $recipient !== null) {
        $user = User::query()->lockForUpdate()->find($recipient);
        if (! $this->policy->access($user) || ! $private) {
          throw ValidationException::withMessages(['recipient_id' => __('webblocks-cms::notes.invalid_recipient')]);
        }
      }

      $note->fill(Arr::only($input, ['title', 'body']));
      $note->recipient_id = $private ? $recipient : null;
      $note->is_private = $private;
      $note->save();

      return $this->notes->details($note);
    }, 3);
  }

  public function delete(User $actor, int|string $id): void
  {
    DB::transaction(fn () => $this->notes->find($actor, $id, 'delete', lock: true)->delete(), 3);
  }

  public function readState(User $actor, int|string $id, bool $read): void
  {
    DB::transaction(function () use ($actor, $id, $read): void {
      $note = $this->notes->find($actor, $id, 'view', lock: true);
      $this->writeReadState($actor, [$note->id], $read);
    }, 3);
  }

  public function bulkReadState(User $actor, array $ids, bool $read): void
  {
    abort_unless($this->policy->access($actor), 403);

    DB::transaction(function () use ($actor, $ids, $read): void {
      $notes = Note::query()->whereKey($ids)->orderBy('id')->lockForUpdate()
        ->get(['id', 'author_id', 'recipient_id', 'is_private']);
      abort_unless($notes->count() === count($ids)
        && $notes->every(fn (Note $note) => $this->policy->view($actor, $note)), 404);

      $this->writeReadState($actor, $notes->modelKeys(), $read);
    }, 3);
  }

  private function writeReadState(User $actor, array $ids, bool $read): void
  {
    $states = DB::table('wbcms_note_reads');
    if ($read) {
      $states->upsert(
        array_map(fn ($id) => ['note_id' => $id, 'user_id' => $actor->id], $ids),
        ['note_id', 'user_id'],
        ['note_id'],
      );
    } else {
      $states->whereIn('note_id', $ids)->where('user_id', $actor->id)->delete();
    }
  }
}
