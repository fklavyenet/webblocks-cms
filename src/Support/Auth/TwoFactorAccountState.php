<?php

namespace WebBlocks\Cms\Support\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TwoFactorAccountState
{
  public const SESSION = 'wbcms.two_factor.account_version';

  public function state(Authenticatable $user): ?object
  {
    return Schema::hasTable('wbcms_two_factor_account_states')
      ? DB::table('wbcms_two_factor_account_states')->where('user_id', $user->getAuthIdentifier())->first() : null;
  }

  public function stamp(Request $request, Authenticatable $user): void
  {
    $request->session()->put(self::SESSION, $this->state($user)?->session_version ?? '');
  }

  public function current(Request $request, Authenticatable $user): bool
  {
    return hash_equals($this->state($user)?->session_version ?? '', (string) $request->session()->get(self::SESSION, ''));
  }
}
