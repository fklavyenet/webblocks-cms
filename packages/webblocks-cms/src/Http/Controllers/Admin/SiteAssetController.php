<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use WebBlocks\Cms\Http\Requests\Admin\SiteAssetRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Users\AdminAuthorization;

class SiteAssetController extends Controller
{
  public function __construct(
    private readonly AdminAuthorization $authorization,
    private readonly SiteAssetStore $assets,
  ) {}

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
    } catch (RuntimeException $exception) {
      throw ValidationException::withMessages([
        'contents' => $exception->getMessage(),
      ]);
    }

    return redirect()
      ->route('admin.sites.edit', ['site' => $site, 'tab' => 'assets'])
      ->with('status', $asset['label'].' site asset saved.');
  }
}
