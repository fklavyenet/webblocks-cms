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
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Sitemap\SitemapCache;

final class ActivateAutomaticPageRule
{
  public function handle(AutomaticPageRule $rule, User $actor, string $digest): void
  {
    Gate::forUser($actor)->authorize('activate', $rule);
    DB::transaction(function () use ($rule, $actor, $digest): void {
      Site::query()->lockForUpdate()->findOrFail($rule->site_id);
      $rule = AutomaticPageRule::query()->lockForUpdate()->findOrFail($rule->id);
      $plan = app(AutomaticPageTransition::class)->inspect($rule, $actor);
      abort_unless($plan['errors'] === [] && hash_equals($plan['digest'], $digest), 409);
      foreach ($plan['rows'] as $row) {
        if (! $row['page']) {
          continue;
        }
        $page = Page::query()->lockForUpdate()->findOrFail($row['page']->id);
        abort_unless(app(AutomaticPageTransition::class)->hash($page) === $row['hash'], 409);
        $revision = app(PageRevisionManager::class)->capture($page, $actor, event: 'automatic_page_adopted', source: 'admin', force: true);
        $originalStatus = $page->status;
        $page->status = Page::STATUS_DRAFT;
        $page->save();
        AutomaticPageAdoption::query()->updateOrCreate(['automatic_page_rule_id' => $rule->id, 'record_key' => $row['key']], ['slug' => $row['slug'],
          'page_id' => $page->id, 'page_revision_id' => $revision->id, 'original_status' => $originalStatus,
          'retained_hash' => app(AutomaticPageTransition::class)->hash($page->fresh())]);
      }
      $rule->status = 'active';
      $rule->revision++;
      $rule->save();
      app(SitemapCache::class)->invalidateSite((int) $rule->site_id);
    });
  }
}
