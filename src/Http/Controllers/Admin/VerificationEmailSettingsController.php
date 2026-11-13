<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Actions\System\UpdateVerificationEmailTemplates;
use WebBlocks\Cms\Http\Requests\Admin\VerificationEmailSettingsRequest;
use WebBlocks\Cms\Support\Mail\VerificationEmailTemplates;

final class VerificationEmailSettingsController extends Controller
{
  public function update(VerificationEmailSettingsRequest $request, UpdateVerificationEmailTemplates $update, VerificationEmailTemplates $templates): RedirectResponse|Response|JsonResponse
  {
    $locale = $request->validated('locale');
    $values = $request->safe()->only(VerificationEmailTemplates::FIELDS);
    if ($request->validated('intent') === 'preview') {
      $message = $templates->message('123456', $locale, $values);
      $html = (string) $message->render();

      if ($request->expectsJson()) {
        return response()->json(['subject' => $message->subject, 'html' => $html])
          ->header('Cache-Control', 'no-store, private');
      }

      return response($html)
        ->header('Cache-Control', 'no-store, private')
        ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'");
    }

    $reset = $request->validated('intent') === 'reset';
    $update->execute($locale, $values, $reset);

    return redirect()->route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => $locale])
      ->with('status', __('webblocks-cms::two-factor.template_'.($reset ? 'reset_done' : 'saved')));
  }
}
