<?php

namespace WebBlocks\Cms\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Policies\TwoFactorPolicy;

final class UpdateTwoFactorPolicy
{
  /** Return whether any existing role requirement was removed. */
  public function handle(Authenticatable $actor, array $data): bool
  {
    abort_unless(app(TwoFactorPolicy::class)->configure($actor), 403);

    return DB::transaction(function () use ($data): bool {
      $previous = DB::table('wbcms_two_factor_policy')->where('id', 1)->lockForUpdate()->first();
      $previousRoles = DB::table('wbcms_two_factor_role_requirements')->pluck('role_id')->all();
      DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => (bool) $data['require_super_admin']]);
      DB::table('wbcms_two_factor_role_requirements')->delete();
      foreach (array_unique($data['role_ids'] ?? []) as $id) {
        DB::table('wbcms_two_factor_role_requirements')->insert(['role_id' => $id]);
      }

      return ((bool) $previous?->require_super_admin && ! (bool) $data['require_super_admin'])
        || array_diff($previousRoles, $data['role_ids'] ?? []) !== [];
    });
  }
}
