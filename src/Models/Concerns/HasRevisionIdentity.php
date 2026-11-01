<?php

namespace WebBlocks\Cms\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use WeakMap;

trait HasRevisionIdentity
{
  private static ?WeakMap $revisionIdentityColumns = null;

  public static function bootHasRevisionIdentity(): void
  {
    static::creating(function (self $model): void {
      // Old-schema migration fixtures and the installer may create records before
      // the additive identity migration. Cache by PDO, never by database name.
      $pdo = $model->getConnection()->getPdo();
      self::$revisionIdentityColumns ??= new WeakMap;
      $table = $model->getTable();
      $columns = self::$revisionIdentityColumns[$pdo] ?? [];
      $available = $columns[$table] ??= Schema::connection($model->getConnectionName())->hasColumn($table, 'revision_key');
      self::$revisionIdentityColumns[$pdo] = $columns;
      if ($available) {
        $model->revision_key ??= (string) Str::uuid();
        if (! Str::isUuid($model->revision_key)) {
          throw new LogicException('A revision identity must be a UUID.');
        }
      }
    });
    static::updating(function (self $model): void {
      if ($model->isDirty('revision_key')) {
        throw new LogicException('An existing revision identity cannot be changed.');
      }
    });
    static::replicating(function (self $model): void {
      unset($model->revision_key);
    });
  }

  public static function clearRevisionIdentitySchemaCache(): void
  {
    self::$revisionIdentityColumns = null;
  }

  /** Internal same-entity restore/candidate/staging path; never mass assignable. */
  public function withRevisionIdentity(?string $key): static
  {
    if ($this->exists || ($key !== null && ! Str::isUuid($key))) {
      throw new LogicException('Only a new record may receive a valid historical revision identity.');
    }
    if ($key !== null) {
      $this->revision_key = strtolower($key);
    }

    return $this;
  }
}
