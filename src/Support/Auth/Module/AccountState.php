<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AccountState
{
  public function __construct(private readonly Storage $storage) {}

  public function state(Authenticatable $user): ?object
  {
    return Schema::hasTable($this->storage->tableName('account_states'))
      ? $this->storage->table('account_states')->where('user_id', $user->getAuthIdentifier())->first() : null;
  }

  public function stamp(Request $request, Authenticatable $user): void
  {
    $request->session()->put($this->storage->sessionKey('account_version'), $this->state($user)?->session_version ?? '');
  }

  public function current(Request $request, Authenticatable $user): bool
  {
    return hash_equals($this->state($user)?->session_version ?? '', (string) $request->session()->get($this->storage->sessionKey('account_version'), ''));
  }
}
