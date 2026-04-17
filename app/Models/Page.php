<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Page extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if (! $page->page_type) {
                $page->page_type = 'default';
            }
        });

        static::created(function (self $page): void {
            if (app()->runningUnitTests()) {
                return;
            }

            $route = request()?->route();

            Log::info('Page created', [
                'page_id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status,
                'page_type' => $page->page_type,
                'user_id' => Auth::id(),
                'route' => $route?->getName(),
                'method' => request()?->method(),
                'url' => request()?->fullUrl(),
                'referrer' => request()?->headers->get('referer'),
                'ip' => request()?->ip(),
                'console' => app()->runningInConsole(),
                'command' => $_SERVER['argv'][1] ?? null,
            ]);
        });
    }

    protected $fillable = [
        'title',
        'slug',
        'page_type',
        'status',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('sort_order');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PageSlot::class)->orderBy('sort_order');
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function publicUrl(): string
    {
        return route('pages.show', $this->slug);
    }

    public function publicPath(): string
    {
        return route('pages.show', $this->slug, false);
    }
}
