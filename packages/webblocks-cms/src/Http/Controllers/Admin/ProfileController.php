<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\ProfilePasswordUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\ProfileUpdateRequest;

class ProfileController extends Controller
{
  public function edit(): View
  {
    return view('webblocks-cms::admin.profile.edit', [
      'user' => request()->user(),
    ]);
  }

  public function update(ProfileUpdateRequest $request): RedirectResponse
  {
    /** @var User $user */
    $user = $request->user();

    $user->fill($request->safe()->only(['name', 'email']));
    $user->save();

    return redirect()->route('admin.profile.edit')->with('status', 'Profile updated successfully.');
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

    return redirect()->route('admin.profile.edit')->with('status', 'Password updated successfully.');
  }
}
