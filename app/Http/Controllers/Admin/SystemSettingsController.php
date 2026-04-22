<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Support\System\InstalledVersionStore;
use App\Support\System\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
        private readonly InstalledVersionStore $installedVersionStore,
    ) {}

    public function edit(): View
    {
        return view('admin.system.settings', [
            'settings' => [
                'app_name' => old('app_name', $this->systemSettings->appName()),
                'app_slogan' => old('app_slogan', $this->systemSettings->appSlogan()),
                'default_locale' => old('default_locale', $this->systemSettings->defaultLocaleCode()),
                'timezone' => old('timezone', $this->systemSettings->timezone()),
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
