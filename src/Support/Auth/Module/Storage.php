<?php

namespace WebBlocks\Cms\Support\Auth\Module;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class Storage
{
  public function __construct(
    private readonly string $tablePrefix,
    private readonly string $sessionPrefix,
    public readonly string $userTable = 'users',
    public readonly string $userKey = 'id',
  ) {}

  public function tableName(string $name): string
  {
    return $this->tablePrefix.$name;
  }

  public function table(string $name): Builder
  {
    return DB::table($this->tableName($name));
  }

  public function sessionKey(string $name): string
  {
    return $this->sessionPrefix.'.'.$name;
  }

  /** Call inside the transaction that changes the account's verification state. */
  public function lockUser(Authenticatable $user): void
  {
    DB::table($this->userTable)->where($this->userKey, $user->getAuthIdentifier())->lockForUpdate()->first();
  }
}
