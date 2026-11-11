<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Note extends Model
{
  protected $table = 'wbcms_notes';

  protected $fillable = ['author_id', 'recipient_id', 'is_private', 'title', 'body'];

  protected $casts = ['is_private' => 'boolean', 'is_read' => 'boolean'];

  public function author(): BelongsTo
  {
    return $this->belongsTo(User::class, 'author_id');
  }

  public function recipient(): BelongsTo
  {
    return $this->belongsTo(User::class, 'recipient_id');
  }

  public function readers(): BelongsToMany
  {
    return $this->belongsToMany(User::class, 'wbcms_note_reads', 'note_id', 'user_id');
  }
}
