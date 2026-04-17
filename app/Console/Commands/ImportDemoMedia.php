<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\DemoAssetReference;
use App\Models\Page;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Assets\AssetKindResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImportDemoMedia extends Command
{
    protected $signature = 'demo:import-media';

    protected $description = 'Import curated starter/showcase media into the media library';

    public function handle(): int
    {
        $items = collect(config('demo_media.items', []));

        if ($items->isEmpty()) {
            $this->warn('No demo media items are configured.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($items as $item) {
            $key = (string) ($item['key'] ?? '');

            if ($key === '') {
                $failed++;
                $this->error('Skipping a demo media item because it has no key.');

                continue;
            }

            if (DemoAssetReference::query()->where('source_key', $key)->exists()) {
                $skipped++;
                $this->line("Skipped {$key}");

                continue;
            }

            try {
                $this->importItem($item);
                $imported++;
                $this->info("Imported {$key}");
            } catch (Throwable $throwable) {
                $failed++;
                $this->error("Failed {$key}: {$throwable->getMessage()}");
            }
        }

        $bindings = $this->bindDemoContent(
            DemoAssetReference::query()
                ->with('asset')
                ->whereIn('source_key', $items->pluck('key')->all())
                ->get()
                ->mapWithKeys(fn (DemoAssetReference $reference) => [$reference->source_key => $reference->asset])
        );

        $this->newLine();
        $this->table(
            ['Imported', 'Skipped', 'Failed', 'Bindings'],
            [[$imported, $skipped, $failed, $bindings]]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function importItem(array $item): Asset
    {
        $response = Http::timeout(30)->get((string) $item['source_url']);

        if (! $response->successful()) {
            throw new \RuntimeException('Download returned HTTP '.$response->status().'.');
        }

        $contents = $response->body();

        if ($contents === '') {
            throw new \RuntimeException('Download returned an empty response body.');
        }

        $extension = $this->detectExtension($response, (string) ($item['source_url'] ?? ''), $contents);
        $mimeType = $this->detectMimeType($response, $contents, $extension);
        $kind = AssetKindResolver::resolve($mimeType, $extension);
        $path = sprintf(
            'assets/demo-content/%s/%s.%s',
            trim((string) $item['topic'], '/'),
            (string) $item['key'],
            $extension
        );

        Storage::disk('public')->put($path, $contents);

        try {
            $folder = $this->ensureFolder((string) ($item['folder'] ?? 'Demo Content'));
            $dimensions = $this->imageDimensions($contents, $kind);

            $asset = Asset::query()->create([
                'folder_id' => $folder?->id,
                'disk' => 'public',
                'path' => $path,
                'filename' => basename($path),
                'original_name' => basename($path),
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size' => strlen($contents),
                'kind' => $kind,
                'visibility' => 'public',
                'title' => $item['title'] ?? Str::headline((string) $item['key']),
                'alt_text' => $item['alt'] ?? null,
                'caption' => $item['alt'] ?? null,
                'description' => 'Curated demo media imported from an approved source.',
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'uploaded_by' => $this->uploaderId(),
            ]);

            DemoAssetReference::query()->updateOrCreate(
                ['source_key' => (string) $item['key']],
                ['asset_id' => $asset->id],
            );

            return $asset;
        } catch (Throwable $throwable) {
            Storage::disk('public')->delete($path);

            throw $throwable;
        }
    }

    private function ensureFolder(string $folderPath): ?AssetFolder
    {
        $segments = array_values(array_filter(explode('/', $folderPath), fn ($segment) => trim($segment) !== ''));

        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $folder = null;

        foreach ($segments as $segment) {
            $folder = AssetFolder::query()->firstOrCreate(
                [
                    'parent_id' => $parentId,
                    'name' => $segment,
                ],
                [
                    'slug' => Str::slug($segment),
                ]
            );

            $parentId = $folder->id;
        }

        return $folder;
    }

    private function bindDemoContent(Collection $assets): int
    {
        $bindings = 0;

        $bindings += $this->bindBlockAsset('home', 'image', 'Editorial command center', $assets->get('home-hero-01'));
        $bindings += $this->bindGalleryAssets('home', 'slider', 'A quick tour of the working environment', $assets, ['gallery-01', 'gallery-02', 'gallery-03', 'gallery-04']);
        $bindings += $this->bindBlockAsset('about', 'image', 'Implementation workshop', $assets->get('about-team-01'));
        $bindings += $this->bindServicesOverview($assets->get('services-workspace-01'));
        $bindings += $this->bindBlockAsset('service-implementation-ops', 'image', 'Workshop visual', $assets->get('services-workspace-01'));
        $bindings += $this->bindBlockAsset('blog-launching-a-governed-content-platform', 'image', 'Governance models', $assets->get('blog-writing-01'));
        $bindings += $this->bindContactImage($assets->get('contact-office-01'));
        $bindings += $this->bindGalleryAssets('case-studies', 'gallery', 'Selected project visuals', $assets, ['gallery-01', 'gallery-02', 'gallery-03', 'gallery-04']);

        return $bindings;
    }

    private function bindBlockAsset(string $pageSlug, string $type, string $title, ?Asset $asset): int
    {
        if (! $asset) {
            return 0;
        }

        $block = Block::query()
            ->where('type', $type)
            ->where('title', $title)
            ->whereHas('page', fn ($query) => $query->where('slug', $pageSlug))
            ->first();

        if (! $block) {
            return 0;
        }

        $updates = [
            'asset_id' => $asset->id,
        ];

        if ($block->type === 'image' && blank($block->subtitle)) {
            $updates['subtitle'] = $asset->alt_text;
        }

        $block->update($updates);

        return 1;
    }

    private function bindServicesOverview(?Asset $asset): int
    {
        if (! $asset) {
            return 0;
        }

        $bindings = $this->bindBlockAsset('services', 'product-card', 'Implementation Ops Sprint', $asset);

        $block = Block::query()
            ->where('type', 'product-grid')
            ->where('title', 'Northstar Labs service lines')
            ->whereHas('page', fn ($query) => $query->where('slug', 'services'))
            ->first();

        if (! $block) {
            return $bindings;
        }

        $settings = json_decode((string) $block->settings, true);

        if (! is_array($settings)) {
            return $bindings;
        }

        $settings['items'] = collect($settings['items'] ?? [])
            ->map(function ($item) use ($asset) {
                if (! is_array($item)) {
                    return $item;
                }

                $item['media_url'] = $asset->url();

                return $item;
            })
            ->all();

        $block->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
        ]);

        return $bindings + 1;
    }

    private function bindContactImage(?Asset $asset): int
    {
        if (! $asset) {
            return 0;
        }

        $page = Page::query()->where('slug', 'contact')->first();
        $slotType = SlotType::query()->where('slug', 'main')->first();
        $blockType = BlockType::query()->where('slug', 'image')->first();

        if (! $page || ! $slotType || ! $blockType) {
            return 0;
        }

        $existing = Block::query()
            ->where('page_id', $page->id)
            ->where('slot_type_id', $slotType->id)
            ->where('type', 'image')
            ->where('title', 'Office interior')
            ->first();

        $payload = [
            'page_id' => $page->id,
            'parent_id' => null,
            'type' => $blockType->slug,
            'block_type_id' => $blockType->id,
            'source_type' => $blockType->source_type ?? 'static',
            'slot' => $slotType->slug,
            'slot_type_id' => $slotType->id,
            'sort_order' => $existing?->sort_order ?? ((int) Block::query()
                ->where('page_id', $page->id)
                ->where('slot_type_id', $slotType->id)
                ->max('sort_order')) + 1,
            'title' => 'Office interior',
            'subtitle' => $asset->alt_text,
            'content' => null,
            'url' => null,
            'asset_id' => $asset->id,
            'variant' => null,
            'meta' => null,
            'settings' => null,
            'status' => 'published',
            'is_system' => false,
        ];

        if ($existing) {
            $existing->update($payload);

            return 1;
        }

        Block::query()->create($payload);

        return 1;
    }

    private function bindGalleryAssets(string $pageSlug, string $type, string $title, Collection $assets, array $keys): int
    {
        $block = Block::query()
            ->where('type', $type)
            ->where('title', $title)
            ->whereHas('page', fn ($query) => $query->where('slug', $pageSlug))
            ->first();

        if (! $block) {
            return 0;
        }

        $galleryAssets = collect($keys)
            ->map(fn (string $key) => $assets->get($key))
            ->filter();

        if ($galleryAssets->isEmpty()) {
            return 0;
        }

        BlockAsset::query()->where('block_id', $block->id)->where('role', 'gallery_item')->delete();

        foreach ($galleryAssets->values() as $position => $asset) {
            BlockAsset::query()->create([
                'block_id' => $block->id,
                'asset_id' => $asset->id,
                'role' => 'gallery_item',
                'position' => $position,
            ]);
        }

        if ($pageSlug === 'home') {
            $block->update([
                'subtitle' => 'Curated workspace and collaboration scenes from the local demo media library.',
            ]);
        }

        return 1;
    }

    private function detectExtension(Response $response, string $sourceUrl, string $contents): string
    {
        $mimeType = $this->headerMimeType($response) ?: $this->finfoMimeType($contents);

        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => strtolower(pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg',
        };
    }

    private function detectMimeType(Response $response, string $contents, string $extension): string
    {
        return $this->headerMimeType($response)
            ?: $this->finfoMimeType($contents)
            ?: match ($extension) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/jpeg',
            };
    }

    private function headerMimeType(Response $response): ?string
    {
        $header = trim((string) $response->header('Content-Type'));

        if ($header === '') {
            return null;
        }

        return strtolower(trim(strtok($header, ';')));
    }

    private function finfoMimeType(string $contents): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents);

        return is_string($mimeType) && $mimeType !== '' ? strtolower($mimeType) : null;
    }

    private function imageDimensions(string $contents, string $kind): array
    {
        if ($kind !== Asset::KIND_IMAGE) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesizefromstring($contents);

        if (! is_array($size)) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    private function uploaderId(): ?int
    {
        return User::query()->where('email', 'admin@example.com')->value('id')
            ?? User::query()->where('email', 'test@example.com')->value('id')
            ?? User::query()->value('id');
    }
}
