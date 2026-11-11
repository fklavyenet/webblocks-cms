<?php

namespace WebBlocks\Cms\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as SqlBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Note;
use WebBlocks\Cms\Policies\NotePolicy;

final class NoteQuery
{
  public function __construct(private readonly NotePolicy $policy) {}

  public function listing(User $actor, array $filters): LengthAwarePaginator
  {
    abort_unless($this->policy->access($actor), 403);
    $query = Note::query()->latest()->orderByDesc('id');
    $audience = $filters['audience'] ?? null;

    if ($audience === 'administration') {
      abort_unless($this->policy->administer($actor), 403);

      // The administration list exposes only references to the edit screen.
      return $query->where('is_private', true)
        ->where(fn (Builder $q) => $q->whereNull('author_id')->orWhere('author_id', '!=', $actor->id))
        ->where(fn (Builder $q) => $q->whereNull('recipient_id')->orWhere('recipient_id', '!=', $actor->id))
        ->paginate($filters['per_page'] ?? 20, ['id'], 'page', $filters['page'] ?? 1);
    }

    $read = fn (Builder $q) => $q->where('wbcms_note_reads.user_id', $actor->id);
    $query->select(['id', 'title', 'author_id', 'recipient_id', 'is_private', 'created_at', 'updated_at'])
      ->where(fn (Builder $q) => $q->where('is_private', false)->orWhere('author_id', $actor->id)->orWhere('recipient_id', $actor->id))
      ->with(['author:id,name', 'recipient:id,name'])
      ->withExists(['readers as is_read' => $read]);

    if ($audience !== null) {
      $query->where('is_private', $audience === 'private');
    }
    if (isset($filters['search'])) {
      $search = strtr($filters['search'], ['!' => '!!', '%' => '!%', '_' => '!_']);
      $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.$search.'%']);
    }
    if (isset($filters['status'])) {
      $query->has('readers', $filters['status'] === 'read' ? '>=' : '<', 1, 'and', $read);
    }

    return $query->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
  }

  public function find(User $actor, int|string $id, string $ability, bool $lock = false): Note
  {
    abort_unless($this->policy->access($actor), 403);
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    abort_if($id === false, 404);
    $note = Note::query()->when($lock, fn (Builder $q) => $q->lockForUpdate())->findOrFail($id);
    abort_unless($this->policy->{$ability}($actor, $note), 404);

    return $note;
  }

  public function details(Note $note, ?User $reader = null): Note
  {
    $note->load(['author:id,name', 'recipient:id,name']);
    if ($reader !== null) {
      $note->loadExists(['readers as is_read' => fn (Builder $q) => $q->where('wbcms_note_reads.user_id', $reader->id)]);
    }

    return $note;
  }

  public function recipients(): Collection
  {
    return User::query()->where('is_active', true)
      ->where(function (Builder $users): void {
        $users->whereExists(fn (SqlBuilder $q) => $q->selectRaw('1')
          ->from('wbcms_acl_system_administrators')->whereColumn('user_id', 'users.id'))
          ->orWhereExists(fn (SqlBuilder $q) => $q->selectRaw('1')->from('wbcms_site_user as membership')
            ->join('wbcms_acl_role_permissions as permission', 'permission.role_id', '=', 'membership.role_id')
            ->where('permission.permission', 'cms.panel.access')->whereColumn('membership.user_id', 'users.id'));
      })
      ->orderBy('name')->orderBy('id')->get(['id', 'name']);
  }
}
