<?php

namespace WebBlocks\Cms\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Policies\TwoFactorPolicy;

final class UpdateTwoFactorPolicy
{
  public function handle(Authenticatable $actor, array $data): void
  {
    abort_unless(app(TwoFactorPolicy::class)->configure($actor), 403);
    DB::transaction(function () use ($data): void {
      DB::table('wbcms_two_factor_policy')->where('id', 1)->lockForUpdate()->first();
      DB::table('wbcms_two_factor_policy')->where('id', 1)->update(['require_super_admin' => (bool) $data['require_super_admin']]);
      DB::table('wbcms_two_factor_role_requirements')->delete();
      foreach (array_unique($data['role_ids'] ?? []) as $id) {
        DB::table('wbcms_two_factor_role_requirements')->insert(['role_id' => $id]);
      }
    });
  }
}
