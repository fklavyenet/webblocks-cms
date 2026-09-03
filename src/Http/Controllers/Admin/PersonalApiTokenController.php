<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\PersonalApiTokenRequest;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalApiTokens\PersonalApiTokenPolicy;

class PersonalApiTokenController extends Controller
{
  public function index(PersonalApiTokenPolicy $policy): View
  {
    $user = request()->user();

    return view('webblocks-cms::admin.profile.api-tokens', [
      'tokens' => CmsApiToken::query()->where('token_type', 'personal')->where('created_by_user_id', $user->id)->latest()->get(),
      'sites' => $user->accessibleSites(),
      'capabilities' => $policy->grantable($user),
      'capabilityLabels' => app(CmsApiTokenCapabilities::class)->labelsAll(),
      'createdToken' => session('created_personal_api_token'),
      'apiBaseUrl' => url('/webadmin/api'),
    ]);
  }

  public function store(PersonalApiTokenRequest $request, CmsApiTokenIssuer $issuer): RedirectResponse
  {
    $issued = $issuer->issue(
      trim((string) $request->validated('name')),
      $request->user(),
      array_values(array_unique($request->validated('capabilities'))),
      'personal',
      array_values(array_unique($request->validated('site_ids'))),
      now()->addDays((int) $request->validated('expires_in_days')),
    );

    return redirect()->route('admin.profile.api-tokens.index')
      ->with('status_key', 'profile.api_tokens.created')
      ->with('created_personal_api_token', $issued->plainToken);
  }

  public function revoke(CmsApiToken $token): RedirectResponse
  {
    $this->ownedToken($token)->forceFill(['revoked_at' => now()])->save();

    return back()->with('status_key', 'profile.api_tokens.revoked');
  }

  public function destroy(CmsApiToken $token): RedirectResponse
  {
    $this->ownedToken($token)->delete();

    return back()->with('status_key', 'profile.api_tokens.deleted');
  }

  private function ownedToken(CmsApiToken $token): CmsApiToken
  {
    abort_unless($token->isPersonal() && (int) $token->created_by_user_id === (int) request()->user()->id, 404);

    return $token;
  }
}
