<?php

namespace WebBlocks\Cms\Actions\SystemPages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemPage;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\SystemPages\SystemPageBlocks;
use WebBlocks\Cms\Support\SystemPages\SystemPageDefinition;

final class PrepareSystemPage
{
  public function execute(Site $site, SystemPageDefinition $definition, User $user): Page
  {
    return DB::transaction(function () use ($site, $definition, $user): Page {
      Site::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();
      $binding = SystemPage::query()->where('site_id', $site->id)->where('key', $definition->key)->first();
      if ($binding) {
        return $binding->page;
      }
      $page = Page::create([
        'site_id' => $site->id, 'page_type' => Page::TYPE_SYSTEM, 'status' => Page::STATUS_DRAFT,
        'settings' => ['public_shell' => 'default'],
        'created_by_user_id' => $user->id, 'updated_by_user_id' => $user->id,
      ]);
      $slug = 'system-'.Str::slug($definition->key).'-'.$page->id;
      foreach (app(LocaleResolver::class)->enabledForSite($site) as $locale) {
        $page->translations()->create([
          'site_id' => $site->id, 'locale_id' => $locale->id,
          'name' => $definition->label($locale->code), 'slug' => $slug, 'path' => '/'.$slug,
        ]);
      }
      app(PageLayoutSlotSyncer::class)->seedInitialSlots($page, 'default');
      SystemPage::create(['site_id' => $site->id, 'key' => $definition->key, 'page_id' => $page->id]);
      app(SystemPageBlocks::class)->add($page, $definition);
      app(PageRevisionManager::class)->capture($page->fresh(), $user, 'System page prepared', 'A draft system page design was prepared.', event: 'page_created');

      return $page;
    });
  }
}
