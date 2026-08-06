<?php

namespace WebBlocks\Cms\Support\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeCatalogSyncer;

class CatalogRepairer
{
  public function __construct(
    private readonly CoreBlockTypeCatalogSyncer $blockTypeSyncer,
    private readonly PluginBlockTypeCatalogSyncer $pluginBlockTypeSyncer,
  ) {}

  public function repair(array $scopes, bool $dryRun = true): array
  {
    $summary = [];

    DB::beginTransaction();

    try {
      foreach ($scopes as $scope) {
        $summary[$scope] = match ($scope) {
          'block-types' => $this->repairBlockTypes($dryRun),
          'plugin-block-types' => $this->pluginBlockTypeSyncer->sync($dryRun),
          'slot-types' => $this->repairSlotTypes($dryRun),
          'page-layouts' => $this->repairPageLayouts($dryRun),
          'icons' => $this->repairIcons($dryRun),
          default => ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 1],
        };
      }

      if ($dryRun) {
        DB::rollBack();
      } else {
        DB::commit();
      }
    } catch (Throwable $exception) {
      DB::rollBack();

      throw $exception;
    }

    return $summary;
  }

  private function repairBlockTypes(bool $dryRun): array
  {
    return $this->repairDefinitions(
      modelClass: BlockType::class,
      keys: ['slug'],
      definitions: $this->blockTypeSyncer->definitions(),
      dryRun: $dryRun,
    );
  }

  private function repairSlotTypes(bool $dryRun): array
  {
    return $this->repairDefinitions(
      modelClass: SlotType::class,
      keys: ['slug'],
      definitions: $this->slotTypeDefinitions(),
      dryRun: $dryRun,
    );
  }

  private function repairPageLayouts(bool $dryRun): array
  {
    $summary = $this->repairDefinitions(
      modelClass: PageLayout::class,
      keys: ['handle'],
      definitions: $this->pageLayoutDefinitions(),
      dryRun: $dryRun,
    );

    if (! Schema::hasTable('wbcms_page_layout_slots')) {
      $summary['skipped']++;

      return $summary;
    }

    foreach (PageLayoutCatalog::definitions() as $layoutDefinition) {
      $pageLayout = PageLayout::query()->where('handle', $layoutDefinition['handle'])->first();

      if (! $pageLayout) {
        $summary['skipped']++;

        continue;
      }

      foreach ($layoutDefinition['managed_slots'] ?? [] as $slotDefinition) {
        $slotTypeSummary = $this->repairDefinitions(
          modelClass: SlotType::class,
          keys: ['slug'],
          definitions: [[
            'slug' => $slotDefinition['slot_type_slug'],
            'name' => $slotDefinition['label'] ?? str($slotDefinition['slot_type_slug'])->headline()->toString(),
            'description' => $slotDefinition['description'] ?? null,
            'is_system' => true,
            'sort_order' => $slotDefinition['sort_order'] ?? 0,
            'status' => 'published',
          ]],
          dryRun: $dryRun,
        );
        $summary = $this->mergeSummary($summary, $slotTypeSummary);

        $slotType = SlotType::query()->where('slug', $slotDefinition['slot_type_slug'])->first();

        if (! $slotType) {
          $summary['skipped']++;

          continue;
        }

        // css_classes is operator-customizable presentation: when the catalog
        // defines no canonical value for a slot (footer everywhere, header and
        // sidebar in the default layout), repairing it to null would wipe the
        // site's own classes — so the column is only repaired when the catalog
        // actually states a value.
        $slotAttributes = [
          'page_layout_id' => $pageLayout->id,
          'slot_name' => $slotDefinition['slot_name'],
          'slot_type_id' => $slotType->id,
          'label' => $slotDefinition['label'] ?? null,
          'description' => $slotDefinition['description'] ?? null,
          'html_element' => $slotDefinition['html_element'] ?? 'div',
          'html_id' => $slotDefinition['html_id'] ?? null,
          'css_classes' => $slotDefinition['css_classes'] ?? null,
          'before_html' => $slotDefinition['before_html'] ?? null,
          'start_html' => $slotDefinition['start_html'] ?? null,
          'end_html' => $slotDefinition['end_html'] ?? null,
          'after_html' => $slotDefinition['after_html'] ?? null,
          'is_required' => (bool) ($slotDefinition['is_required'] ?? false),
          'is_active' => (bool) ($slotDefinition['is_active'] ?? true),
          'is_system' => (bool) ($slotDefinition['is_system'] ?? false),
          'sort_order' => (int) ($slotDefinition['sort_order'] ?? 0),
        ];

        if (! array_key_exists('css_classes', $slotDefinition)) {
          unset($slotAttributes['css_classes']);
        }

        $slotSummary = $this->repairDefinitions(
          modelClass: PageLayoutSlot::class,
          keys: ['page_layout_id', 'slot_name'],
          definitions: [$slotAttributes],
          dryRun: $dryRun,
        );

        $summary = $this->mergeSummary($summary, $slotSummary);
      }
    }

    return $summary;
  }

  /**
   * Syncs the whole shipped icon catalog, which is what makes a site installed
   * before the manifest was bundled pick it up on its next System Update: the
   * updater runs this repair for exactly that reason.
   */
  private function repairIcons(bool $dryRun): array
  {
    return $this->repairDefinitions(
      modelClass: IconCatalogItem::class,
      keys: ['source', 'slug'],
      definitions: collect($this->bundledIcons())
        ->map(fn (array $icon, int $index) => [
          'source' => (string) ($icon['source'] ?? 'webblocks-ui'),
          'slug' => (string) $icon['slug'],
          'label' => (string) ($icon['label'] ?? Str::of((string) $icon['slug'])->replace('-', ' ')->title()->toString()),
          'css_class' => (string) ($icon['css_class'] ?? 'wb-icon-'.$icon['slug']),
          'categories' => $icon['categories'] ?? [],
          'contexts' => $icon['contexts'] ?? [],
          'keywords' => IconCatalogItem::normalizeKeywords($icon['keywords'] ?? [(string) $icon['slug']]),
          'is_active' => true,
          'sort_order' => $index + 1,
        ])
        ->all(),
      dryRun: $dryRun,
    );
  }

  private function repairDefinitions(string $modelClass, array $keys, array $definitions, bool $dryRun): array
  {
    $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];

    foreach ($definitions as $definition) {
      $identity = array_intersect_key($definition, array_flip($keys));
      /** @var Model|null $model */
      $model = $modelClass::query()->where($identity)->first();

      if (! $model) {
        $summary['created']++;

        if (! $dryRun) {
          $modelClass::query()->create($definition);
        }

        continue;
      }

      $changes = collect($definition)
        ->except($keys)
        ->filter(fn ($value, string $column) => $this->normalValue($model->{$column}) !== $this->normalValue($value))
        ->all();

      if ($changes === []) {
        $summary['unchanged']++;

        continue;
      }

      $summary['updated']++;

      if (! $dryRun) {
        $model->forceFill($changes)->save();
      }
    }

    return $summary;
  }

  private function normalValue(mixed $value): mixed
  {
    if (is_array($value)) {
      ksort($value);

      return json_encode($value);
    }

    if (is_bool($value)) {
      return (int) $value;
    }

    return $value;
  }

  private function pageLayoutDefinitions(): array
  {
    return collect(PageLayoutCatalog::definitions())
      ->map(fn (array $layout) => [
        'handle' => $layout['handle'],
        'name' => $layout['name'],
        'description' => $layout['description'] ?? null,
        'is_system' => $layout['is_system'] ?? false,
        'is_active' => $layout['is_active'] ?? true,
        'sort_order' => $layout['sort_order'] ?? 0,
        'body_class' => $layout['body_class'] ?? null,
        'shell_type' => $layout['shell_type'] ?? 'default',
        'slot_schema' => $layout['slot_schema'] ?? null,
        'wrapper_schema' => $layout['wrapper_schema'] ?? null,
      ])
      ->all();
  }

  private function slotTypeDefinitions(): array
  {
    return [
      ['name' => 'Header', 'slug' => 'header', 'description' => 'Header slot', 'axis' => 'horizontal', 'is_system' => true, 'sort_order' => 1, 'status' => 'published'],
      ['name' => 'Main', 'slug' => 'main', 'description' => 'Main slot', 'axis' => 'vertical', 'is_system' => true, 'sort_order' => 2, 'status' => 'published'],
      ['name' => 'Sidebar', 'slug' => 'sidebar', 'description' => 'Sidebar slot', 'axis' => 'vertical', 'is_system' => true, 'sort_order' => 3, 'status' => 'published'],
      ['name' => 'Footer', 'slug' => 'footer', 'description' => 'Footer slot', 'axis' => 'horizontal', 'is_system' => true, 'sort_order' => 4, 'status' => 'published'],
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function bundledIcons(): array
  {
    try {
      $decoded = app(WebBlocksIconManifestSyncer::class)->readInstallManifest();
    } catch (Throwable) {
      // Repair reports per-scope results and an update continues past a failed
      // scope, so an unreadable manifest leaves icons alone rather than
      // wiping the catalog the site already has.
      return [];
    }

    return array_values(array_filter($decoded, fn ($icon) => is_array($icon) && ! empty($icon['slug'])));
  }

  private function mergeSummary(array $left, array $right): array
  {
    foreach (['created', 'updated', 'unchanged', 'skipped'] as $key) {
      $left[$key] = ($left[$key] ?? 0) + ($right[$key] ?? 0);
    }

    return $left;
  }
}
