<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssetFolderRequest;
use App\Http\Requests\Admin\AssetUpdateRequest;
use App\Http\Requests\Admin\AssetUploadRequest;
use App\Models\Asset;
use App\Models\AssetFolder;
use App\Support\Assets\AssetKindResolver;
use App\Support\Assets\AssetUsageResolver;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(
        private readonly AssetUsageResolver $assetUsageResolver,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(): View
    {
        $selectedFolderId = request()->integer('folder_id') ?: null;
        $search = trim((string) request('search'));
        $kind = request()->string('kind')->toString();
        $usage = request()->string('usage')->toString();
        $view = request()->string('view')->toString() === 'grid' ? 'grid' : 'list';
        $openModal = old('_media_modal', request()->string('modal')->toString() ?: null);
        $previewAssetId = request()->integer('preview') ?: null;
        $usageAssetId = request()->integer('usage_asset') ?: null;

        if (! in_array($kind, [Asset::KIND_IMAGE, Asset::KIND_VIDEO, Asset::KIND_DOCUMENT, Asset::KIND_OTHER], true)) {
            $kind = '';
        }

        if (! in_array($usage, ['used', 'unused'], true)) {
            $usage = '';
        }

        $assetPaginator = $this->assetListingQuery(request()->user(), $selectedFolderId, $search, $kind, $usage)
            ->paginate($view === 'grid' ? 24 : 20)
            ->withQueryString();

        $assetPaginator->getCollection()->transform(function (Asset $asset) {
            $usages = $this->assetUsageResolver->resolve($asset);
            $asset->setRelation('resolvedUsages', $usages);
            $asset->setAttribute('resolved_usage_count', $usages->count());

            return $asset;
        });

        $assets = $assetPaginator;
        $previewAsset = $previewAssetId
            ? ($assets->getCollection()->firstWhere('id', $previewAssetId) ?: $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())->with(['folder', 'uploader'])->find($previewAssetId))
            : null;
        $usageAsset = $usageAssetId
            ? ($assets->getCollection()->firstWhere('id', $usageAssetId) ?: $this->authorization->scopeAssetsForUser(Asset::query(), request()->user())->with(['folder', 'uploader'])->find($usageAssetId))
            : null;

        if ($previewAsset instanceof Asset && ! $previewAsset->relationLoaded('resolvedUsages')) {
            $previewAsset->setRelation('resolvedUsages', $this->assetUsageResolver->resolve($previewAsset));
        }

        if ($usageAsset instanceof Asset && ! $usageAsset->relationLoaded('resolvedUsages')) {
            $usageAsset->setRelation('resolvedUsages', $this->assetUsageResolver->resolve($usageAsset));
        }

        return view('admin.media.index', [
            'folders' => $this->folderOptions(),
            'assets' => $assets,
            'selectedFolderId' => $selectedFolderId,
            'search' => $search,
            'kind' => $kind,
            'usage' => $usage,
            'viewMode' => $view,
            'previewAsset' => $previewAsset,
            'usageAsset' => $usageAsset,
            'openModal' => in_array($openModal, ['upload-asset', 'new-folder'], true) ? $openModal : null,
        ]);
    }

    public function show(Asset $asset): View
    {
        $this->authorization->abortUnlessAssetAccess(request()->user(), $asset);

        return view('admin.media.show', [
            'asset' => $asset->load('folder'),
            'usages' => $this->assetUsageResolver->resolve($asset),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->authorization->abortUnlessAssetAccess(request()->user(), $asset);

        return view('admin.media.edit', [
            'asset' => $asset,
            'folders' => $this->folderOptions(),
        ]);
    }

    public function update(AssetUpdateRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorization->abortUnlessAssetAccess($request->user(), $asset);
        $asset->update($request->validated());

        return redirect()
            ->route('admin.media.show', array_filter([
                'asset' => $asset,
                'back_to_preview' => $request->boolean('back_to_preview') ? 1 : null,
            ]))
            ->with('status', 'Asset updated successfully.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorization->abortUnlessAssetAccess(request()->user(), $asset);
        $usages = $this->assetUsageResolver->resolve($asset);

        if ($usages->isNotEmpty()) {
            $summary = $usages->take(3)->map(fn (array $usage) => $usage['context'].': '.$usage['label'])->implode(', ');

            return redirect()
                ->route('admin.media.show', $asset)
                ->withErrors(['asset' => 'Asset cannot be deleted because it is in use. '.$summary]);
        }

        Storage::disk($asset->disk)->delete($asset->path);
        $asset->delete();

        return redirect()
            ->route('admin.media.index')
            ->with('status', 'Asset deleted successfully.');
    }

    public function store(AssetUploadRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $mimeType = $file?->getMimeType();
        $extension = strtolower($file?->getClientOriginalExtension() ?: $file?->extension() ?: '');
        $kind = AssetKindResolver::resolve($mimeType, $extension);
        $disk = 'public';
        $directory = 'media/'.AssetKindResolver::directoryFor($kind);
        $filename = $this->buildFilename($file, $extension);
        $path = $file->storeAs($directory, $filename, $disk);

        $dimensions = $this->imageDimensions($file, $kind);

        Asset::create([
            'folder_id' => $request->validated('folder_id'),
            'disk' => $disk,
            'path' => $path,
            'filename' => basename($path),
            'original_name' => $file->getClientOriginalName(),
            'extension' => $extension ?: null,
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'kind' => $kind,
            'visibility' => 'public',
            'title' => $request->validated('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt_text' => $request->validated('alt_text'),
            'caption' => $request->validated('caption'),
            'description' => $request->validated('description'),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'duration' => null,
            'uploaded_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.media.index', array_filter(['folder_id' => $request->validated('folder_id')]))
            ->with('status', 'Asset uploaded successfully.');
    }

    public function storeFolder(AssetFolderRequest $request): RedirectResponse
    {
        $folder = AssetFolder::create($request->validated());

        return redirect()
            ->route('admin.media.index', ['folder_id' => $folder->id])
            ->with('status', 'Folder created successfully.');
    }

    private function buildFilename(UploadedFile $file, string $extension): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return trim($name !== '' ? $name : 'asset').'-'.Str::lower(Str::random(10)).($extension !== '' ? '.'.$extension : '');
    }

    private function imageDimensions(UploadedFile $file, string $kind): array
    {
        if ($kind !== Asset::KIND_IMAGE) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($file->getRealPath());

        if (! is_array($size)) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    private function folderOptions()
    {
        return AssetFolder::query()
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    private function assetListingQuery($user, ?int $folderId = null, ?string $search = null, ?string $kind = null, ?string $usage = null)
    {
        return $this->authorization->scopeAssetsForUser(Asset::query(), $user)
            ->with(['folder', 'uploader'])
            ->when($folderId, fn ($query) => $query->where('folder_id', $folderId))
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->when($usage === 'used', function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('asset_id')
                        ->orWhereExists(function ($exists) {
                            $exists->selectRaw('1')
                                ->from('block_assets')
                                ->whereColumn('block_assets.asset_id', 'assets.id');
                        });
                });
            })
            ->when($usage === 'unused', function ($query) {
                $query->whereNull('asset_id')
                    ->whereNotExists(function ($exists) {
                        $exists->selectRaw('1')
                            ->from('block_assets')
                            ->whereColumn('block_assets.asset_id', 'assets.id');
                    });
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('filename', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('alt_text', 'like', "%{$search}%")
                        ->orWhere('caption', 'like', "%{$search}%");
                });
            })
            ->latest();
    }
}
