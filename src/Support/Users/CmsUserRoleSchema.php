<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Users;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CmsUserRoleSchema
{
  public function ensureNullableRole(): void
  {
    if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_admin')) {
      return;
    }

    $column = collect(Schema::getColumns('users'))->firstWhere('name', 'role');
    if ($column === null || $column['nullable']) {
      return;
    }

    // CMS defines a string role column. Do not reinterpret unrelated host types.
    if (! in_array($column['type_name'], ['varchar', 'char', 'character varying'], true)) {
      throw new \RuntimeException('CMS role compatibility requires a nullable string users.role column.');
    }
    $length = preg_match('/\((\d+)\)/', $column['type'], $matches) ? (int) $matches[1] : 32;
    $default = $column['default'];
    $connection = Schema::getConnection();
    $expressionDefault = in_array($connection->getDriverName(), ['sqlite', 'pgsql', 'mariadb'], true)
      || ($connection instanceof MySqlConnection && $connection->isMaria());
    if ($default !== null && $expressionDefault) {
      $default = new Expression($default);
    }
    Schema::table('users', function (Blueprint $table) use ($column, $length, $default): void {
      $definition = $column['type_name'] === 'char'
        ? $table->char('role', $length)
        : $table->string('role', $length);
      $definition->nullable()->default($default)->comment($column['comment'] ?? '');
      if ($column['collation'] !== null) {
        $definition->collation($column['collation']);
      }
      $definition->change();
    });
  }
}
