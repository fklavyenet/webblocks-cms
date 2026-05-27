<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Http\Requests\WebBlocksUiReleaseRequest;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiPublishRun;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Models\WebBlocksUiRelease;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiManagerPaths;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiManagerSchema;
use WebBlocks\Cms\Plugins\WebBlocksUiManager\Support\WebBlocksUiReleasePublisher;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class WebBlocksUiReleaseController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly WebBlocksUiManagerPaths $paths,
    private readonly WebBlocksUiReleasePublisher $publisher,
    private readonly WebBlocksUiManagerSchema $schema,
  ) {}

  public function index(): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    return view($this->view('index'), $this->viewData('WebBlocks UI Releases', [
      'releases' => WebBlocksUiRelease::query()
        ->withCount('artifacts')
        ->latest('id')
        ->paginate(20),
    ]));
  }

  public function create(): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

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
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $release = WebBlocksUiRelease::query()->create($request->validated());

    return redirect()
      ->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release)
      ->with('status', 'WebBlocks UI release metadata created.');
  }

  public function show(string $release): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $release = $this->release($release);
    $release->load(['artifacts', 'publishRuns']);

    return view($this->view('show'), $this->viewData($release->label ?: $release->version, [
      'release' => $release,
      'latestPublishRun' => $release->publishRuns->first(),
      'showPublishModal' => request('modal') === 'publish',
    ]));
  }

  public function edit(string $release): View
  {
    if (! $this->schema->isReady()) {
      return $this->setupRequiredView();
    }

    $release = $this->release($release);

    return view($this->view('form'), $this->viewData('Edit WebBlocks UI Release', [
      'release' => $release,
      'formAction' => route('webblocks.plugins.webblocks_ui_manager.releases.update', $release),
      'method' => 'PUT',
    ]));
  }

  public function update(WebBlocksUiReleaseRequest $request, string $release): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $release = $this->release($release);
    $release->update($request->validated());

    return redirect()
      ->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release)
      ->with('status', 'WebBlocks UI release metadata updated.');
  }

  public function dryRun(string $release): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $release = $this->release($release);
    $run = $this->publisher->dryRun($release->version);

    $redirect = redirect()->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release);

    if ($run->status !== WebBlocksUiPublishRun::STATUS_SUCCEEDED) {
      return $redirect->withErrors(['publish' => $run->message]);
    }

    return $redirect->with('status', $run->message);
  }

  public function publish(string $release): RedirectResponse
  {
    if (! $this->schema->isReady()) {
      return redirect()
        ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
        ->withErrors(['plugin' => $this->schema->message()]);
    }

    $release = $this->release($release);
    $run = $this->publisher->publish($release->version);

    $redirect = redirect()->route('webblocks.plugins.webblocks_ui_manager.releases.show', $release);

    if ($run->status !== WebBlocksUiPublishRun::STATUS_SUCCEEDED) {
      return $redirect->withErrors(['publish' => $run->message]);
    }

    return $redirect->with('status', 'WebBlocks UI release published.');
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

  private function setupRequiredView(): View
  {
    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.plugins.setup-required', $this->viewData('WebBlocks UI Manager Setup Required', [
      'message' => $this->schema->message(),
      'pluginDetailUrl' => route('admin.system.plugins.show', 'webblocks-ui-manager'),
    ]));
  }

  private function release(string $release): WebBlocksUiRelease
  {
    return WebBlocksUiRelease::query()->findOrFail($release);
  }
}
