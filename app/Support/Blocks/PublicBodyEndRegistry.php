<?php

namespace App\Support\Blocks;

use Illuminate\Support\Collection;

class PublicBodyEndRegistry
{
    private const REQUEST_KEY = '_wb_public_body_end';

    public function push(?string $html): void
    {
        $html = is_string($html) ? trim($html) : '';

        if ($html === '') {
            return;
        }

        request()?->attributes->set(
            self::REQUEST_KEY,
            $this->all()->push($html)->values()->all(),
        );
    }

    public function all(): Collection
    {
        $items = request()?->attributes->get(self::REQUEST_KEY, []);

        return collect(is_array($items) ? $items : [])->filter()->values();
    }
}
