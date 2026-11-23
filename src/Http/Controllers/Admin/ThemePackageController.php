<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\ThemePackageRequest;
use WebBlocks\Cms\Queries\ExtensionListSearch;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Theme\PublicThemes;

final class ThemePackageController
{
  public function __construct(private readonly PublicThemes $themes) {}

  public function index(Request $request): View|JsonResponse
  {
    abort_unless($this->themes->ready(), 503);
    $api = $request->routeIs('internal-content-api.*');
    $search = $api ? '' : ExtensionListSearch::term($request->query('search'));
    $versions = $this->themes->versions($api ? 25 : AdminPagination::perPage(), packageManagement: true, search: $search);
    if ($api) {
      return response()->json($versions);
    }
    AdminPagination::redirectOutOfRange($versions, $request);

    return view('webblocks-cms::admin.system.themes.index', compact('versions', 'search'));
  }

  public function store(ThemePackageRequest $request): RedirectResponse|JsonResponse
  {
    abort_unless($this->themes->ready(), 503);
    $operation = new ExtensionOperation('theme', 'install');
    $version = $operation->validate(fn () => $this->themes->install($request->file('package')->getPathname(), $operation));

    return $request->routeIs('internal-content-api.*')
      ? response()->json(['version' => $version], 201)
      : redirect()->route('admin.system.themes.index')->with('theme_status', 'installed');
  }

  public function destroy(Request $request, int $version): RedirectResponse|JsonResponse
  {
    abort_unless($this->themes->ready(), 503);
    $this->themes->delete($version);

    if ($request->routeIs('internal-content-api.*')) {
      return response()->json(['ok' => true]);
    }

    $search = ExtensionListSearch::term($request->query('search'));

    return redirect()->route('admin.system.themes.index', $search === '' ? [] : ['search' => $search])
      ->with('theme_status', 'deleted');
  }
}
