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

        return $lines;
    }
}
