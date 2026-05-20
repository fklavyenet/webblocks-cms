<?php

namespace WebBlocks\Cms\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageAsset extends Model
{
    use HasFactory;

    public const TYPE_CSS = 'css';

    public const TYPE_JS = 'js';

    public const LOAD_POSITION_HEAD = 'head';

    public const LOAD_POSITION_BODY_END = 'body_end';

    protected $fillable = [
        'page_id',
        'type',
        'path',
        'load_position',
        'is_defer',
        'is_async',
        'is_module',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_defer' => 'boolean',
            'is_async' => 'boolean',
            'is_module' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public static function allowedTypes(): array
    {
        return [self::TYPE_CSS, self::TYPE_JS];
    }

    public static function allowedLoadPositions(): array
    {
        return [self::LOAD_POSITION_HEAD, self::LOAD_POSITION_BODY_END];
    }

    public static function defaultLoadPositionFor(string $type): string
    {
        return $type === self::TYPE_JS
            ? self::LOAD_POSITION_BODY_END
            : self::LOAD_POSITION_HEAD;
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
