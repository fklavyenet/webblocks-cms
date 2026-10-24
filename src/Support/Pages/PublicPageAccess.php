<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAccessRule;
use WebBlocks\Cms\Support\Memberships\MemberDirectory;
use WebBlocks\Cms\Support\Plugins\PluginServiceRegistry;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

class PublicPageAccess
{
  public function allows(Page $page, Request $request): bool
  {
    $rule = $this->ruleFor($page);
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
    return $this->available() ? $query->whereDoesntHave('accessRule') : $query;
  }

  public function protected(Page $page): bool
  {
    return $this->ruleFor($page) !== null;
  }

  public function exportRule(Page $page): ?array
  {
    return $this->ruleFor($page)?->only('package_id');
  }

  public function available(): bool
  {
    return app(RequestMemo::class)->remember('page.access-schema', [], fn () => Schema::hasTable('wbcms_page_access_rules'));
  }

  private function ruleFor(Page $page): ?PageAccessRule
  {
    if (! $this->available()) {
      return null;
    }

    return app(RequestMemo::class)->remember('page.access-rule', [$page->getConnectionName(), $page->site_id, $page->id], fn () => $page->accessRule()->first());
  }

  /** Called immediately after eager loading, without caching membership decisions. */
  public function primeRules(Collection $pages): void
  {
    foreach ($pages as $page) {
      if ($page instanceof Page && $page->relationLoaded('accessRule')) {
        app(RequestMemo::class)->put('page.access-rule', [$page->getConnectionName(), $page->site_id, $page->id], $page->accessRule);
      }
    }
  }

  public function importRule(Page $page, mixed $rule): void
  {
    if ($rule === null) {
    return;
    } // Import never loosens an existing target policy.
    if (! is_array($rule) || ! array_key_exists('package_id', $rule) || array_diff(array_keys($rule), ['package_id']) !== []) {
      throw new \RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid_access'));
    }
    if (! $this->available()) {
      throw new \RuntimeException(__('webblocks-cms::admin.plugin_dependencies.invalid_access'));
    }
    // Source package IDs cannot authorize unrelated packages on another installation.
    $page->accessRule()->updateOrCreate(['page_id' => $page->id], ['package_id' => $rule['package_id'] === null ? null : 0]);
  }
}
