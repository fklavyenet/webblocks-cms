<?php

namespace WebBlocks\Cms\Actions\AutomaticPages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ContentSources\AutomaticPageTransition;
use WebBlocks\Cms\Support\Sitemap\SitemapCache;

final class PauseAutomaticPageRule
{
  public function handle(AutomaticPageRule $rule, User $actor, int $revision): void
  {
    Gate::forUser($actor)->authorize('activate', $rule);
    DB::transaction(function () use ($rule, $revision): void {
      Site::query()->lockForUpdate()->findOrFail($rule->site_id);
      $rule = AutomaticPageRule::query()->lockForUpdate()->findOrFail($rule->id);
      abort_unless($rule->status === 'active' && $rule->revision === $revision, 409);
      $adoptions = AutomaticPageAdoption::query()->where('automatic_page_rule_id', $rule->id)->lockForUpdate()->get();
      foreach ($adoptions as $adoption) {
        $page = Page::query()->lockForUpdate()->where('site_id', $rule->site_id)->findOrFail($adoption->page_id);
        abort_unless(hash_equals($adoption->retained_hash, app(AutomaticPageTransition::class)->hash($page)), 409);
      }
      foreach ($adoptions as $adoption) {
        $page = Page::query()->findOrFail($adoption->page_id);
        $page->status = $adoption->original_status;
        $page->save();
      }
      $rule->status = 'paused';
      $rule->revision++;
      $rule->save();
      app(SitemapCache::class)->invalidateSite((int) $rule->site_id);
    });
  }
}
