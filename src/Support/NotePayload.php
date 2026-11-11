<?php

namespace WebBlocks\Cms\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use WebBlocks\Cms\Models\Note;

final class NotePayload
{
  public static function listing(LengthAwarePaginator $notes, bool $administration): array
  {
    return [
      'notes' => $notes->getCollection()->map(fn (Note $note) => $administration
        ? ['id' => $note->id, 'edit_only' => true]
        : self::record($note))->all(),
      'pagination' => [
        'page' => $notes->currentPage(),
        'per_page' => $notes->perPage(),
        'total' => $notes->total(),
        'last_page' => $notes->lastPage(),
      ],
    ];
  }

  public static function record(Note $note, bool $includeBody = false, bool $editContext = false): array
  {
    $result = [
      'id' => $note->id,
      'title' => $note->title,
      'visibility' => $note->is_private ? 'private' : 'general',
      'author' => $note->author?->only(['id', 'name']),
      'recipient' => $note->recipient?->only(['id', 'name']),
      'created_at' => $note->created_at?->toISOString(),
      'updated_at' => $note->updated_at?->toISOString(),
    ];
    if ($includeBody) {
      $result['body'] = $note->body;
    }
    if (! $editContext) {
      $result['read'] = $note->is_read;
    }

    return $result;
  }
}
