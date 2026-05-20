<?php

namespace WebBlocks\Cms\Models;

use App\Models\Media;
use App\Models\MediaFolder as RootMediaFolder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    use HasFactory;

    protected $table = 'media_folders';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RootMediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(RootMediaFolder::class, 'parent_id')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    public function assets(): HasMany
    {
        return $this->media();
    }
}
