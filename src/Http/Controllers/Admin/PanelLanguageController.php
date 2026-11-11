<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PanelLanguageCatalogRequest;
use WebBlocks\Cms\Http\Requests\Admin\PanelLanguageDefaultRequest;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\PanelLanguages;
use WebBlocks\Cms\Support\Translations\PanelLanguageSource;

final class PanelLanguageController
{
  public function __construct(
    private readonly PanelLanguages $languages,
    private readonly PanelLanguageSource $source,
  ) {}

  public function index(Request $request, AdminLocaleResolver $resolver): JsonResponse|View
  {
    $data = [
      'catalogs' => $this->languages->catalogs(),
      'options' => $this->languages->options(),
      'defaultLocale' => $resolver->defaultLocale(),
      'packaged' => array_keys($this->source->packagedOptions()),
    ];

    return $this->api($request) ? response()->json($data) : view('webblocks-cms::admin.system.panel-languages.index', $data);
  }

  public function template(): JsonResponse
  {
    return response()->json($this->source->template(), 200, [
      'Content-Disposition' => 'attachment; filename="webblocks-panel-language.json"',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }

  public function store(PanelLanguageCatalogRequest $request): JsonResponse|RedirectResponse
  {
    $id = $this->languages->import($request->catalogContents());
    if ($this->api($request)) {
      return response()->json($this->languages->preview($id), 201);
    }

    return redirect()->route('admin.system.panel-languages.show', $id)
      ->with('status', __('webblocks-cms::panel-languages.uploaded'));
  }

  public function show(Request $request, int $catalog): JsonResponse|View
  {
    $data = $this->languages->preview($catalog);

    return $this->api($request) ? response()->json($data) : view('webblocks-cms::admin.system.panel-languages.show', $data);
  }

  public function activate(Request $request, int $catalog): JsonResponse|RedirectResponse
  {
    $this->languages->activate($catalog);

    return $this->result($request, 'activated');
  }

  public function deactivate(Request $request, string $code): JsonResponse|RedirectResponse
  {
    $this->languages->deactivate($code);

    return $this->result($request, 'deactivated');
  }

  public function updateDefault(PanelLanguageDefaultRequest $request): JsonResponse|RedirectResponse
  {
    $this->languages->saveDefault($request->validated('code'));

    return $this->result($request, 'default_saved');
  }

  private function result(Request $request, string $status): JsonResponse|RedirectResponse
  {
    return $this->api($request)
      ? response()->json(['ok' => true])
      : redirect()->route('admin.system.panel-languages.index')->with('status', __('webblocks-cms::panel-languages.'.$status));
  }

  private function api(Request $request): bool
  {
    return $request->routeIs('internal-content-api.*');
  }
}
