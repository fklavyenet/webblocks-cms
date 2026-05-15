<?php

namespace App\Console\Commands;

use App\Support\BlockTypes\BlockTypeContract;
use App\Support\BlockTypes\BlockTypeContractRegistry;
use Illuminate\Console\Command;

class BlockTypeContractsAuditCommand extends Command
{
    protected $signature = 'block-types:contracts-audit {--json : Output the audit as JSON}';

    protected $description = 'Audit shipped published block type contracts and support files';

    public function __construct(
        private readonly BlockTypeContractRegistry $contracts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $contracts = array_map(
            fn (BlockTypeContract $contract): array => $contract->toAuditArray(),
            $this->contracts->publishedCoreContracts(),
        );

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

        $lines[] = '';

        foreach ($contracts as $contract) {
            $lines[] = '## `'.$contract['slug'].'`';
            $lines[] = '';
            $lines[] = '- Label: '.$contract['label'];
            $lines[] = '- Category: `'.$contract['category'].'`';
            $lines[] = '- Status: `'.$contract['status'].'`';
            $lines[] = '- Source type: `'.$contract['source_type'].'`';
            $lines[] = '- System block: '.($contract['is_system'] ? 'yes' : 'no');
            $lines[] = '- Container: '.($contract['is_container'] ? 'yes' : 'no');
            $lines[] = '- Translation family: '.($contract['translation_family'] === null ? 'shared/canonical' : '`'.$contract['translation_family'].'`');
            $lines[] = '- Translation family fields: '.$this->markdownList($contract['translation_family_fields']);
            $lines[] = '- Admin form source: `'.($contract['admin_form_source'] ?? 'missing').'`';
            $lines[] = '- Admin form fields: '.$this->markdownList($contract['admin_form_fields']);
            $lines[] = '- Translatable fields: '.$this->markdownList($contract['translatable_fields']);
            $lines[] = '- Shared/settings fields: '.$this->markdownList($contract['shared_settings_fields']);
            $lines[] = '- Storage fields: '.$this->markdownList($contract['storage_fields']);
            $lines[] = '- Media/relationship fields: '.$this->markdownList($contract['media_relationship_fields']);
            $lines[] = '- Child/container behavior: '.$this->markdownList($contract['child_container_behavior']);
            $lines[] = '- Public renderer source: `'.($contract['public_renderer_source'] ?? 'missing').'`';
            $lines[] = '- Renderer root contract: '.$contract['renderer_root_contract'];
            $lines[] = '- Supports children: '.($contract['supports_children'] ? 'yes' : 'no');
            $lines[] = '- Allowed child type slugs: '.$this->markdownList($contract['allowed_child_type_slugs']);
            $lines[] = '- Owns public root helper: '.($contract['owns_public_root_helper'] ? 'yes' : 'no');
            $lines[] = '- Current contract status: `'.$contract['current_contract_status'].'`';
            $lines[] = '- Known gaps: '.$this->markdownList($contract['known_gaps']);

            if ($contract['undocumented_message']) {
                $lines[] = '- Undocumented message: '.$contract['undocumented_message'];
            }

            $lines[] = '';
        }

        return $lines;
    }

    private function markdownList(?array $values): string
    {
        if (! is_array($values) || $values === []) {
            return 'none';
        }

        return implode('; ', $values);
    }
}
