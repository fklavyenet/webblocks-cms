<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\InstallCatalogThemeRequest;
use WebBlocks\Cms\Support\Theme\PublicThemes;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;

final class ThemeCatalogController
{
  public function __construct(private readonly ThemeCatalog $catalog, private readonly PublicThemes $themes) {}

  public function index(Request $request): View
  {
    abort_unless($this->themes->ready(), 503);
    abort_unless($this->catalog->enabled(), 404);
    $query = $request->query('page', '1');
    $page = is_string($query) && ctype_digit($query) ? min(10000, max(1, (int) $query)) : 1;
    $catalogError = null;
    try {
      $result = $this->catalog->browse($page);
    } catch (ValidationException $exception) {
      $result = ['themes' => [], 'hasMore' => false];
      $catalogError = $exception->getMessage();
    }

    return view('webblocks-cms::admin.system.themes.catalog', $result + compact('page', 'catalogError'));
  }

  public function store(InstallCatalogThemeRequest $request): RedirectResponse
  {
    abort_unless($this->themes->ready(), 503);
    abort_unless($this->catalog->enabled(), 404);
    $this->catalog->install($request->validated('handle'), $request->validated('version'));

    return redirect()->route('admin.system.themes.index')->with('theme_status', 'installed');
  }
}
