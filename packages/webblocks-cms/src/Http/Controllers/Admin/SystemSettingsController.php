<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\CmsMailTestEmailRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemSettingsRequest;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\Mail\CmsTestEmailSender;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemSettingsController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly InstalledVersionStore $installedVersionStore,
    private readonly CmsMailSettingsResolver $mailSettingsResolver,
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
        'cms_mail_mode' => old('cms_mail_mode', $this->systemSettings->cmsMailSettings()['mode']),
        'cms_mail_mailer' => old('cms_mail_mailer', $this->systemSettings->cmsMailSettings()['mailer']),
        'cms_mail_host' => old('cms_mail_host', $this->systemSettings->cmsMailSettings()['host']),
        'cms_mail_port' => old('cms_mail_port', $this->systemSettings->cmsMailSettings()['port']),
        'cms_mail_encryption' => old('cms_mail_encryption', $this->systemSettings->cmsMailSettings()['encryption']),
        'cms_mail_username' => old('cms_mail_username', $this->systemSettings->cmsMailSettings()['username']),
        'cms_mail_from_address' => old('cms_mail_from_address', $this->systemSettings->cmsMailSettings()['from_address']),
        'cms_mail_from_name' => old('cms_mail_from_name', $this->systemSettings->cmsMailSettings()['from_name']),
        'cms_mail_reply_to_address' => old('cms_mail_reply_to_address', $this->systemSettings->cmsMailSettings()['reply_to_address']),
        'cms_mail_timeout' => old('cms_mail_timeout', $this->systemSettings->cmsMailSettings()['timeout']),
        'cms_mail_password_configured' => $this->systemSettings->cmsMailPasswordConfigured(),
      ],
      'mailDiagnostics' => $this->mailSettingsResolver->diagnostics(),
      'cmsMailMailerOptions' => array_combine(CmsMailSettingsResolver::SUPPORTED_MAILERS, CmsMailSettingsResolver::SUPPORTED_MAILERS),
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

  public function sendMailTest(CmsMailTestEmailRequest $request, CmsTestEmailSender $sender): RedirectResponse
  {
    try {
      $sender->send($request->recipientEmail());
    } catch (Throwable) {
      return redirect()
        ->route('admin.system.settings.edit')
        ->withInput($request->only('recipient_email'))
        ->withErrors(['recipient_email' => 'The test email could not be sent. Please check CMS Mail settings.']);
    }

    return redirect()
      ->route('admin.system.settings.edit')
      ->with('status', 'Test email sent to '.$request->recipientEmail().'.');
  }
}
