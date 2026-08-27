<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\SiteAssetRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Sites\SiteAssetWriteException;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteAssetController extends Controller
{
  private const SITE_CONTEXT_SESSION_KEY = 'admin.assets.site';

  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly SiteAssetStore $assets,
  ) {}

  public function index(Request $request): View
  {
    $sites = $this->authorization
      ->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), $request->user())
      ->get();
    $requestedSiteId = (int) $request->integer('site');
    $sessionSiteId = $request->hasSession() ? (int) $request->session()->get(self::SITE_CONTEXT_SESSION_KEY) : 0;
    $site = $sites->firstWhere('id', $requestedSiteId)
      ?? $sites->firstWhere('id', $sessionSiteId)
      ?? $sites->firstWhere('is_primary', true)
      ?? $sites->first();

    if ($site && $request->hasSession()) {
      $request->session()->put(self::SITE_CONTEXT_SESSION_KEY, (string) $site->id);
    }

    return view('webblocks-cms::admin.sites.assets', [
      'sites' => $sites,
      'site' => $site,
      'canManageSiteSettings' => $site ? $this->authorization->canMutateSiteSettings($request->user(), $site) : false,
      'siteAssets' => $site
        ? collect(SiteAssetStore::TYPES)->map(fn (string $type) => $this->assets->read($site, $type))->values()
        : collect(),
    ]);
  }

  public function update(SiteAssetRequest $request, Site $site, string $type): RedirectResponse
  {
    $this->authorization->abortUnlessSiteSettingsMutation($request->user(), $site);

    try {
      $asset = $this->assets->write(
        $site,
        $type,
        $request->contents(),
        $request->expectedChecksum(),
      );
    } catch (SiteAssetWriteException $exception) {
      throw ValidationException::withMessages([
        'contents' => $exception->getMessage(),
      ]);
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages([
        'contents' => $exception->getMessage(),
      ]);
    }

    return redirect()
      ->route('admin.site-assets.index', ['site' => $site])
      ->with('status', $asset['label'].' site asset saved.');
  }
}
