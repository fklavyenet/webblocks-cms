<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MediaUsageFilter
{
    public function apply(Builder $query, ?string $usage): Builder
    {
        return match ($usage) {
            'used' => $this->applyUsed($query),
            'unused' => $this->applyUnused($query),
            default => $query,
        };
    }

    private function applyUsed(Builder $query): Builder
    {
        $references = $this->availableReferences();

        if ($references === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($references): void {
            foreach ($references as $index => $reference) {
                $method = $index === 0 ? 'whereExists' : 'orWhereExists';

                $inner->{$method}(function ($exists) use ($reference): void {
                    $exists->selectRaw('1')
                        ->from($reference['table'])
                        ->whereColumn($reference['qualified_column'], 'media.id');
                });
            }
        });
    }

    private function applyUnused(Builder $query): Builder
    {
        foreach ($this->availableReferences() as $reference) {
            $query->whereNotExists(function ($exists) use ($reference): void {
                $exists->selectRaw('1')
                    ->from($reference['table'])
                    ->whereColumn($reference['qualified_column'], 'media.id');
            });
        }

        return $query;
    }

    private function availableReferences(): array
    {
        return collect([
            ['table' => 'blocks', 'column' => 'media_id'],
            ['table' => 'block_media', 'column' => 'media_id'],
            ['table' => 'sites', 'column' => 'favicon_media_id'],
            ['table' => 'sites', 'column' => 'social_image_media_id'],
            ['table' => 'page_translations', 'column' => 'og_image_media_id'],
        ])
            ->filter(fn (array $reference) => Schema::hasTable($reference['table']) && Schema::hasColumn($reference['table'], $reference['column']))
            ->map(fn (array $reference) => [
                'table' => $reference['table'],
                'qualified_column' => $reference['table'].'.'.$reference['column'],
            ])
            ->values()
            ->all();
    }
}
