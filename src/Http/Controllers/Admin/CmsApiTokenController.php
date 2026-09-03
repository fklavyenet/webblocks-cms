<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\CmsApiTokenRequest;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Plugins\PluginApiCapabilityRegistrar;
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
    $schemaReady = $this->apiTokenSchemaReady();
    $activitySchemaReady = $this->apiTokenActivitySchemaReady();
    $tokens = new LengthAwarePaginator([], 0, $this->systemSettings->adminListingPerPage());
    $totalCount = 0;

    if ($schemaReady) {
      $tokens = CmsApiToken::query()
        ->where(fn ($query) => $query->whereNull('token_type')->orWhere('token_type', 'system'))
        ->with('creator')
        ->orderByRaw('case when revoked_at is null then 0 else 1 end')
        ->latest()
        ->paginate($this->systemSettings->adminListingPerPage());

      AdminPagination::redirectOutOfRange($tokens);

      if ($activitySchemaReady) {
        $tokens->getCollection()->each(function (CmsApiToken $token): void {
          $token->setRelation(
            'activityLogs',
            $token->activityLogs()
              ->latest('occurred_at')
              ->latest('id')
              ->limit(10)
              ->get()
          );
        });
      }

      $totalCount = CmsApiToken::query()->count();
    }

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.api-tokens.index', [
      'title' => 'CMS API Tokens',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('CMS API Tokens'),
      'tokens' => $tokens,
      'totalCount' => $totalCount,
      'apiBaseUrl' => url('/webadmin/api'),
      'defaultCapabilities' => CmsApiTokenCapabilities::DEFAULT,
      'advancedCapabilities' => app(CmsApiTokenCapabilities::class)->advancedGrantable(),
      'capabilityGroups' => $this->capabilityGroups(),
      'capabilityLabels' => app(CmsApiTokenCapabilities::class)->labelsAll(),
      'capabilitiesPresenter' => app(CmsApiTokenCapabilities::class),
      'createdToken' => session('created_cms_api_token'),
      'createdTokenName' => session('created_cms_api_token_name'),
      'schemaReady' => $schemaReady,
      'activitySchemaReady' => $activitySchemaReady,
    ]);
  }

  public function store(CmsApiTokenRequest $request): RedirectResponse
  {
    if (! $this->apiTokenSchemaReady()) {
      return redirect()
        ->route('admin.system.api-tokens.index')
        ->withErrors(['name' => 'CMS API token storage is not ready. Run System Update again or contact your CMS operator.']);
    }

    $issuedToken = $this->issuer->issue($request->tokenName(), $request->user(), $request->tokenCapabilities());

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

  public function update(CmsApiTokenRequest $request, CmsApiToken $token): RedirectResponse
  {
    $token->forceFill([
      'name' => $request->tokenName(),
      'capabilities' => $request->tokenCapabilities(),
    ])->save();

    return redirect()
      ->route('admin.system.api-tokens.index')
      ->with('status', 'CMS API token updated.');
  }

  public function destroy(CmsApiToken $token): RedirectResponse
  {
    $token->delete();

    return redirect()
      ->route('admin.system.api-tokens.index')
      ->with('status', 'CMS API token deleted.');
  }

  private function apiTokenSchemaReady(): bool
  {
    if (! Schema::hasTable('wbcms_cms_api_tokens')) {
      return false;
    }

    foreach (['id', 'name', 'token_hash', 'token_preview', 'token_type', 'capabilities', 'allowed_site_ids', 'expires_at', 'created_by_user_id', 'last_used_at', 'last_used_ip', 'last_used_user_agent', 'revoked_at', 'created_at', 'updated_at'] as $column) {
      if (! Schema::hasColumn('wbcms_cms_api_tokens', $column)) {
        return false;
      }
    }

    return true;
  }

  /**
   * @return array<int, array{key: string, label: string, description: string, capabilities: array<int, string>}>
   */
  private function capabilityGroups(): array
  {
    return [
      [
        'key' => 'page-building',
        'label' => 'Page building',
        'description' => 'Default draft content, navigation, Shared Slots, media discovery, and site presentation permissions.',
        'capabilities' => CmsApiTokenCapabilities::DEFAULT,
      ],
      // Enabled plugins (e.g. WebBlocks Commerce) contribute their own groups below.
      [
        'key' => 'site-feedback',
        'label' => 'Site assets and feedback',
        'description' => 'Physical site CSS/JS edits, per-page asset attachment, and public engagement review permissions.',
        'capabilities' => [
          CmsApiTokenCapabilities::SITE_ASSETS_READ,
          CmsApiTokenCapabilities::SITE_ASSETS_WRITE,
          CmsApiTokenCapabilities::PAGE_ASSETS_WRITE,
          CmsApiTokenCapabilities::ENGAGEMENT_READ,
          CmsApiTokenCapabilities::ENGAGEMENT_MODERATE,
        ],
      ],
      [
        'key' => 'plugins',
        'label' => 'Plugin lifecycle',
        'description' => 'Install, enable, setup, disable, or uninstall manually uploaded plugins.',
        'capabilities' => [
          CmsApiTokenCapabilities::PLUGINS_READ,
          CmsApiTokenCapabilities::PLUGINS_INSTALL,
          CmsApiTokenCapabilities::PLUGINS_MANAGE,
          CmsApiTokenCapabilities::PLUGINS_SETUP,
          CmsApiTokenCapabilities::PLUGINS_UNINSTALL,
        ],
      ],
      [
        'key' => 'applications',
        'label' => 'Embedded Applications',
        'description' => 'Discover, register, update, or delete trusted browser application definitions.',
        'capabilities' => [
          CmsApiTokenCapabilities::APPLICATIONS_READ,
          CmsApiTokenCapabilities::APPLICATIONS_WRITE,
          CmsApiTokenCapabilities::APPLICATIONS_DELETE,
        ],
      ],
      [
        'key' => 'media',
        'label' => 'Media management',
        'description' => 'Upload, edit metadata, replace, move, and delete Media Library records.',
        'capabilities' => [
          CmsApiTokenCapabilities::MEDIA_WRITE,
          CmsApiTokenCapabilities::MEDIA_UPLOAD,
          CmsApiTokenCapabilities::MEDIA_REPLACE,
          CmsApiTokenCapabilities::MEDIA_MOVE,
          CmsApiTokenCapabilities::MEDIA_DELETE,
        ],
      ],
      [
        'key' => 'destructive',
        'label' => 'Publishing and destructive actions',
        'description' => 'Publish content or delete pages/navigation items. Grant only when explicitly needed.',
        'capabilities' => [
          CmsApiTokenCapabilities::NAVIGATION_DELETE,
          CmsApiTokenCapabilities::CONTENT_PUBLISH,
          CmsApiTokenCapabilities::PAGES_DELETE,
          CmsApiTokenCapabilities::CONTENT_BLOCKS_DELETE,
        ],
      ],
      [
        'key' => 'system-safety',
        'label' => 'System safety',
        'description' => 'Create a full system backup restore point before a large or destructive content apply. Grant only when explicitly needed.',
        'capabilities' => [
          CmsApiTokenCapabilities::BACKUPS_CREATE,
          CmsApiTokenCapabilities::BACKUPS_READ,
          CmsApiTokenCapabilities::BACKUPS_SETTINGS_WRITE,
          CmsApiTokenCapabilities::BACKUPS_DELETE,
        ],
      ],
      ...app(PluginApiCapabilityRegistrar::class)->groups(),
    ];
  }

  private function apiTokenActivitySchemaReady(): bool
  {
    if (! Schema::hasTable('wbcms_cms_api_token_activity_logs')) {
      return false;
    }

    foreach (['id', 'cms_api_token_id', 'occurred_at', 'status', 'method', 'path', 'route_name', 'required_capability', 'ip', 'user_agent', 'created_at', 'updated_at'] as $column) {
      if (! Schema::hasColumn('wbcms_cms_api_token_activity_logs', $column)) {
        return false;
      }
    }

    return true;
  }
}
