<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Plugins\PluginAccessResolver;
use WebBlocks\Cms\Support\Plugins\PluginAuthorizationRegistrar;

class AuthorizePluginPermission
{
  public function __construct(
    private readonly PluginAuthorizationRegistrar $authorization,
    private readonly PluginAccessResolver $access,
  ) {}

  public function handle(Request $request, Closure $next, string $permission): Response
  {
    $this->authorization->register();

    if (! $request->user()) {
      return redirect()->guest(route('webblocks.auth.login'));
    }

    abort_unless($this->access->canAccessPluginPermission($request->user(), $permission), 403);

    $acl = app(CmsAuthorization::class);
    if ($acl->ready() && ! $acl->isSuperAdmin($request->user())) {
      $sites = $this->access->permittedSiteIds($request->user(), $permission)->intersect(collect($request->attributes->get('cms_acl_allowed_sites', $acl->siteIds($request->user())->all())));
      abort_unless($sites->isNotEmpty(), 403);
      $request->attributes->set('cms_acl_allowed_sites', $sites->all());
      foreach ($request->route()?->parameters() ?? [] as $resource) {
        if ($resource instanceof Model) {
          $siteId = $resource instanceof Site ? $resource->id : $resource->site_id;
          abort_unless($siteId && $sites->contains((int) $siteId), 403);
        }
      }
      foreach (['site_id', 'site'] as $key) {
        if ($request->filled($key)) {
        abort_unless(is_numeric($request->input($key)) && $sites->contains((int) $request->input($key)), 403);
        }
      }
    }

    return $next($request);
  }
}
