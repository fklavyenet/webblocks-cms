<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\SystemSettingsRequest;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function edit(): View
    {
        return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.settings', [
            'title' => 'System Settings',
            'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
            'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('System Settings'),
            'settings' => [
                'project_name' => old('project_name', $this->systemSettings->projectName()),
                'project_tagline' => old('project_tagline', $this->systemSettings->projectTagline()),
                'default_locale' => old('default_locale', $this->systemSettings->defaultLocaleCode()),
                'timezone' => old('timezone', $this->systemSettings->timezone()),
                'admin_listing_per_page' => old('admin_listing_per_page', $this->systemSettings->adminListingPerPage()),
                'visitor_consent_banner_enabled' => old('visitor_consent_banner_enabled', $this->systemSettings->visitorConsentBannerEnabled()),
            ],
            'localeOptions' => $this->systemSettings->enabledLocaleOptions(),
            'timezoneOptions' => $this->systemSettings->timezoneOptions(),
            'installedVersionDisplay' => $this->installedVersionStore->displayVersion(),
            'environment' => app()->environment(),
        ]);
    }

    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        $this->systemSettings->save($request->settingsPayload());

        return redirect()
            ->route('admin.system.settings.edit')
            ->with('status', 'Settings updated successfully.');
    }
}
