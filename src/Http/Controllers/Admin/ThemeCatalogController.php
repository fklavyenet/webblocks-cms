<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\InstallCatalogThemeRequest;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Theme\PublicThemes;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;

final class ThemeCatalogController
{
  public function __construct(private readonly ThemeCatalog $catalog, private readonly PublicThemes $themes) {}

  public function index(Request $request): View
  {
    abort_unless($this->themes->ready(), 503);
    abort_unless($this->catalog->enabled(), 404);
    $page = $this->page($request);
    $catalogError = null;
    try {
      $result = $this->catalog->browse($page);
    } catch (ValidationException $exception) {
      $result = ['themes' => [], 'hasMore' => false];
      $catalogError = $exception->getMessage();
    }

    $themes = new Paginator($result['themes'], ThemeCatalog::PAGE_SIZE, $page, ['path' => $request->url()]);
    $themes->hasMorePagesWhen($page < 10000 && $result['hasMore']);

    return view('webblocks-cms::admin.system.themes.catalog', compact('themes', 'catalogError'));
  }

  public function store(InstallCatalogThemeRequest $request): RedirectResponse
  {
    abort_unless($this->themes->ready(), 503);
    abort_unless($this->catalog->enabled(), 404);
    $page = $this->page($request);
    $return = redirect()->route('admin.system.themes.catalog.index', $page > 1 ? ['page' => $page] : []);
    $operation = new ExtensionOperation('theme', 'install', 'download');
    $operation->identify($request->validated('handle'), $request->validated('version'));
    try {
      $operation->validate(fn () => $this->catalog->install($request->validated('handle'), $request->validated('version'), $operation), 'catalog');
    } catch (ValidationException $exception) {
      return $return->withErrors($exception->errors());
    }

    return $return->with('theme_status', 'catalog_install_success');
  }

  private function page(Request $request): int
  {
    $query = $request->query('page', '1');

    return is_string($query) && ctype_digit($query) ? min(10000, max(1, (int) $query)) : 1;
  }
}
