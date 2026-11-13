<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;
use WebBlocks\Cms\Http\Requests\Admin\CmsMailTestEmailRequest;
use WebBlocks\Cms\Http\Requests\Admin\McpSettingsRequest;
use WebBlocks\Cms\Http\Requests\Admin\SystemSettingsRequest;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Policies\LogFormatPolicy;
use WebBlocks\Cms\Policies\VerificationEmailPolicy;
use WebBlocks\Cms\Support\Auth\TwoFactorAuthentication;
use WebBlocks\Cms\Support\Logging\LogFormatSettings;
use WebBlocks\Cms\Support\Mail\CmsMailSettingsResolver;
use WebBlocks\Cms\Support\Mail\CmsTestEmailSender;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\RequestSecuritySettings;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SystemSettingsController extends Controller
{
  public function __construct(
    private readonly SystemSettings $systemSettings,
    private readonly InstalledVersionStore $installedVersionStore,
    private readonly CmsMailSettingsResolver $mailSettingsResolver,
    private readonly AdminLocaleResolver $adminLocaleResolver,
  ) {}

  public function edit(McpConfiguration $mcp): View
  {
    $verificationLocale = request()->query('verification_locale', $this->adminLocaleResolver->packagedLocale(request()->user()));
    $verificationLocale = is_string($verificationLocale) && in_array($verificationLocale, AdminLocaleResolver::SUPPORTED_LOCALES, true) ? $verificationLocale : 'en';
    $canManageVerificationEmail = app(VerificationEmailPolicy::class)->update(request()->user());
    $customMail = $this->systemSettings->cmsMailSettings();
    $mailSummaries = [
      SystemSettings::CMS_MAIL_MODE_ENV => $this->mailSettingsResolver->environmentSummary(),
      SystemSettings::CMS_MAIL_MODE_CUSTOM => $this->mailSettingsResolver->customSummary($customMail),
    ];
    unset($customMail['password']);

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.system.settings', [
      'title' => 'System Settings',
      'adminProjectIdentity' => $this->systemSettings->adminProjectIdentity(),
      'adminBrowserTitle' => $this->systemSettings->adminBrowserTitle('System Settings'),
      'settings' => [
        'log_format' => old('log_format', app(LogFormatSettings::class)->mode()),
        'project_name' => old('project_name', $this->systemSettings->projectName()),
        'project_tagline' => old('project_tagline', $this->systemSettings->projectTagline()),
        'default_locale' => old('default_locale', $this->systemSettings->defaultLocaleCode()),
        'timezone' => old('timezone', $this->systemSettings->timezone()),
        'admin_listing_per_page' => old('admin_listing_per_page', $this->systemSettings->adminListingPerPage()),
        'visitor_consent_banner_enabled' => old('visitor_consent_banner_enabled', $this->systemSettings->visitorConsentBannerEnabled()),
      ],
      'customMail' => $customMail,
      'mailSummaries' => $mailSummaries,
      'mcpEnabled' => $mcp->enabled(),
      'mcpReady' => $mcp->ready(),
      'mcpResource' => $mcp->resource(),
      'mcpScopes' => array_diff_key(McpConfiguration::LABELS, [McpConfiguration::READ => true]),
      'mcpAvailableScopes' => $mcp->availableScopes(),
      'mailDiagnostics' => $this->mailSettingsResolver->diagnostics(),
      'canManageVerificationEmail' => $canManageVerificationEmail,
      'verificationLocale' => $verificationLocale,
      'verificationTemplates' => $canManageVerificationEmail ? app(VerificationEmailTemplates::class)->templates($verificationLocale) : [],
      'canManageLogFormat' => app(LogFormatPolicy::class)->update(request()->user()),
      'twoFactorAvailable' => app(TwoFactorAuthentication::class)->available(),
      'twoFactorPolicy' => app(TwoFactorAuthentication::class)->available() ? app(TwoFactorAuthentication::class)->policy() : [],
      'twoFactorRoles' => app(TwoFactorAuthentication::class)->available() ? CmsRole::query()->orderBy('name')->get() : collect(),
      'twoFactorEnabled' => request()->user() && app(TwoFactorAuthentication::class)->credential(request()->user())?->confirmed_at !== null,
      'securityLimits' => app(RequestSecuritySettings::class)->values(),
      'securityDefinitions' => RequestSecuritySettings::DEFINITIONS,
      'securityHasOverrides' => app(RequestSecuritySettings::class)->hasOverrides(),
      'cmsMailMailerOptions' => array_combine(CmsMailSettingsResolver::SUPPORTED_MAILERS, CmsMailSettingsResolver::SUPPORTED_MAILERS),
      'localeOptions' => $this->systemSettings->enabledLocaleOptions(),
      'verificationLocaleOptions' => AdminLocaleResolver::PACKAGED_OPTIONS,
      'timezoneOptions' => $this->systemSettings->timezoneOptions(),
      'installedVersionDisplay' => $this->installedVersionStore->displayVersion(),
      'environment' => app()->environment(),
    ]);
  }

  public function update(SystemSettingsRequest $request): RedirectResponse
  {
    $section = $request->validated('section');
    if (in_array($section, ['mail_mode', 'mail_custom'], true)) {
      DB::transaction(fn () => $this->systemSettings->save($request->settingsPayload()));

      $status = app(CmsTranslator::class)->admin(
        'system_settings.'.$section.'_saved',
        $this->adminLocaleResolver->locale(),
      );
      $response = redirect()->route('admin.system.settings.edit', [
        'tab' => 'mail', 'verification_locale' => $request->query('verification_locale'),
      ])->with('status', $status);
      $pending = $request->validated('cms_mail_pending_mode');

      return $section === 'mail_custom' && $pending ? $response->withInput(['cms_mail_pending_mode' => $pending]) : $response;
    }

    $this->systemSettings->save($request->settingsPayload());

    return redirect()
      ->route('admin.system.settings.edit', ['tab' => $section])
      ->with('status', $section === 'logging'
        ? __('webblocks-cms::admin.system_settings.logging_saved') : 'Settings updated successfully.');
  }

  public function updateMcp(McpSettingsRequest $request): RedirectResponse
  {
    DB::transaction(fn () => $this->systemSettings->save($request->settingsPayload()));

    return redirect()->route('admin.system.settings.edit', ['tab' => 'mcp'])->with('status', __('webblocks-cms::mcp.saved'));
  }

  public function sendMailTest(CmsMailTestEmailRequest $request, CmsTestEmailSender $sender): RedirectResponse
  {
    try {
      $sender->send($request->recipientEmail());
    } catch (Throwable) {
      return redirect()
        ->route('admin.system.settings.edit', ['tab' => 'mail'])
        ->withInput($request->only('recipient_email'))
        ->withErrors(['recipient_email' => 'The test email could not be sent. Please check CMS Mail settings.']);
    }

    return redirect()
      ->route('admin.system.settings.edit', ['tab' => 'mail'])
      ->with('status', 'Test email sent to '.$request->recipientEmail().'.');
  }
}
