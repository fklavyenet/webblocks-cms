<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteVariable extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'site_id',
    'key',
    'label',
    'value',
    'sort_order',
    'is_enabled',
  ];

  protected function casts(): array
  {
    return [
      'is_enabled' => 'boolean',
      'sort_order' => 'integer',
    ];
  }

  protected static function booted(): void
  {
    static::saving(function (self $siteVariable): void {
      $siteVariable->key = str((string) $siteVariable->key)
        ->trim()
        ->snake()
        ->replace('-', '_')
        ->lower()
        ->toString();

      $siteVariable->label = static::normalizeNullableString($siteVariable->label);
      $siteVariable->value = static::normalizeNullableString($siteVariable->value, preserveLineBreaks: true);
    });
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function displayLabel(): string
  {
    return trim((string) $this->label) !== ''
          ? (string) $this->label
          : str($this->key)->replace('_', ' ')->headline()->toString();
  }

  private static function normalizeNullableString(mixed $value, bool $preserveLineBreaks = false): ?string
  {
    $value = (string) ($value ?? '');
    $value = $preserveLineBreaks
          ? preg_replace("/\r\n?|\n/u", "\n", $value)
          : trim($value);

    if (! is_string($value)) {
      return null;
    }

    $value = $preserveLineBreaks ? trim($value) : $value;

    return $value !== '' ? $value : null;
  }
}
