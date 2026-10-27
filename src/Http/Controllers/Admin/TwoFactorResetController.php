<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use WebBlocks\Cms\Actions\Auth\ResetTwoFactor;
use WebBlocks\Cms\Http\Requests\Auth\ResetTwoFactorRequest;

final class TwoFactorResetController extends Controller
{
  public function store(ResetTwoFactorRequest $request, ResetTwoFactor $reset): RedirectResponse
  {
    $response = to_route('admin.users.edit', ['user' => $request->route('user')]);
    if (! $reset->fromPanel($request, $request->target(), $request->validated())) {
      return $response->withErrors(['code' => __('webblocks-cms::two-factor.reset_invalid')]);
    }

    return $response->with('status', __('webblocks-cms::two-factor.reset_done'));
  }
}
