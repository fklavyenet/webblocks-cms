<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Actions\System\UpdateRequestSecuritySettings;
use WebBlocks\Cms\Http\Requests\Admin\RequestSecuritySettingsRequest;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class RequestSecuritySettingsController extends Controller
{
  public function update(RequestSecuritySettingsRequest $request, UpdateRequestSecuritySettings $update): RedirectResponse
  {
    $update->handle($request->validated());

    return redirect()->route('admin.system.settings.edit', ['tab' => 'security'])
      ->with('status', app(CmsTranslator::class)->admin('system_settings.security_saved', app(AdminLocaleResolver::class)->locale()));
  }
}
