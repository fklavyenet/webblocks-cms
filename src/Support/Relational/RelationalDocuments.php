<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\CmsModel;

class RelationalDocuments
{
  private array $availability = [];

  public function clearAvailability(): void
  {
    $this->availability = [];
  }

  public function available(array $definition): bool
  {
    if (! app()->bound('db.schema')) {
      return false;
    }
    $name = $definition['table'];

    return $this->availability[$name] ??= Schema::hasTable($name);
  }

  public function decoded(mixed $value): mixed
  {
    if (! is_string($value)) {
      return $value;
    }
    $decoded = json_decode($value, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
  }

  public function read(CmsModel $model, array $definition, mixed $legacy): mixed
  {
    if (! $model->exists || ! $this->available($definition)) {
      return $legacy;
    }
    $row = DB::table($definition['table'])->where($definition['owner'], $model->getKey())->first();
    if ($row === null || $row->document_kind === 'legacy') {
      return $legacy;
    }
    if ($row->document_kind === 'null') {
      return null;
    }
    $result = is_array($legacy) ? $legacy : [];
    foreach ($definition['groups'] as $group) {
      $kind = $row->{$group.'_kind'};
      if ($kind === null) {
        unset($result[$group]);
      } elseif ($kind === 'null') {
        $result[$group] = null;
      } elseif ($kind === 'array' && ! is_array($result[$group] ?? null)) {
        $result[$group] = [];
      }
    }
    foreach ($definition['fields'] as $path => [$column, $type]) {
      $kind = $row->{$column.'_kind'};
      if ($kind === 'legacy' || (str_contains($path, '.') && in_array($row->{explode('.', $path)[0].'_kind'}, ['null', 'legacy'], true))) {
        continue;
      }
      if ($kind !== null) {
        Arr::set($result, $path, $this->scalar($row, $column, $kind));
      } else {
        Arr::forget($result, $path);
      }
    }
    if ($definition['lists'] !== []) {
      $items = DB::table('wbcms_page_runtime_items')->where('page_id', $model->getKey())->orderBy('position')->get()->groupBy('collection');
      foreach ($definition['lists'] as $path) {
        $column = str_replace('.', '_', $path);
        $kind = $row->{$column.'_kind'};
        if ($kind === 'legacy' || in_array($row->{explode('.', $path)[0].'_kind'}, ['null', 'legacy'], true)) {
          continue;
        }
        if ($kind === 'array') {
          Arr::set($result, $path, $items->get($path, collect())->pluck('value')->all());
        } elseif ($kind === 'null') {
          Arr::set($result, $path, null);
        } else {
          Arr::forget($result, $path);
        }
      }
    }

    return $result;
  }

  private function scalar(object $row, string $column, string $kind): mixed
  {
    $value = $row->{$column};

    return match ($kind) {
      'null' => null,
      'string' => $row->{$column.'_text'} ?? (string) $value,
      'boolean' => (bool) $value,
      'double' => (float) ($row->{$column.'_text'} ?? $value),
      default => (int) ($row->{$column.'_text'} ?? $value),
    };
  }

  public function write(CmsModel $model, array $definition, mixed $document): void
  {
    $row = ['document_kind' => $document === null ? 'null' : (is_array($document) ? 'array' : 'legacy')];
    foreach ($definition['groups'] as $group) {
      $row[$group.'_kind'] = array_key_exists($group, is_array($document) ? $document : [])
        ? ($document[$group] === null ? 'null' : (is_array($document[$group]) ? 'array' : 'legacy')) : null;
    }
    foreach ($definition['fields'] as $path => [$column, $type]) {
      $row[$column] = null;
      $row[$column.'_kind'] = null;
      $row[$column.'_text'] = null;
      if (! is_array($document) || ! Arr::has($document, $path)) {
        continue;
      }
      $value = Arr::get($document, $path);
      if ($value === null) {
        $row[$column.'_kind'] = 'null';
      } elseif (in_array($type, ['string', 'text'], true) && is_string($value) && ($type === 'text' || mb_strlen($value) <= 190)) {
        $row[$column] = $value;
        $row[$column.'_kind'] = 'string';
      } elseif ($type === 'integer' && (is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false))) {
        $row[$column] = (int) $value;
        $row[$column.'_kind'] = is_string($value) ? 'string' : 'integer';
        $row[$column.'_text'] = is_string($value) ? $value : null;
      } elseif ($type === 'boolean' && (is_bool($value) || is_int($value) || is_string($value))) {
        $row[$column] = filter_var($value, FILTER_VALIDATE_BOOL);
        $row[$column.'_kind'] = is_string($value) ? 'string' : (is_bool($value) ? 'boolean' : 'integer');
        $row[$column.'_text'] = is_string($value) || is_int($value) ? (string) $value : null;
      } else {
        // Invalid historical types stay in the retained compatibility column.
        $row[$column.'_kind'] = 'legacy';
      }
    }
    $items = [];
    foreach ($definition['lists'] as $path) {
      $column = str_replace('.', '_', $path);
      $row[$column.'_kind'] = null;
      if (! is_array($document) || ! Arr::has($document, $path)) {
        continue;
      }
      $value = Arr::get($document, $path);
      $valid = is_array($value) && array_is_list($value) && collect($value)->every(fn ($item) => is_string($item) && mb_strlen($item) <= 190);
      $row[$column.'_kind'] = $value === null ? 'null' : ($valid ? 'array' : 'legacy');
      if ($valid) {
        foreach ($value as $position => $item) {
          $items[] = ['page_id' => $model->getKey(), 'collection' => $path, 'position' => $position, 'value' => $item];
        }
      }
    }
    foreach ($definition['relations'] as $column => $table) {
      $row[$column] = match ($column) {
        'resolved_layout_id' => $this->resolve($table, 'handle', $row['layout_handle']),
        'resolved_shared_slot_id' => $this->resolve($table, 'id', $row['shared_slot_reference']),
        'resolved_staged_source_id' => $this->resolve($table, 'id', $row['staged_source_reference']),
        'resolved_restore_source_id' => $this->resolve($table, 'id', $row['restore_source_reference']),
        'resolved_restore_revision_id' => $this->resolve($table, 'id', $row['restore_revision_reference']),
        'resolved_promoted_page_id' => $this->resolve($table, 'id', $row['staged_promoted_reference']),
        'resolved_restore_candidate_id' => $this->resolve($table, 'id', $row['restore_candidate_reference']),
        'resolved_application_id' => $this->resolve($table, 'handle', $row['application_handle']),
      };
    }
    DB::table($definition['table'])->updateOrInsert([$definition['owner'] => $model->getKey()], $row);
    if ($definition['lists'] !== []) {
      DB::table('wbcms_page_runtime_items')->where('page_id', $model->getKey())->delete();
      foreach (array_chunk($items, 500) as $batch) {
        DB::table('wbcms_page_runtime_items')->insert($batch);
      }
    }
  }

  private function resolve(string $table, string $key, mixed $value): ?int
  {
    return $value !== null && Schema::hasTable($table) ? DB::table($table)->where($key, $value)->value('id') : null;
  }

  public function where(Builder $query, array $definition, string $path, mixed $value, bool $null = false): Builder
  {
    $group = in_array($path, $definition['groups'], true);
    if (! $this->available($definition) || (! isset($definition['fields'][$path]) && ! $group)) {
      return $null ? $query->whereNull('settings->'.str_replace('.', '->', $path)) : $query->where('settings->'.str_replace('.', '->', $path), $value);
    }
    $column = $group ? $path : $definition['fields'][$path][0];
    $ownerTable = $query->getModel()->getTable();
    $table = $definition['table'];

    $legacyPath = 'settings->'.str_replace('.', '->', $path);
    $groupColumn = str_contains($path, '.') ? explode('.', $path)[0].'_kind' : null;

    return $query->where(function ($scope) use ($table, $definition, $ownerTable, $column, $value, $null, $legacyPath, $groupColumn): void {
      $owner = fn ($row) => $row->selectRaw('1')->from($table)->whereColumn($table.'.'.$definition['owner'], $ownerTable.'.id');
      $scope->whereExists(function ($row) use ($owner, $column, $value, $null, $groupColumn): void {
        $owner($row)->where('document_kind', '!=', 'legacy');
        if ($groupColumn !== null) {
          $row->where(fn ($group) => $group->whereNull($groupColumn)->orWhere($groupColumn, '!=', 'legacy'));
        }
        $null ? $row->where(function ($missing) use ($column): void {
          $missing->whereNull($column.'_kind')->orWhere($column.'_kind', 'null');
        }) : $row->where($column, $value)->whereNotNull($column.'_kind')->where($column.'_kind', '!=', 'legacy');
      })->orWhere(function ($legacy) use ($owner, $column, $groupColumn, $null, $legacyPath, $value): void {
        $legacy->where(function ($fallback) use ($owner, $column, $groupColumn): void {
          $fallback->whereNotExists(fn ($row) => $owner($row))->orWhereExists(function ($row) use ($owner, $column, $groupColumn): void {
            $owner($row)->where(function ($invalid) use ($column, $groupColumn): void {
              $invalid->where('document_kind', 'legacy')->orWhere($column.'_kind', 'legacy');
              if ($groupColumn !== null) {
                $invalid->orWhere($groupColumn, 'legacy');
              }
            });
          });
        });
        $null ? $legacy->whereNull($legacyPath) : $legacy->where($legacyPath, $value);
      });
    });
  }
}
