<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use WebBlocks\Cms\Models\SitePromotionRecord;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

class SitePromotionPlanStore
{
  public const DISK = 'site-promotions';

  public function save(SitePromotionPlan $plan): SitePromotionPlan
  {
    if (! app(PersistentDocuments::class)->available()) {
      PersistentSchema::ensure();
    }
    SitePromotionRecord::updateOrCreate(['token' => $plan->token], ['plan' => $plan->toArray()]);

    return $plan;
  }

  public function load(?string $token): ?SitePromotionPlan
  {
    $token = trim((string) $token);

    if ($token === '') {
      return null;
    }

    if (Schema::hasTable('wbcms_site_promotion_plans')) {
      $record = SitePromotionRecord::where('token', $token)->first();
      if ($record !== null) {
        return SitePromotionPlan::fromArray($record->plan);
      }
    }
    if (! config('filesystems.disks.'.self::DISK.'.driver')) {
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
