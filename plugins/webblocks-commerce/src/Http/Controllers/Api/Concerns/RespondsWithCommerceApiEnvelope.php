<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Http\Controllers\Api\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/**
 * Shared JSON envelope for the plugin-owned commerce API. Mirrors the CMS
 * internal API shape ({ ok, warnings, errors, ... }) so AI agents get a
 * consistent contract across core and plugin endpoints.
 */
trait RespondsWithCommerceApiEnvelope
{
  /**
   * @param  array<int, string>  $tables
   */
  protected function requireTables(array $tables): ?JsonResponse
  {
    $missing = array_values(array_filter($tables, fn (string $table): bool => ! Schema::hasTable($table)));

    if ($missing === []) {
      return null;
    }

    return response()->json([
      'ok' => false,
      'code' => 'commerce_setup_required',
      'message' => 'WebBlocks Commerce setup migrations have not been run yet.',
      'missing_tables' => $missing,
      'setup_url' => '/webadmin/api/plugins/webblocks-commerce/setup',
      'warnings' => [],
      'errors' => [['path' => 'commerce', 'message' => 'Run plugin setup before using Commerce API resources.']],
    ], 409);
  }

  /**
   * @param  array<string, mixed>  $data
   */
  protected function ok(array $data, int $status = 200): JsonResponse
  {
    return response()->json(['ok' => true, ...$data, 'warnings' => [], 'errors' => []], $status);
  }

  protected function apiError(string $code, string $message, int $status = 422): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => $message,
      'warnings' => [],
      'errors' => [['path' => 'commerce', 'message' => $message]],
    ], $status);
  }

  /**
   * @param  array<string, array<int, string>>  $errors
   */
  protected function validationErrors(array $errors, string $code): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => $code,
      'message' => 'Validation failed.',
      'warnings' => [],
      'errors' => collect($errors)
        ->map(fn (array $messages, string $field): array => ['path' => $field, 'message' => $messages[0] ?? 'Invalid value.'])
        ->values()
        ->all(),
    ], 422);
  }
}
