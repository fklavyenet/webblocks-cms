<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\InstallCatalogLanguageRequest;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Translations\LanguagePackCatalog;
use WebBlocks\Cms\Support\Translations\NativeLanguagePacks;
use WebBlocks\Cms\Support\WebBlocks;

final class LanguagePackCatalogController
{
  public function __construct(
    private readonly LanguagePackCatalog $catalog,
    private readonly NativeLanguagePacks $native,
  ) {}

  public function index(): View
  {
    abort_unless($this->catalog->enabled(), 404);

    $catalogError = null;
    try {
      $releases = $this->catalog->browse();
    } catch (ValidationException $exception) {
      $releases = [];
      $catalogError = $exception->getMessage();
    }

    $installed = $this->native->installed();
    $rows = [];
    foreach ($releases as $code => $formats) {
      foreach ($formats as $format => $release) {
        $current = $format === 'native' ? $installed->get($code) : null;
        $state = $current === null ? 'install'
          : (version_compare($release['version'], $current->release_version, '>') ? 'update' : 'installed');
        $rows[] = ['state' => $state] + $release;
      }
    }

    return view('webblocks-cms::admin.system.panel-languages.extensions', [
      'releases' => $rows,
      'catalogError' => $catalogError,
      'cmsVersion' => WebBlocks::VERSION,
    ]);
  }

  public function store(InstallCatalogLanguageRequest $request): RedirectResponse
  {
    abort_unless($this->catalog->enabled(), 404);

    $data = $request->validated();
    $operation = new ExtensionOperation('language_pack', 'install', 'download');
    $operation->identify($data['code'], $data['version']);
    $operation->targetCms(WebBlocks::VERSION);
    try {
      $id = $operation->validate(fn () => $this->catalog->install($data['code'], $data['version'], $data['format'], $data['checksum'], $operation), 'catalog');
    } catch (ValidationException $exception) {
      return redirect()->route('admin.system.panel-languages.extensions.index')->withErrors($exception->errors());
    }

    return $id === null
      ? redirect()->route('admin.system.panel-languages.index')->with('panel_language_status', 'extensions_native_installed')
      : redirect()->route('admin.system.panel-languages.show', $id)->with('panel_language_status', 'uploaded');
  }
}
