<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAccessRule;
use WebBlocks\Cms\Support\Memberships\MemberDirectory;
use WebBlocks\Cms\Support\Plugins\PluginServiceRegistry;

class PublicPageAccess
{
  public function allows(Page $page, Request $request): bool
  {
    if (! Schema::hasTable('wbcms_page_access_rules')) {
      return true;
    }
    $rule = PageAccessRule::query()->where('page_id', $page->id)->first();
    if (! $rule) {
      return true;
    }
    $directory = app(PluginServiceRegistry::class)->resolve('webblocks-memberships', MemberDirectory::class, '^0.1 || ^0.2');
    if (! $directory instanceof MemberDirectory) {
      return false;
    }
    $member = $directory->current($request, (int) $page->site_id);

    return $member?->verified === true && ($rule->package_id === null || $directory->hasPackage($member, $rule->package_id));
  }

  /** Public discovery never includes protected page text, even for members. */
  public function scopePublic(Builder $query): Builder
  {
    return Schema::hasTable('wbcms_page_access_rules') ? $query->whereDoesntHave('accessRule') : $query;
  }

  public function protected(Page $page): bool
  {
    return Schema::hasTable('wbcms_page_access_rules') && PageAccessRule::query()->where('page_id', $page->id)->exists();
  }

  public function exportRule(Page $page): ?array
  {
    return $this->protected($page) ? $page->accessRule()->first()?->only('package_id') : null;
  }

  public function importRule(Page $page, mixed $rule): void
  {
    if ($rule === null) {
    return;
    } // Import never loosens an existing target policy.
    if (! is_array($rule) || ! array_key_exists('package_id', $rule) || array_diff(array_keys($rule), ['package_id']) !== []) {
      throw new \RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid_access'));
    }
    if (! Schema::hasTable('wbcms_page_access_rules')) {
      throw new \RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid_access'));
    }
    // Source package IDs cannot authorize unrelated packages on another installation.
    $page->accessRule()->updateOrCreate(['page_id' => $page->id], ['package_id' => $rule['package_id'] === null ? null : 0]);
  }
}
