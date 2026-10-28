<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Actions\Consent\SaveConsentService;
use WebBlocks\Cms\Actions\Consent\UpdateConsentSettings;
use WebBlocks\Cms\Http\Requests\ConsentRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\ConsentQuery;

class ConsentController extends Controller
{
  public function index(Request $request, ConsentQuery $query): View
  {
    return view('webblocks-cms::admin.consent.index', $query->workspace($request) + ['section' => match ($request->route()->getName()) {
      'admin.consent.services' => 'services', 'admin.consent.texts' => 'texts', default => 'general',
    }]);
  }

  public function settings(ConsentRequest $request, Site $site, UpdateConsentSettings $action): RedirectResponse
  {
    $action->settings($site, $request->validated());

    return $this->back($site, 'settings');
  }

  public function service(ConsentRequest $request, Site $site, string $service, SaveConsentService $action): RedirectResponse
  {
    $action->execute($site, $service, $request->validated());

    return $this->back($site, 'services');
  }

  public function destroy(ConsentRequest $request, Site $site, string $service, SaveConsentService $action): RedirectResponse
  {
    $action->delete($site, $service);

    return $this->back($site, 'services');
  }

  public function texts(ConsentRequest $request, Site $site, UpdateConsentSettings $action): RedirectResponse
  {
    $action->texts($site, $request->validated('locale'), $request->validated('texts'));

    return redirect()->route('admin.consent.texts', ['site' => $site->id, 'locale' => $request->validated('locale')])
      ->with('consent_locale', $request->validated('locale'))->with('status', __('webblocks-cms::consent.saved'));
  }

  public function preview(Request $request, ConsentQuery $query): View
  {
    return view('webblocks-cms::admin.consent.preview', $query->workspace($request));
  }

  private function back(Site $site, string $section): RedirectResponse
  {
    return redirect()->route('admin.consent.'.$section, ['site' => $site->id])->with('status', __('webblocks-cms::consent.saved'));
  }
}
