<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

use Illuminate\Support\Facades\Schema;

class WebBlocksUiManagerSchema
{
  /**
   * @return array<int, string>
   */
  public function missingTables(): array
  {
    return array_values(array_filter([
      'webblocks_ui_manager_releases',
      'webblocks_ui_manager_artifacts',
      'webblocks_ui_manager_publish_runs',
    ], fn (string $table): bool => ! Schema::hasTable($table)));
  }

  public function isReady(): bool
  {
    return $this->missingTables() === [];
  }

  public function message(): string
  {
    $missing = $this->missingTables();

    if ($missing === []) {
      return 'Release tables are ready.';
    }

    return 'Setup required. Plugin migrations pending. Release tables are missing: '.implode(', ', $missing).'.';
  }
}
