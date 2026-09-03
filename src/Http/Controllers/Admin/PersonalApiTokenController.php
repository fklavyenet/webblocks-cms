<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
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
    $capabilities = $policy->grantable($user);
    $presenter = app(CmsApiTokenCapabilities::class);

    $tokens = CmsApiToken::query()->where('token_type', 'personal')->where('created_by_user_id', $user->id)->latest()->paginate(20);
    $activitySchemaReady = $this->activitySchemaReady();

    if ($activitySchemaReady) {
      $tokens->getCollection()->each(fn (CmsApiToken $token) => $token->setRelation(
        'activityLogs',
        $token->activityLogs()->latest('occurred_at')->latest('id')->limit(10)->get(),
      ));
    }

    return view('webblocks-cms::admin.profile.api-tokens', [
      'tokens' => $tokens,
      'sites' => $user->accessibleSites(),
      'capabilityGroups' => $this->capabilityGroups($capabilities),
      'capabilityLabels' => $presenter->labelsAll(),
      'capabilitiesPresenter' => $presenter,
      'createdToken' => session('created_personal_api_token'),
      'apiBaseUrl' => url('/webadmin/api'),
      'activitySchemaReady' => $activitySchemaReady,
    ]);
  }

  public function update(PersonalApiTokenRequest $request, CmsApiToken $token): RedirectResponse
  {
    $token = $this->ownedToken($token);
    $token->forceFill([
      'name' => trim((string) $request->validated('name')),
      'capabilities' => array_values(array_unique($request->validated('capabilities'))),
      'allowed_site_ids' => array_values(array_unique($request->validated('site_ids'))),
      'expires_at' => now()->addDays((int) $request->validated('expires_in_days')),
    ])->save();

    return back()->with('status_key', 'profile.api_tokens.updated');
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

  private function capabilityGroups(array $grantable): array
  {
    $groups = [
      ['key' => 'page-building', 'label' => 'Page building', 'description' => 'Draft content, navigation, Shared Slots, media discovery, and safe site presentation.', 'capabilities' => CmsApiTokenCapabilities::DEFAULT],
      ['key' => 'media', 'label' => 'Media management', 'description' => 'Upload, edit, replace, move, or delete accessible Media Library records.', 'capabilities' => [CmsApiTokenCapabilities::MEDIA_WRITE, CmsApiTokenCapabilities::MEDIA_UPLOAD, CmsApiTokenCapabilities::MEDIA_REPLACE, CmsApiTokenCapabilities::MEDIA_MOVE, CmsApiTokenCapabilities::MEDIA_DELETE]],
      ['key' => 'destructive', 'label' => 'Publishing and destructive actions', 'description' => 'Publish content or delete pages, blocks, navigation items, and Shared Slots.', 'capabilities' => [CmsApiTokenCapabilities::NAVIGATION_DELETE, CmsApiTokenCapabilities::SHARED_SLOTS_DELETE, CmsApiTokenCapabilities::CONTENT_PUBLISH, CmsApiTokenCapabilities::PAGES_DELETE, CmsApiTokenCapabilities::CONTENT_BLOCKS_DELETE]],
      ['key' => 'personal-site-feedback', 'label' => 'Site feedback', 'description' => 'Read and moderate public comments and ratings for allowed sites.', 'capabilities' => [CmsApiTokenCapabilities::ENGAGEMENT_READ, CmsApiTokenCapabilities::ENGAGEMENT_MODERATE]],
    ];

    return collect($groups)
      ->map(fn (array $group): array => $group + ['capabilities' => []])
      ->map(function (array $group) use ($grantable): array {
        $group['capabilities'] = array_values(array_intersect($group['capabilities'], $grantable));

        return $group;
      })
      ->filter(fn (array $group): bool => $group['capabilities'] !== [])
      ->values()
      ->all();
  }

  private function activitySchemaReady(): bool
  {
    return Schema::hasTable('wbcms_cms_api_token_activity_logs')
      && Schema::hasColumn('wbcms_cms_api_token_activity_logs', 'cms_api_token_id');
  }
}
