<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $this->repairCommentEntries();
    $this->repairContentRatings();
  }

  public function down(): void
  {
    //
  }

  private function repairCommentEntries(): void
  {
    if (! Schema::hasTable('wbcms_comment_entries')) {
      return;
    }

    Schema::table('wbcms_comment_entries', function (Blueprint $table): void {
      $this->index($table, 'wbcms_comment_entries', ['status', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['site_id', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['page_id', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['block_id', 'status', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['visitor_hash', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['ip_hash', 'created_at']);
      $this->index($table, 'wbcms_comment_entries', ['spam_score', 'created_at']);
    });

    $this->foreign('wbcms_comment_entries', 'site_id', 'wbcms_sites');
    $this->foreign('wbcms_comment_entries', 'page_id', 'wbcms_pages');
    $this->foreign('wbcms_comment_entries', 'block_id', 'wbcms_blocks');
    $this->foreign('wbcms_comment_entries', 'approved_by_user_id', 'users');
  }

  private function repairContentRatings(): void
  {
    if (! Schema::hasTable('wbcms_content_ratings')) {
      return;
    }

    Schema::table('wbcms_content_ratings', function (Blueprint $table): void {
      $this->unique($table, 'wbcms_content_ratings', ['block_id', 'visitor_hash'], 'content_ratings_block_visitor_unique');
      $this->index($table, 'wbcms_content_ratings', ['status', 'created_at']);
      $this->index($table, 'wbcms_content_ratings', ['site_id', 'created_at']);
      $this->index($table, 'wbcms_content_ratings', ['page_id', 'created_at']);
      $this->index($table, 'wbcms_content_ratings', ['block_id', 'status', 'rating_value']);
      $this->index($table, 'wbcms_content_ratings', ['ip_hash', 'created_at']);
    });

    $this->foreign('wbcms_content_ratings', 'site_id', 'wbcms_sites');
    $this->foreign('wbcms_content_ratings', 'page_id', 'wbcms_pages');
    $this->foreign('wbcms_content_ratings', 'block_id', 'wbcms_blocks');
  }

  /**
   * @param  array<int, string>  $columns
   */
  private function index(Blueprint $table, string $tableName, array $columns): void
  {
    $indexName = $tableName.'_'.implode('_', $columns).'_index';

    if (! $this->hasIndex($tableName, $indexName) && ! $this->hasIndexOnColumns($tableName, $columns, false)) {
      $table->index($columns, $indexName);
    }
  }

  /**
   * @param  array<int, string>  $columns
   */
  private function unique(Blueprint $table, string $tableName, array $columns, string $indexName): void
  {
    if (! $this->hasIndex($tableName, $indexName) && ! $this->hasIndexOnColumns($tableName, $columns, true)) {
      $table->unique($columns, $indexName);
    }
  }

  private function foreign(string $table, string $column, string $referencedTable): void
  {
    if (! $this->usesMysql() || ! Schema::hasTable($referencedTable) || $this->hasForeignKey($table, $column, $referencedTable)) {
      return;
    }

    Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable): void {
      $blueprint->foreign($column)->references('id')->on($referencedTable)->nullOnDelete();
    });
  }

  private function hasIndex(string $table, string $index): bool
  {
    $driver = DB::getDriverName();

    return match ($driver) {
      'mysql', 'mariadb' => DB::table('information_schema.statistics')
        ->where('table_schema', DB::raw('database()'))
        ->where('table_name', $table)
        ->where('index_name', $index)
        ->exists(),
      'sqlite' => collect(DB::select("pragma index_list('{$table}')"))
        ->contains(fn (object $row): bool => ($row->name ?? null) === $index),
      default => false,
    };
  }

  /**
   * @param  array<int, string>  $columns
   */
  private function hasIndexOnColumns(string $table, array $columns, bool $unique): bool
  {
    $driver = DB::getDriverName();

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
      $indexes = DB::table('information_schema.statistics')
        ->selectRaw('INDEX_NAME as index_name, COLUMN_NAME as column_name, SEQ_IN_INDEX as seq_in_index, NON_UNIQUE as non_unique')
        ->where('table_schema', DB::raw('database()'))
        ->where('table_name', $table)
        ->orderBy('index_name')
        ->orderBy('seq_in_index')
        ->get()
        ->groupBy(fn (object $row): string => (string) $row->index_name);

      foreach ($indexes as $indexRows) {
        $first = $indexRows->first();

        if ($unique && (int) ($first->non_unique ?? 1) !== 0) {
          continue;
        }

        if ($indexRows->map(fn (object $row): string => (string) $row->column_name)->values()->all() === $columns) {
          return true;
        }
      }

      return false;
    }

    if ($driver === 'sqlite') {
      foreach (DB::select("pragma index_list('{$table}')") as $index) {
        $name = (string) ($index->name ?? '');

        if ($name === '' || ($unique && (int) ($index->unique ?? 0) !== 1)) {
          continue;
        }

        $indexColumns = collect(DB::select("pragma index_info('{$name}')"))
          ->sortBy('seqno')
          ->map(fn (object $row): string => (string) $row->name)
          ->values()
          ->all();

        if ($indexColumns === $columns) {
          return true;
        }
      }
    }

    return false;
  }

  private function hasForeignKey(string $table, string $column, string $referencedTable): bool
  {
    if (! $this->usesMysql()) {
      return true;
    }

    return DB::table('information_schema.KEY_COLUMN_USAGE')
      ->where('TABLE_SCHEMA', DB::raw('database()'))
      ->where('TABLE_NAME', $table)
      ->where('COLUMN_NAME', $column)
      ->where('REFERENCED_TABLE_NAME', $referencedTable)
      ->exists();
  }

  private function usesMysql(): bool
  {
    return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
  }
};
