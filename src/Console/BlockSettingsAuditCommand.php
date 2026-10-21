<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\BlockTypes\BlockSettingsSchema;
use WebBlocks\Cms\Support\BlockTypes\BlockSettingsValidator;

/** Read-only compatibility report; no values, provider resolution or repairs. */
class BlockSettingsAuditCommand extends Command
{
  protected $signature = 'webblocks:blocks:audit-settings {--limit=100 : Maximum reported blocks (1-1000)}';

  protected $description = 'Report block setting schema compatibility as JSON without changing content';

  public function handle(BlockSettingsSchema $schemas, BlockSettingsValidator $validator): int
  {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $scanned = $incompatible = $legacyPlugins = 0;
    $issues = [];
    Block::query()->with('blockType')->orderBy('id')->chunkById(100, function ($blocks) use ($schemas, $validator, $limit, &$scanned, &$incompatible, &$legacyPlugins, &$issues): void {
      foreach ($blocks as $block) {
        $scanned++;
        $type = $block->typeSlug();
        if ($schemas->forType($type)['mode'] === 'legacy_plugin') {
          $legacyPlugins++;

          continue;
        }
        $errors = [];
        // Admin field validation measures value compatibility without granting
        // an API operation or invoking application/content-source providers.
        $validator->normalize($type, $block->decodedSettings(), 'settings', $errors, 'admin');
        if ($errors !== []) {
          $incompatible++;
          if (count($issues) < $limit) {
            $issues[] = ['block_id' => $block->id, 'type' => $type, 'errors' => array_map(fn ($error) => ['path' => $error['path'], 'code' => $error['code']], $errors)];
          }
        }
      }
    });
    $this->line(json_encode(['schema_revision' => 1, 'scanned' => $scanned, 'incompatible' => $incompatible, 'legacy_plugins' => $legacyPlugins, 'truncated' => $incompatible > count($issues), 'issues' => $issues], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

    return $incompatible > 0 ? self::FAILURE : self::SUCCESS;
  }
}
