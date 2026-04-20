<?php

namespace App\Support\Sites;

use App\Models\Site;
use Illuminate\Http\Request;

class SiteResolver
{
    public function current(?Request $request = null): Site
    {
        $request ??= request();
        $host = trim(strtolower((string) $request->getHost()));

        if ($host !== '') {
            $site = Site::query()
                ->whereNotNull('domain')
                ->whereRaw('lower(domain) = ?', [$host])
                ->first();

            if ($site) {
                return $site;
            }
        }

        return $this->primary();
    }

    public function primary(): Site
    {
        return Site::query()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->firstOrFail();
    }
}
