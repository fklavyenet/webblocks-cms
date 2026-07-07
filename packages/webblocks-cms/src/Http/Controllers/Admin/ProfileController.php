<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\ProfilePasswordUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\ProfileUpdateRequest;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;

class ProfileController extends Controller
{
  public function edit(AdminLocaleResolver $adminLocaleResolver): View
  {
    return view('webblocks-cms::admin.profile.edit', [
      'user' => request()->user(),
      'adminLocaleOptions' => $adminLocaleResolver->options(),
      'adminLocalePreferencesAvailable' => $adminLocaleResolver->userPreferencesAvailable(),
    ]);
  }

  public function update(ProfileUpdateRequest $request, AdminLocaleResolver $adminLocaleResolver): RedirectResponse
  {
    /** @var User $user */
    $user = $request->user();

    $user->fill($request->safe()->only(['name', 'email']));

    if ($adminLocaleResolver->userPreferencesAvailable()) {
      $user->admin_locale = $request->validated('admin_locale');
    }

    $user->save();

    return redirect()->route('admin.profile.edit')->with('status_key', 'profile.updated');
  }

  public function updatePassword(ProfilePasswordUpdateRequest $request): RedirectResponse
  {
    /** @var User $user */
    $user = $request->user();

    $user->forceFill([
      'password' => $request->validated('new_password'),
      'remember_token' => Str::random(60),
    ])->save();

    $request->session()->regenerate();

    return redirect()->route('admin.profile.edit')->with('status_key', 'profile.password_updated');
  }
}
