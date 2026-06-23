<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\CmsApiTokenRequest;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CmsApiTokenController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly CmsApiTokenIssuer $issuer,
  ) {}

  public function index(): View
  {
    $tokens = CmsApiToken::query()
      ->with('creator')
      ->orderByRaw('case when revoked_at is null then 0 else 1 end')
      ->latest()
      ->paginate($this->systemSettings->adminListingPerPage());

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.api-tokens.index', [
      'title' => 'CMS API Tokens',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('CMS API Tokens'),
      'tokens' => $tokens,
      'totalCount' => CmsApiToken::query()->count(),
      'currentCmsUrl' => url('/'),
      'createdToken' => session('created_cms_api_token'),
      'createdTokenName' => session('created_cms_api_token_name'),
    ]);
  }

  public function store(CmsApiTokenRequest $request): RedirectResponse
  {
    $issuedToken = $this->issuer->issue($request->tokenName(), $request->user());

    return redirect()
      ->route('admin.system.api-tokens.index')
      ->with('status', 'CMS API token created. Copy it now; it will not be shown again.')
      ->with('created_cms_api_token', $issuedToken->plainToken)
      ->with('created_cms_api_token_name', $issuedToken->record->name);
  }

  public function revoke(CmsApiToken $token): RedirectResponse
  {
    if (! $token->isRevoked()) {
      $token->forceFill(['revoked_at' => now()])->save();
    }

    return redirect()
      ->route('admin.system.api-tokens.index')
      ->with('status', 'CMS API token revoked.');
  }
}
