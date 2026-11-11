<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PanelLanguageCatalogRequest;
use WebBlocks\Cms\Http\Requests\Admin\PanelLanguageDefaultRequest;
use WebBlocks\Cms\Support\Admin\AdminPagination;
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
    $api = $this->api($request);
    $catalogs = $api ? $this->languages->catalogs() : $this->languages->catalogs(AdminPagination::perPage());
    if (! $api) {
      AdminPagination::redirectOutOfRange($catalogs, $request);
    }

    $data = [
      'catalogs' => $catalogs,
      'options' => $this->languages->options(),
      'defaultLocale' => $resolver->defaultLocale(),
      'packaged' => array_keys($this->source->packagedOptions()),
    ];

    return $api ? response()->json($data) : view('webblocks-cms::admin.system.panel-languages.index', $data);
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
      ->with('panel_language_status', 'uploaded');
  }

  public function show(Request $request, int $catalog): JsonResponse|View
  {
    $data = $this->languages->preview($catalog);

    if ($this->api($request)) {
      return response()->json($data);
    }

    $data['sampleSource'] = array_intersect_key($this->source->messages(), $data['sample']);
    $page = LengthAwarePaginator::resolveCurrentPage('missing_page');
    $pageSize = 50;
    $missingCount = count($data['missing']);
    $pageItems = $page <= max(1, (int) ceil($missingCount / $pageSize))
      ? array_slice($data['missing'], ($page - 1) * $pageSize, $pageSize)
      : [];
    $data['missing'] = new LengthAwarePaginator(
      $pageItems,
      $missingCount,
      $pageSize,
      $page,
      ['path' => $request->url(), 'pageName' => 'missing_page', 'fragment' => 'panel-language-missing'],
    );
    AdminPagination::redirectOutOfRange($data['missing'], $request, 'missing_page');

    return view('webblocks-cms::admin.system.panel-languages.show', $data);
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

  public function destroy(int $catalog): RedirectResponse
  {
    $this->languages->delete($catalog);

    return redirect()->route('admin.system.panel-languages.index')->with('panel_language_status', 'deleted');
  }

  public function updateDefault(PanelLanguageDefaultRequest $request): JsonResponse|RedirectResponse
  {
    $this->languages->saveDefault($request->validated('code'));

    return $this->result($request, 'default_saved', ['tab' => 'settings']);
  }

  private function result(Request $request, string $status, array $parameters = []): JsonResponse|RedirectResponse
  {
    return $this->api($request)
      ? response()->json(['ok' => true])
      : redirect()->route('admin.system.panel-languages.index', $parameters)->with('panel_language_status', $status);
  }

  private function api(Request $request): bool
  {
    return $request->routeIs('internal-content-api.*');
  }
}
