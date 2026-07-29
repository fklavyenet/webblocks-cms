<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRouteRegistrar;

/**
 * Serves a plugin admin URL whose own route was not registered for this request.
 *
 * There are two ways that happens, and neither is the plugin's fault. With a
 * cached route table the registrar does not run at all, so no plugin route
 * exists; and immediately after a catalog update the loaded provider can be the
 * previous version's, pointing at a package directory that has been replaced.
 * Both would otherwise land on the admin dashboard, which reads as "the plugin
 * is broken" rather than "the route table is stale".
 *
 * So this hydrates the plugin's real routes and then runs the one that matches.
 * It used to dispatch by hand instead — a method per plugin, naming controller
 * classes and repeating each route's permission check as an `abort_unless`.
 * That could only ever serve the plugins core had been taught about, it was a
 * second copy of an authorization rule that already lives on the route, and the
 * copy is what a plugin's own release would silently invalidate.
 */
class PluginRouteFallbackController extends Controller
{
  public function __construct(
    private readonly PluginRegistry $plugins,
    private readonly PluginRouteRegistrar $routes,
    private readonly Router $router,
  ) {}

  public function __invoke(Request $request, ?string $plugin = null, ?string $pluginPath = null): Response
  {
    $plugin = $this->routeString($request, 'plugin') ?? $plugin;

    abort_if($plugin === null || $plugin === '', 404);

    $definition = $this->plugins->get($plugin);

    abort_if($definition === null || ! $this->plugins->isEnabled($plugin), 404);

    $fallback = $request->route();

    $this->routes->registerAdminRoutesFor($definition);

    $route = $this->matchOutsideFallback($request);

    abort_if($route === null, 404);

    return $this->run($request, $route, $fallback);
  }

  /**
   * Match the request against every route except this one.
   *
   * The fallback is registered before the plugin routes it hydrates, so a plain
   * re-dispatch would match the fallback again and call straight back into here.
   * Matching against a collection it has been removed from is what makes the
   * newly registered plugin route the one that wins.
   */
  private function matchOutsideFallback(Request $request): ?RoutingRoute
  {
    $candidates = new RouteCollection;

    foreach ($this->router->getRoutes() as $candidate) {
      if ($candidate->getName() === 'webblocks.plugins.fallback') {
        continue;
      }

      $candidates->add($candidate);
    }

    try {
      return $candidates->match($request);
    } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
      return null;
    }
  }

  /**
   * Run the matched route through the middleware it declares that has not
   * already run.
   *
   * The admin stack — session, authentication, admin access — ran on the way in
   * to the fallback, and running it a second time would start a second session
   * and re-queue its cookies. What has *not* run is what makes this route
   * different from the fallback: its plugin permission and the setup guard. Those
   * are the point of re-running anything at all, so they are gathered from the
   * route rather than restated here.
   */
  private function run(Request $request, RoutingRoute $route, ?RoutingRoute $fallback): Response
  {
    $alreadyRun = $fallback === null ? [] : $this->stringMiddleware($this->router->gatherRouteMiddleware($fallback));
    $middleware = array_values(array_filter(
      $this->router->gatherRouteMiddleware($route),
      fn (mixed $item): bool => ! is_string($item) || ! in_array($item, $alreadyRun, true),
    ));

    $route->setContainer(app());
    $request->setRouteResolver(fn (): RoutingRoute => $route);

    return (new Pipeline(app()))
      ->send($request)
      ->through($middleware)
      ->then(fn (Request $request): Response => $this->router->prepareResponse($request, $route->run()));
  }

  /**
   * @param  array<int, mixed>  $middleware
   * @return array<int, string>
   */
  private function stringMiddleware(array $middleware): array
  {
    return array_values(array_filter($middleware, fn (mixed $item): bool => is_string($item)));
  }

  private function routeString(Request $request, string $key): ?string
  {
    $value = $request->route($key);

    if (is_string($value) || is_numeric($value)) {
      return (string) $value;
    }

    return null;
  }
}
