<?php

namespace App\Support\Locales;

use App\Models\Locale;
use Illuminate\Http\Request;

class LocaleResolver
{
    public function current(?Request $request = null): Locale
    {
        $request ??= request();
        $code = trim((string) $request->route('locale'));

        if ($code === '') {
            return $this->default();
        }

        return $this->enabled($code) ?? $this->default();
    }

    public function default(): Locale
    {
        return Locale::query()
            ->where('is_default', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->firstOrFail();
    }

    public function enabled(string $code): ?Locale
    {
        return Locale::query()
            ->where('code', $code)
            ->where('is_enabled', true)
            ->first();
    }

    public function resolve(string $code): ?Locale
    {
        return Locale::query()->where('code', $code)->first();
    }
}
