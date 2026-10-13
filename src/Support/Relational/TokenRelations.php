<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsApiToken;

class TokenRelations
{
  public const LISTS = [
    'capabilities' => ['wbcms_api_token_capabilities', 'string'],
    'allowed_site_ids' => ['wbcms_api_token_sites', 'integer'],
    'allowed_ip_ranges' => ['wbcms_api_token_networks', 'string'],
  ];

  private array $availability = [];

  public function clearAvailability(): void
  {
    $this->availability = [];
  }

  public function available(string $attribute): bool
  {
    if (! app()->bound('db.schema')) {
      return false;
    }

    return $this->availability[$attribute] ??= Schema::hasTable(self::LISTS[$attribute][0]);
  }

  public function read(CmsApiToken $token, string $attribute, mixed $legacy): mixed
  {
    if (! $token->exists || ! $this->available($attribute)) {
      return $legacy;
    }
    $mode = $token->getAttribute($attribute.'_mode');
    if ($mode === null || $mode === 'legacy') {
      return $legacy;
    }
    if ($mode === 'null') {
      return null;
    }

    return DB::table(self::LISTS[$attribute][0])->where('token_id', $token->getKey())->orderBy('position')->get()
      ->map(fn ($row) => self::LISTS[$attribute][1] === 'integer' ? ($row->original_text ?? (int) $row->value) : $row->value)->all();
  }

  public function write(CmsApiToken $token, string $attribute, mixed $value): void
  {
    [$table, $type] = self::LISTS[$attribute];
    $valid = is_array($value) && array_is_list($value) && collect($value)->every(fn ($item) => $type === 'string'
      ? is_string($item) && mb_strlen($item) <= 190
      : is_int($item) || (is_string($item) && filter_var($item, FILTER_VALIDATE_INT) !== false));
    $mode = $value === null ? 'null' : ($valid ? 'array' : 'legacy');
    DB::table($token->getTable())->where('id', $token->getKey())->update([$attribute.'_mode' => $mode]);
    $token->setAttribute($attribute.'_mode', $mode);
    DB::table($table)->where('token_id', $token->getKey())->delete();
    if ($valid) {
      $rows = [];
      foreach ($value as $position => $item) {
        $row = ['token_id' => $token->getKey(), 'position' => $position, 'value' => $type === 'integer' ? (int) $item : $item];
        if ($type === 'integer') {
          $row['original_text'] = is_string($item) ? $item : null;
          $row['resolved_site_id'] = DB::table('wbcms_sites')->where('id', (int) $item)->value('id');
        }
        $rows[] = $row;
      }
      foreach (array_chunk($rows, 500) as $batch) {
        DB::table($table)->insert($batch);
      }
    }
  }
}
