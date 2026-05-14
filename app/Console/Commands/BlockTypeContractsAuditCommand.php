<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Blocks\CoreBlockTypeCatalogSyncer;
use Illuminate\Console\Command;

class BlockTypeContractsAuditCommand extends Command
{
    protected $signature = 'block-types:contracts-audit {--json : Output the audit as JSON}';

    protected $description = 'Audit shipped published block type contracts and support files';

    public function __construct(
        private readonly CoreBlockTypeCatalogSyncer $syncer,
        private readonly BlockTranslationRegistry $translationRegistry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $contracts = collect($this->syncer->definitions())
            ->filter(fn (array $definition): bool => ($definition['status'] ?? null) === 'published')
            ->sortBy(fn (array $definition): string => sprintf(
                '%03d-%s',
                (int) ($definition['sort_order'] ?? 0),
                (string) ($definition['slug'] ?? '')
            ))
            ->values()
            ->map(fn (array $definition): array => $this->contractSummary($definition))
            ->all();

        if ($this->option('json')) {
            $this->line(json_encode([
                'published_count' => count($contracts),
                'contracts' => $contracts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('# Published Block Type Contracts Audit');
        $this->newLine();
        $this->line('Published block types: '.count($contracts));
        $this->newLine();

        foreach ($this->markdownTableLines($contracts) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function contractSummary(array $definition): array
    {
        $slug = (string) ($definition['slug'] ?? '');
        $translationFamily = $this->translationRegistry->familyFor($slug);
        $translatableFields = $translationFamily
            ? $this->translationRegistry->translatedFieldMap($translationFamily)
            : [];
        $block = new Block(['type' => $slug]);
        $adminFormPath = 'resources/views/admin/blocks/types/'.$slug.'.blade.php';
        $publicRendererPath = 'resources/views/pages/partials/blocks/'.$slug.'.blade.php';
        $allowedChildren = $block->allowedChildTypeSlugs();

        return [
            'slug' => $slug,
            'label' => (string) ($definition['name'] ?? ''),
            'category' => (string) ($definition['category'] ?? ''),
            'status' => (string) ($definition['status'] ?? ''),
            'is_system' => (bool) ($definition['is_system'] ?? false),
            'is_container' => (bool) ($definition['is_container'] ?? false),
            'translation_family' => $translationFamily,
            'translatable_fields' => $translatableFields,
            'admin_form_source' => is_file(resource_path('views/admin/blocks/types/'.$slug.'.blade.php'))
                ? $adminFormPath
                : null,
            'public_renderer_source' => is_file(resource_path('views/pages/partials/blocks/'.$slug.'.blade.php'))
                ? $publicRendererPath
                : null,
            'supports_children' => $block->canAcceptChildren(),
            'allowed_child_type_slugs' => $allowedChildren,
            'owns_public_root_helper' => $block->ownsPublicRoot(),
        ];
    }

    private function markdownTableLines(array $contracts): array
    {
        $lines = [
            '| Slug | Label | Category | Translation | Container | Admin form | Public renderer |',
            '| --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($contracts as $contract) {
            $lines[] = sprintf(
                '| `%s` | %s | `%s` | %s | %s | `%s` | `%s` |',
                $contract['slug'],
                $contract['label'],
                $contract['category'],
                $contract['translation_family'] === null
                    ? 'shared/canonical'
                    : '`'.$contract['translation_family'].'`'.($contract['translatable_fields'] === []
                        ? ''
                        : ' ('.implode(', ', $contract['translatable_fields']).')'),
                $contract['is_container'] ? 'yes' : 'no',
                $contract['admin_form_source'] ?? 'missing',
                $contract['public_renderer_source'] ?? 'missing',
            );
        }

        return $lines;
    }
}
