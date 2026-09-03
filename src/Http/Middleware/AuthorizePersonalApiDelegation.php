<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\SharedSlotBlock;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalApiResponseMetadata;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class AuthorizePersonalApiDelegation
{
  public function __construct(
    private readonly InternalApiResponseMetadata $metadata,
    private readonly AdminAuthorization $authorization,
    private readonly PageWorkflowManager $workflow,
  ) {}

  public function handle(Request $request, Closure $next): mixed
  {
    $token = $request->attributes->get('cms_api_token');

    if (! $token instanceof CmsApiToken || ! $token->isPersonal()) {
      return $next($request);
    }

    $user = $token->creator;
    $allowedSiteIds = collect($token->allowed_site_ids ?? [])
      ->map(fn ($id) => (int) $id)
      ->intersect($user->accessibleSiteIds()->map(fn ($id) => (int) $id))
      ->values();

    if ($allowedSiteIds->isEmpty()) {
      return $this->denied('This personal API token no longer has access to any site.');
    }

    $request->attributes->set('cms_api_user', $user);
    $request->attributes->set('cms_api_allowed_site_ids', $allowedSiteIds->all());
    $request->setUserResolver(fn () => $user);
    Auth::setUser($user);

    $resourceSiteIds = collect($request->route()?->parameters() ?? [])
      ->map(fn ($resource) => $this->siteIdFor($resource))
      ->filter()
      ->values();

    if ($resourceSiteIds->contains(fn ($siteId) => ! $allowedSiteIds->contains((int) $siteId))) {
      return $this->denied('The delegated user cannot access this site resource.');
    }

    foreach ($request->route()?->parameters() ?? [] as $resource) {
      if ($resource instanceof Media) {
        $allowed = $this->authorization->scopeMediaForUser(Media::query(), $user)->whereKey($resource->id)->exists();

        if (! $allowed) {
          return $this->denied('The delegated user cannot access this media resource.');
        }
      }
    }

    $payloadSiteIds = $this->payloadSiteIds($request);

    foreach ($payloadSiteIds as $siteId) {
      if (! $allowedSiteIds->contains($siteId)) {
        return $this->denied('The delegated user cannot access the requested site.');
      }
    }

    $routeName = (string) $request->route()?->getName();

    if (in_array($routeName, ['internal-content-api.locales.store', 'internal-content-api.locales.update'], true)) {
      return $this->denied('Global locale administration cannot be delegated through a personal API token.', 'delegated_operation_denied');
    }

    if ($routeName === 'internal-content-api.navigation-menus.show' && $payloadSiteIds === []) {
      return $this->denied('Personal API navigation requests must identify an allowed site explicitly.');
    }

    if (! $request->isMethodSafe() && $routeName !== 'internal-content-api.pages.delete') {
      $page = $this->pageFromRoute($request);

      if ($page && ! $this->workflow->canEditContent($user, $page) && ! str_contains($routeName, '.publish') && ! str_ends_with($routeName, '.archive')) {
        return $this->denied('The delegated user cannot edit content in the page’s current workflow state.', 'delegated_workflow_access_denied');
      }
    }
    $siteRequired = $request->isMethodSafe() === false
      && (str_starts_with($routeName, 'internal-content-api.navigation-menus.')
        || str_starts_with($routeName, 'internal-content-api.shared-slots.')
        || in_array($routeName, ['internal-content-api.content.validate', 'internal-content-api.content.apply'], true));

    if ($siteRequired && $resourceSiteIds->isEmpty() && $payloadSiteIds === []) {
      return $this->denied('Personal API requests that write content must identify an allowed site explicitly.');
    }

    return $next($request);
  }

  /** @return list<int> */
  private function payloadSiteIds(Request $request): array
  {
    $payload = $request->json()->all();
    $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : $payload;
    $values = [
      $request->query('site'),
      $request->query('site_id'),
      $plan['site'] ?? null,
      $plan['site_id'] ?? null,
      $plan['site_handle'] ?? null,
    ];
    $ids = [];

    foreach (array_filter($values, fn ($value) => is_scalar($value) && trim((string) $value) !== '') as $value) {
      $site = is_numeric($value)
        ? Site::query()->find((int) $value)
        : Site::query()->where('handle', trim((string) $value))->first();

      if ($site) {
        $ids[] = (int) $site->id;
      }
    }

    foreach (['page_id', 'source_page_id', 'staged_page_id', 'expected_source_page_id'] as $key) {
      if (isset($plan[$key]) && is_numeric($plan[$key])) {
        $siteId = Page::query()->whereKey((int) $plan[$key])->value('site_id');

        if ($siteId) {
          $ids[] = (int) $siteId;
        }
      }
    }

    return array_values(array_unique($ids));
  }

  private function pageFromRoute(Request $request): ?Page
  {
    foreach ($request->route()?->parameters() ?? [] as $resource) {
      $page = match (true) {
        $resource instanceof Page => $resource,
        $resource instanceof PageSlot => $resource->page,
        $resource instanceof PageTranslation => $resource->page,
        $resource instanceof Block => $resource->page,
        default => null,
      };

      if ($page) {
        return $page;
      }
    }

    return null;
  }

  private function siteIdFor(mixed $resource): ?int
  {
    return match (true) {
      $resource instanceof Site => (int) $resource->id,
      $resource instanceof Page => (int) $resource->site_id,
      $resource instanceof NavigationItem => (int) $resource->site_id,
      $resource instanceof SharedSlot => (int) $resource->site_id,
      $resource instanceof PageTranslation => (int) $resource->site_id,
      $resource instanceof PageSlot => (int) $resource->page?->site_id,
      $resource instanceof Block => (int) ($resource->page?->site_id ?: $resource->page()->value('site_id')),
      $resource instanceof SharedSlotBlock => (int) ($resource->sharedSlot?->site_id ?: $resource->sharedSlot()->value('site_id')),
      $resource instanceof Model && isset($resource->site_id) => (int) $resource->site_id,
      default => null,
    } ?: null;
  }

  private function denied(string $message, string $code = 'delegated_site_access_denied'): JsonResponse
  {
    return response()->json($this->metadata->merge([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'errors' => [['path' => 'Authorization', 'message' => $message]],
    ]), 403);
  }
}
