<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\EmbeddedApplicationAssetRequest;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Applications\ApplicationAssetStore;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class EmbeddedApplicationAssetController extends Controller
{
  public function __construct(private readonly ApplicationAssetStore $assets) {}

  public function index(Request $request, EmbeddedApplication $embeddedApplication): View
  {
    $sites = Site::query()->orderBy('name')->get();
    $site = $sites->firstWhere('id', (int) $request->query('site')) ?? $sites->first();

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.embedded-applications.assets', [
      'application' => $embeddedApplication,
      'sites' => $sites,
      'site' => $site,
      'assets' => $site ? $this->assets->all($site, $embeddedApplication) : [],
    ]);
  }

  public function store(EmbeddedApplicationAssetRequest $request, EmbeddedApplication $embeddedApplication): RedirectResponse
  {
    $site = Site::query()->findOrFail($request->integer('site_id'));
    $file = $request->file('asset');
    $filename = $file->getClientOriginalName();
    $type = strtolower((string) $file->getClientOriginalExtension());

    try {
      $current = $this->assets->read($site, $embeddedApplication, $type, $filename);
      if ($current['exists']) {
        throw new RuntimeException($this->message('asset_exists'));
      }
      $this->assets->write($site, $embeddedApplication, $type, $filename, (string) file_get_contents($file->getRealPath()), null);
      if ($type === 'html') {
        $this->assets->activateManagedEntry($embeddedApplication);
      }
    } catch (RuntimeException $exception) {
      return back()->withErrors(['asset' => $exception->getMessage()]);
    }

    return $this->redirect($embeddedApplication, $site)->with('status', $this->message('asset_uploaded'));
  }

  public function update(EmbeddedApplicationAssetRequest $request, EmbeddedApplication $embeddedApplication, string $type, string $filename): RedirectResponse
  {
    $site = Site::query()->findOrFail($request->integer('site_id'));

    try {
      $this->assets->write($site, $embeddedApplication, $type, $filename, (string) $request->input('contents'), $request->input('expected_checksum'));
    } catch (RuntimeException $exception) {
      return back()->withErrors(['contents' => $exception->getMessage()]);
    }

    return $this->redirect($embeddedApplication, $site)->with('status', $this->message('asset_updated'));
  }

  public function destroy(EmbeddedApplicationAssetRequest $request, EmbeddedApplication $embeddedApplication, string $type, string $filename): RedirectResponse
  {
    $site = Site::query()->findOrFail($request->integer('site_id'));

    try {
      $current = $this->assets->read($site, $embeddedApplication, $type, $filename);
      if ($type === 'html' && $embeddedApplication->entry_url === $current['public_path']) {
        throw new RuntimeException($this->message('asset_referenced'));
      }
      $references = collect($type === 'css' ? $embeddedApplication->css_assets : ($type === 'js' ? $embeddedApplication->js_assets : []))
        ->map(fn (array|string $asset): string => is_array($asset) ? (string) ($asset['path'] ?? '') : $asset);
      if ($references->contains($current['public_path'])) {
        throw new RuntimeException($this->message('asset_referenced'));
      }
      $this->assets->delete($site, $embeddedApplication, $type, $filename, $request->input('expected_checksum'));
    } catch (RuntimeException $exception) {
      return back()->withErrors(['asset' => $exception->getMessage()]);
    }

    return $this->redirect($embeddedApplication, $site)->with('status', $this->message('asset_deleted'));
  }

  private function redirect(EmbeddedApplication $application, Site $site): RedirectResponse
  {
    return redirect()->route('admin.embedded-applications.assets.index', ['embedded_application' => $application, 'site' => $site->id]);
  }

  private function message(string $key): string
  {
    return app(CmsTranslator::class)->admin('embedded_applications.'.$key, app(AdminLocaleResolver::class)->locale());
  }
}
