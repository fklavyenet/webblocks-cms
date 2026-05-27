<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Requests\WebBlocksUiReleaseRequest;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiManagerPaths;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class WebBlocksUiReleaseController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly WebBlocksUiManagerPaths $paths,
  ) {}

  public function index(): View
  {
    return view($this->view('index'), $this->viewData('WebBlocks UI Releases', [
      'releases' => WebBlocksUiRelease::query()
        ->withCount('artifacts')
        ->latest('id')
        ->paginate(20),
    ]));
  }

  public function create(): View
  {
    return view($this->view('form'), $this->viewData('New WebBlocks UI Release', [
      'release' => new WebBlocksUiRelease([
        'status' => WebBlocksUiRelease::STATUS_DRAFT,
        'cdn_base_path' => $this->paths->defaultCdnBasePath(),
        'cdn_base_url' => $this->paths->defaultCdnBaseUrl(),
      ]),
      'formAction' => route('webblocks.plugins.webblocks_ui_manager.releases.store'),
      'method' => 'POST',
    ]));
  }

  public function store(WebBlocksUiReleaseRequest $request): RedirectResponse
  {
    $release = WebBlocksUiRelease::query()->create($request->validated());

    return redirect()
      ->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release)
      ->with('status', 'WebBlocks UI release metadata created.');
  }

  public function show(WebBlocksUiRelease $release): View
  {
    $release->load('artifacts');

    return view($this->view('show'), $this->viewData($release->label ?: $release->version, [
      'release' => $release,
    ]));
  }

  public function edit(WebBlocksUiRelease $release): View
  {
    return view($this->view('form'), $this->viewData('Edit WebBlocks UI Release', [
      'release' => $release,
      'formAction' => route('webblocks.plugins.webblocks_ui_manager.releases.update', $release),
      'method' => 'PUT',
    ]));
  }

  public function update(WebBlocksUiReleaseRequest $request, WebBlocksUiRelease $release): RedirectResponse
  {
    $release->update($request->validated());

    return redirect()
      ->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release)
      ->with('status', 'WebBlocks UI release metadata updated.');
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function viewData(string $title, array $data): array
  {
    return array_merge($data, [
      'title' => $title,
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle($title),
    ]);
  }

  private function view(string $name): string
  {
    return WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::plugins.webblocks-ui-manager.releases.'.$name;
  }
}
