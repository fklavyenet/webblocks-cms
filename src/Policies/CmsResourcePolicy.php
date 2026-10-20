<?php

namespace WebBlocks\Cms\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;

/** One operation/site decision shared by session and delegated API entry points. */
final class CmsResourcePolicy
{
  public function __construct(private readonly CmsAuthorization $acl) {}

  public function authorizeRequest(Request $request): void
  {
    $user = $request->user();
    if (! $this->acl->ready() || ! $user) {
    return;
    }
    $route = (string) $request->route()?->getName();
    $api = str_starts_with($route, 'internal-content-api.');
    $name = preg_replace('/^(admin|internal-content-api)\./', '', $route);
    if (! str_starts_with($route, 'admin.') && ! $api) {
    return;
    } // Plugin permissions have their own registered contract.
    if ($this->acl->isSuperAdmin($user)) {
    return;
    }
    if (str_starts_with($name, 'listing-views.')) {
      abort_unless(app(ListingViewPolicy::class)->listing($user, (string) $request->route('listing')), 403);

      return;
    }
    if ($name === 'dashboard' || $name === 'profile.edit' || in_array($name, ['profile.update', 'profile.password.update', 'profile.locale.update'], true)) {
    return;
    }
    abort_if(str_starts_with($name, 'pages.assets.'), 403);
    if (str_starts_with($name, 'site-assets.') || str_starts_with($name, 'sites.assets.') || str_starts_with($name, 'pages.move-site.')) {
      abort_if($api, 403);
      $legacyPermission = str_starts_with($name, 'pages.move-site.') ? 'cms.legacy.pages.move-site' : ($request->isMethodSafe() ? 'cms.legacy.site-assets.read' : 'cms.legacy.site-assets.write');
      $sites = $this->acl->siteIds($user, $legacyPermission);
      abort_unless($sites->isNotEmpty(), 403);
      foreach ($request->route()?->parameters() ?? [] as $resource) {
        if ($resource instanceof Model) {
        abort_unless($sites->contains((int) ($resource instanceof Site ? $resource->id : $resource->site_id)), 403);
        }
      }
      foreach (['site', 'site_id', 'target_site_id'] as $key) {
      if ($request->filled($key)) {
      abort_unless($sites->contains((int) $request->input($key)), 403);
      }
      }
      $request->attributes->set('cms_acl_allowed_sites', $sites->all());

      return;
    }
    $group = $this->group($name);
    $boundPage = null;
    foreach ($request->route()?->parameters() ?? [] as $resource) {
      if ($resource instanceof Page) {
      $boundPage = $resource;
      }
      if ($resource instanceof Block || $resource instanceof PageSlot) {
      $boundPage = $resource->page;
      }
    }
    if (! $boundPage && is_numeric($request->input('page_id'))) {
    $boundPage = Page::query()->find($request->input('page_id'));
    }
    if ($boundPage?->isSharedSlotSourcePage() && $group === 'pages') {
    $group = 'shared-slots';
    }
    if ($group === null) {
      abort_unless($api && (str_starts_with($name, 'examples.') || in_array($name, ['openapi', 'ai-guide', 'inventory.show', 'locales.index', 'locale-options.index', 'page-layouts.index', 'block-types.index', 'content-contract.show', 'icon-catalog.index'], true)), 403);

      return;
    }
    $read = 'cms.'.$group.'.read';
    if ($group === 'personal-tokens') {
    $read = 'cms.personal-tokens.manage';
    }
    $sites = $this->acl->siteIds($user, $read)->intersect($this->acl->siteIds($user));
    $request->attributes->set('cms_acl_scope', $read);
    // Existing personal tokens keep their historical transport-specific rights.
    if ($api) {
      $sites = $sites->merge($this->acl->legacyApiSiteIds($user))->unique()->intersect(collect($request->attributes->get('cms_api_allowed_site_ids', $this->acl->siteIds($user)->all())));
      $request->attributes->set('cms_api_allowed_site_ids', $sites->all());
      $request->attributes->set('cms_acl_allowed_sites', $sites->all());
    }
    abort_unless($sites->isNotEmpty(), 403);
    $resourceSites = [];
    $page = null;
    foreach ($request->route()?->parameters() ?? [] as $resource) {
      if ($resource instanceof Page) {
      $page = $resource;
      }
      if ($resource instanceof Block || $resource instanceof PageSlot) {
      $page = $resource->page;
      }
      if ($resource instanceof Model) {
        $siteId = $resource instanceof Site ? $resource->id : ($resource->site_id ?? $resource->page?->site_id ?? $resource->sharedSlot?->site_id);
        if ($siteId) {
        $resourceSites[] = (int) $siteId;
        }
        if ($resource instanceof Media) {
        $this->authorizeMedia($user, $resource, $request);
        }
      }
    }
    foreach (['site_id', 'site'] as $key) {
      $value = $request->input($key);
      if (is_scalar($value) && filled($value)) {
        $siteId = is_numeric($value) ? (int) $value : Site::query()->where('handle', $value)->value('id');
        if ($siteId) {
        $resourceSites[] = (int) $siteId;
        }
      }
    }
    foreach (['page_id', 'source_page_id', 'staged_page_id'] as $key) {
      if (is_numeric($request->input($key))) {
        $target = Page::query()->findOrFail($request->input($key));
        $resourceSites[] = (int) $target->site_id;
        $page ??= $target;
      }
    }
    foreach (array_unique($resourceSites) as $siteId) {
    abort_unless($sites->contains($siteId), 403);
    }
    if ($group === 'sources' && $page) {
      abort_unless($this->acl->allows($user, 'cms.pages.read', $page->site_id), 403);
      if (! $request->isMethodSafe() && str_ends_with($name, '.save')) {
      abort_unless($this->acl->allows($user, 'cms.pages.update-draft', $page->site_id), 403);
      }
    }
    $safe = $request->isMethodSafe() || in_array(last(explode('.', $name)), ['preview', 'validate'], true);
    $permission = $this->permission($request, $name, $group, $page, $safe);
    $targets = array_unique($resourceSites);
    if ($targets === []) {
    $targets = [null];
    }
    foreach ($targets as $siteId) {
      $compat = $api && $siteId !== null && $this->acl->legacyRole($user, $siteId) !== null;
      if (! $compat) {
      abort_unless($this->acl->allows($user, $permission, $siteId), 403);
      }
      if (! $safe && ! $compat) {
      $this->authorizeFields($request->all(), $user, $siteId);
      }
    }
    foreach (['page_ids' => Page::class, 'media_ids' => Media::class, 'comment_ids' => CommentEntry::class] as $key => $class) {
      foreach ((array) $request->input($key, []) as $id) {
        $model = $class::query()->findOrFail($id);
        if ($model instanceof Media) {
        $this->authorizeMedia($user, $model, $request);

        continue;
        }
        abort_unless($this->acl->allows($user, $permission, $model->site_id), 403);
        if ($model instanceof Page && $model->isPublished()) {
        abort_unless($this->acl->allows($user, 'cms.pages.delete-published', $model->site_id), 403);
        }
      }
    }
    if ($page && ! $safe && $group === 'pages' && $page->isPublished() && str_contains($permission, 'delete')) {
      abort_unless($this->acl->allows($user, 'cms.pages.delete-published', $page->site_id), 403);
    }
    if (str_contains($name, 'version-candidates.apply') || str_contains($name, 'versions.apply') || str_contains($name, 'revisions.apply')) {
      abort_unless($page && $this->acl->allows($user, 'cms.pages.publish', $page->site_id), 403);
    }
  }

  public function group(string $name): ?string
  {
    return match (true) {
      str_starts_with($name, 'profile.api-tokens.') => 'personal-tokens',
      str_starts_with($name, 'pages.') && (str_contains($name, 'versions') || str_contains($name, 'revision') || str_contains($name, 'candidate')) => 'revisions',
      str_starts_with($name, 'pages.'), str_starts_with($name, 'blocks.'), str_starts_with($name, 'content.') => 'pages',
      str_starts_with($name, 'media.') => 'media',
      str_starts_with($name, 'navigation.') || str_starts_with($name, 'navigation-menus.') => 'navigation',
      str_starts_with($name, 'shared-slots.') => 'shared-slots',
      str_starts_with($name, 'contact-messages.') => 'messages',
      str_starts_with($name, 'engagement.') => 'engagement',
      str_starts_with($name, 'reports.') => 'reports',
      str_starts_with($name, 'sites.') => 'site-settings',
      str_starts_with($name, 'automatic-pages.') => 'automatic-pages',
      str_starts_with($name, 'dynamic-content.') || str_starts_with($name, 'content-sources.') => 'sources',
      default => null,
    };
  }

  private function permission(Request $request, string $name, string $group, ?Page $page, bool $safe): string
  {
    $action = last(explode('.', $name));
    if ($group === 'personal-tokens') {
    return 'cms.personal-tokens.manage';
    }
    if ($safe) {
    return 'cms.'.$group.'.'.($action === 'preview' && $group === 'sources' ? 'preview' : 'read');
    }
    if ($group === 'pages') {
      if ($name === 'content.apply') {
        return match ($request->input('plan.mode', $request->input('mode', 'create_draft_page'))) {
          'create_draft_page' => 'cms.pages.create',
          'promote_staged_page_update' => 'cms.pages.publish',
          default => 'cms.pages.update-draft',
        };
      }
      if (str_contains($name, '.translations.')) {
      return 'cms.translations.update';
      }
      if (str_contains($name, '.workflow')) {
      return $this->workflowPermission($page, (string) $request->input('action'));
      }
      if (in_array($action, ['publish', 'publish-page-owned-blocks'], true)) {
      return 'cms.pages.publish';
      }
      if ($action === 'archive') {
      return 'cms.pages.archive';
      }
      if (in_array($action, ['destroy', 'bulk-destroy', 'delete'], true) && ! str_starts_with($name, 'blocks.') && ! str_contains($name, '.blocks.')) {
      return 'cms.pages.delete';
      }
      if ($name === 'pages.store' || str_contains($name, '.duplicate.') || str_contains($name, '.import.')) {
      return 'cms.pages.create';
      }

      return 'cms.pages.'.($page && $page->status !== Page::STATUS_DRAFT ? 'update-live' : 'update-draft');
    }
    $verb = match ($group) {
      'revisions' => str_contains($name, 'apply') ? 'apply' : 'prepare',
      'media' => match ($action) {
      'store', 'fetch' => 'upload', 'replace' => 'replace', 'move' => 'move', 'destroy', 'delete', 'bulk-destroy' => 'delete', default => 'update'
      },
      'navigation' => in_array($action, ['destroy', 'delete'], true) ? 'delete' : 'update',
      'shared-slots' => (str_contains($name, 'revision') || str_contains($name, 'candidate') || str_contains($name, 'restore')) ? 'restore' : (str_starts_with($name, 'shared-slots.') && in_array($action, ['store', 'update', 'destroy', 'delete'], true) && ! str_contains($name, '.blocks.') ? 'manage' : 'update-content'),
      'messages' => in_array($action, ['destroy', 'bulk-destroy'], true) ? 'delete' : 'update-status',
      'engagement' => $action === 'destroy' ? 'delete' : 'moderate',
      'site-settings' => 'update',
      'automatic-pages' => in_array($action, ['activate', 'pause'], true) ? 'activate' : 'configure',
      'sources' => $action === 'preview' ? 'preview' : 'read',
      default => 'read',
    };

    return 'cms.'.$group.'.'.$verb;
  }

  public function workflowPermission(?Page $page, string $action): string
  {
    return 'cms.pages.'.match ($action) {
      'submit_review' => 'submit-review', 'publish' => 'publish', 'archive' => 'archive',
      'restore_draft' => $page?->isPublished() ? 'unpublish' : 'return-to-draft',
      default => 'invalid',
    };
  }

  public function authorizeFields(array $payload, $user, ?int $siteId): void
  {
    foreach ($payload as $key => $value) {
      if ($key === 'custom_head_html') {
      abort_unless($siteId && $this->acl->allows($user, 'cms.legacy.site-assets.write', $siteId), 403);
      }
      if (in_array($key, ['seo_title', 'seo_description', 'seo_keywords', 'og_image_media_id', 'og_image_asset_id', 'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image_id', 'canonical_url', 'robots', 'seo'], true)) {
        abort_unless($this->acl->allows($user, 'cms.seo.update', $siteId), 403);
      }
      if ($key === 'locale' && is_string($value) && $value !== (Locale::query()->where('is_default', true)->value('code') ?? 'en')) {
      abort_unless($this->acl->allows($user, 'cms.translations.update', $siteId), 403);
      }
      if ($key === 'translations' || $key === 'translation') {
      abort_unless($this->acl->allows($user, 'cms.translations.update', $siteId), 403);
      }
      if (is_array($value)) {
      $this->authorizeFields($value, $user, $siteId);
      }
    }
  }

  public function authorizePlan(array $plan): void
  {
    $user = request()->user();
    if (! $user || ! $this->acl->ready() || $this->acl->isSuperAdmin($user) || request()->attributes->get('cms_api_token')?->token_type === 'system') {
    return;
    }
    $siteId = (int) data_get($plan, 'site.id');
    abort_unless($siteId && $this->acl->allows($user, 'cms.pages.read', $siteId), 403);
    $permission = ($plan['mode'] ?? '') === 'create_draft_page' ? 'cms.pages.create' : (($plan['mode'] ?? '') === 'promote_staged_page_update' ? 'cms.pages.publish' : 'cms.pages.update-draft');
    abort_unless($this->acl->allows($user, $permission, $siteId), 403);
    foreach (['navigation_menus' => 'cms.navigation.update', 'shared_slots' => 'cms.shared-slots.manage', 'page_slot_shared_slots' => 'cms.shared-slots.update-content'] as $key => $required) {
      if (! empty($plan[$key])) {
      abort_unless($this->acl->allows($user, $required, $siteId) || $this->acl->legacyApiAllows($user, $required, $siteId), 403);
      }
    }
    $this->authorizeFields($plan, $user, $siteId);
  }

  private function authorizeMedia($user, Media $media, Request $request): void
  {
    // Protected compatibility assignments keep their existing media access contract.
    // Custom assignments additionally require authority over every affected site.
    if ($this->acl->usesLegacyRoles($user)) {
    return;
    }
    if ($request->isMethodSafe()) {
    return;
    }
    $action = last(explode('.', (string) $request->route()?->getName()));
    $permission = 'cms.media.'.match ($action) {
    'replace' => 'replace', 'move' => 'move', 'destroy', 'delete', 'bulk-destroy' => 'delete', default => 'update'
    };
    $moving = $request->has('folder_id') && (int) $request->input('folder_id') !== (int) $media->folder_id;
    $sites = collect();
    foreach (['blocks', 'blockMedia.block', 'pageTranslationsUsingAsOgImage.page'] as $relation) {
      try {
        $items = match ($relation) {
          'blocks' => $media->blocks()->with('page')->get()->pluck('page.site_id'),
          'blockMedia.block' => $media->blockMedia()->with('block.page')->get()->pluck('block.page.site_id'),
          default => $media->pageTranslationsUsingAsOgImage()->with('page')->get()->pluck('page.site_id'),
        };
        $sites = $sites->merge($items);
      } catch (QueryException) {
      abort(403);
      }
    }
    $sites = $sites->merge($media->sitesUsingAsFavicon()->pluck('id'))->merge($media->sitesUsingAsSocialImage()->pluck('id'))->filter()->unique();
    foreach ($sites as $siteId) {
      abort_unless($this->acl->allows($user, $permission, $siteId) && (! $moving || $this->acl->allows($user, 'cms.media.move', $siteId)), 403);
    }
    if ($sites->isEmpty()) {
    abort_unless((int) $media->uploaded_by === (int) $user->id && $this->acl->allows($user, $permission) && (! $moving || $this->acl->allows($user, 'cms.media.move')), 403);
    }
  }
}
