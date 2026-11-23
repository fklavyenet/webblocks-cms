<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\LanguagePackUploadRequest;
use WebBlocks\Cms\Http\Requests\Admin\PanelLanguageDefaultRequest;
use WebBlocks\Cms\Queries\ExtensionListSearch;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\LanguagePackCatalog;
use WebBlocks\Cms\Support\Translations\NativeLanguagePacks;

final class PanelLanguageController
{
  public function __construct(private readonly NativeLanguagePacks $packs) {}

  public function index(Request $request, AdminLocaleResolver $resolver): JsonResponse|View
  {
    $packages = $this->packs->installed()->values()->map(fn (object $pack): array => [
      'code' => $pack->code, 'name' => $pack->name, 'source_version' => $pack->source_version,
      'release_version' => $pack->release_version === '' ? null : $pack->release_version,
    ]);

    if ($this->api($request)) {
      return response()->json([
        'packages' => $packages,
        'options' => $resolver->options(),
        'defaultLocale' => $resolver->defaultLocale(),
      ]);
    }

    $search = ExtensionListSearch::term($request->query('search'));

    return view('webblocks-cms::admin.system.panel-languages.index', [
      'packages' => $packages->filter(fn (array $pack): bool => ExtensionListSearch::matches(
        $search, $pack['name'], $pack['code'], $pack['source_version'], $pack['release_version'] ?? '',
      )),
      'search' => $search,
      'extensionsAvailable' => app(LanguagePackCatalog::class)->enabled(),
      'manualInstallAvailable' => $this->packs->ready(),
    ]);
  }

  public function store(LanguagePackUploadRequest $request): JsonResponse|RedirectResponse
  {
    abort_unless($this->packs->ready(), 503);
    $operation = new ExtensionOperation('language_pack', 'install', 'package');
    $package = $operation->validate(fn () => $this->packs->installArchive($request->file('package')->getPathname(), $operation));
    $package['release_version'] = $package['release_version'] ?: null;

    return $this->api($request)
      ? response()->json(['package' => $package], 201)
      : redirect()->route('admin.system.panel-languages.index')->with('panel_language_status', 'extensions_native_installed');
  }

  public function updateDefault(PanelLanguageDefaultRequest $request, AdminLocaleResolver $resolver, SystemSettings $settings): JsonResponse
  {
    $code = $request->validated('code');
    if (! isset($resolver->options()[$code])) {
      throw ValidationException::withMessages(['code' => __('webblocks-cms::panel-languages.unavailable')])->errorBag('panelLanguageDefault');
    }
    $settings->save([SystemSettings::ADMIN_LOCALE => $code]);

    return response()->json(['ok' => true]);
  }

  private function api(Request $request): bool
  {
    return $request->routeIs('internal-content-api.*');
  }
}
