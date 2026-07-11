<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Support\Media\MediaUsageResolver;

class Media extends CmsModel
{
  use HasFactory;

  protected $table = 'media';

  public const KIND_IMAGE = 'image';

  public const KIND_VIDEO = 'video';

  public const KIND_DOCUMENT = 'document';

  public const KIND_OTHER = 'other';

  protected $fillable = [
    'folder_id',
    'disk',
    'path',
    'filename',
    'original_name',
    'extension',
    'mime_type',
    'size',
    'kind',
    'visibility',
    'title',
    'alt_text',
    'caption',
    'description',
    'width',
    'height',
    'duration',
    'focal_point_x',
    'focal_point_y',
    'uploaded_by',
  ];

  public function folder(): BelongsTo
  {
    return $this->belongsTo(MediaFolder::class, 'folder_id');
  }

  public function uploader(): BelongsTo
  {
    return $this->belongsTo(User::class, 'uploaded_by');
  }

  public function blocks(): HasMany
  {
    return $this->hasMany(Block::class, 'media_id');
  }

  public function blockMedia(): HasMany
  {
    return $this->hasMany(BlockMedia::class, 'media_id');
  }

  public function blockAssets(): HasMany
  {
    return $this->blockMedia();
  }

  public function sitesUsingAsFavicon(): HasMany
  {
    return $this->hasMany(Site::class, 'favicon_media_id');
  }

  public function sitesUsingAsSocialImage(): HasMany
  {
    return $this->hasMany(Site::class, 'social_image_media_id');
  }

  public function pageTranslationsUsingAsOgImage(): HasMany
  {
    return $this->hasMany(PageTranslation::class, 'og_image_media_id');
  }

  public function url(): ?string
  {
    if ($this->visibility !== 'public') {
      return null;
    }

    /** @var FilesystemAdapter $disk */
    $disk = Storage::disk($this->disk);

    return $disk->url($this->path);
  }

  public function isImage(): bool
  {
    return $this->kind === self::KIND_IMAGE;
  }

  public function transformUrl(string $variant): ?string
  {
    return app(MediaTransformService::class)->url($this, $variant);
  }

  public function isVideo(): bool
  {
    return $this->kind === self::KIND_VIDEO;
  }

  public function isDocument(): bool
  {
    return $this->kind === self::KIND_DOCUMENT;
  }

  public function canPreview(): bool
  {
    return $this->isImage();
  }

  public function humanSize(): string
  {
    if ($this->size === null) {
      return '-';
    }

    if ($this->size < 1024) {
      return $this->size.' B';
    }

    if ($this->size < 1048576) {
      return number_format($this->size / 1024, 1).' KB';
    }

    return number_format($this->size / 1048576, 1).' MB';
  }

  public function displayTitle(): string
  {
    return $this->title ?: $this->filename;
  }

  public function thumbnailLabel(): string
  {
    return $this->alt_text ?: $this->displayTitle();
  }

  public function previewIconClass(): string
  {
    return match ($this->kind) {
      self::KIND_IMAGE => 'wb-icon-image',
      self::KIND_VIDEO => 'wb-icon-video',
      self::KIND_DOCUMENT => 'wb-icon-file-text',
      default => 'wb-icon-file',
    };
  }

  public function compactTypeLabel(): string
  {
    if (is_string($this->extension) && trim($this->extension) !== '') {
      return Str::upper($this->extension);
    }

    return match ($this->kind) {
      self::KIND_IMAGE => 'Image',
      self::KIND_VIDEO => 'Video',
      self::KIND_DOCUMENT => 'Doc',
      default => 'File',
    };
  }

  public function compactMetaLabel(): string
  {
    $parts = [
      $this->compactTypeLabel(),
      $this->humanSize(),
    ];

    if ($this->width && $this->height) {
      $parts[] = $this->width.'x'.$this->height;
    }

    return collect($parts)
      ->filter(fn (?string $part) => $part !== null && $part !== '' && $part !== '-')
      ->implode(' • ');
  }

  public function pickerPayload(): array
  {
    return [
      'id' => $this->id,
      'title' => $this->title,
      'filename' => $this->filename,
      'original_name' => $this->original_name,
      'kind' => $this->kind,
      'folder_name' => $this->folder?->name,
      'meta_label' => $this->compactMetaLabel(),
      'url' => $this->isImage() ? $this->transformUrl('thumbnail') : $this->url(),
      'previewable' => $this->canPreview(),
    ];
  }

  public function usages(): Collection
  {
    return app(MediaUsageResolver::class)->resolve($this);
  }

  public function usageCount(): int
  {
    return $this->usages()->count();
  }

  public function isUsed(): bool
  {
    return $this->usageCount() > 0;
  }
}
