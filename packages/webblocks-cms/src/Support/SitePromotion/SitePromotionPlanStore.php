<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SitePromotionPlanStore
{
    public const DISK = 'site-promotions';

    public function save(SitePromotionPlan $plan): SitePromotionPlan
    {
        Storage::disk(self::DISK)->put($this->pathFor($plan->token), json_encode($plan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $plan;
    }

    public function load(?string $token): ?SitePromotionPlan
    {
        $token = trim((string) $token);

        if ($token === '') {
            return null;
        }

        $path = $this->pathFor($token);

        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk(self::DISK)->get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Stored site promotion plan is invalid.');
        }

        return SitePromotionPlan::fromArray($decoded);
    }

    public function newToken(): string
    {
        return Str::lower(Str::random(24));
    }

    private function pathFor(string $token): string
    {
        return 'plans/'.$token.'.json';
    }
}
