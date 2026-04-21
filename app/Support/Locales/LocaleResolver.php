<?php

namespace App\Support\Locales;

use App\Models\Locale;
use App\Models\Site;
use Illuminate\Http\Request;

class LocaleResolver
{
    public function current(?Request $request = null, ?Site $site = null): Locale
    {
        $request ??= request();
        $code = Locale::normalizeCode((string) $request->route('locale'));

        if ($code === null) {
            return $this->default();
        }

        return $this->enabled($code, $site) ?? $this->default();
    }

    public function default(): Locale
    {
        return Locale::query()
            ->where('is_default', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->firstOrFail();
    }

    public function enabled(string $code, ?Site $site = null): ?Locale
    {
        $code = Locale::normalizeCode($code);

        if ($code === null) {
            return null;
        }

        $query = Locale::query()
            ->where('code', $code)
            ->where('is_enabled', true);

        if ($site) {
            $query->whereHas('sites', fn ($sites) => $sites
                ->where('sites.id', $site->id)
                ->where('site_locales.is_enabled', true));
        }

        return $query->first();
    }

    public function resolve(string $code): ?Locale
    {
        $code = Locale::normalizeCode($code);

        if ($code === null) {
            return null;
        }

        return Locale::query()->where('code', $code)->first();
    }
}
