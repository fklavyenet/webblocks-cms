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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(private readonly AssetUsageResolver $assetUsageResolver) {}

    public function index(): View
    {
        $selectedFolderId = request()->integer('folder_id') ?: null;
        $search = trim((string) request('search'));
        $openModal = old('_media_modal', request()->string('modal')->toString() ?: null);

        return view('admin.media.index', [
            'folders' => $this->folderOptions(),
            'assets' => $this->assetListingQuery($selectedFolderId, $search)
                ->paginate(20)
                ->withQueryString(),
            'selectedFolderId' => $selectedFolderId,
            'search' => $search,
            'openModal' => in_array($openModal, ['upload-asset', 'new-folder'], true) ? $openModal : null,
        ]);
    }

    public function show(Asset $asset): View
    {
        return view('admin.media.show', [
            'asset' => $asset->load('folder'),
            'usages' => $this->assetUsageResolver->resolve($asset),
        ]);
    }

    public function edit(Asset $asset): View
    {
        return view('admin.media.edit', [
            'asset' => $asset,
            'folders' => $this->folderOptions(),
        ]);
    }

    public function update(AssetUpdateRequest $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validated());

        return redirect()
            ->route('admin.media.show', $asset)
            ->with('status', 'Asset updated successfully.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
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
            ->withCount('assets')
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    private function assetListingQuery(?int $folderId = null, ?string $search = null, ?string $kind = null, ?string $accept = null)
    {
        return Asset::query()
            ->with(['folder', 'uploader'])
            ->when($folderId, fn ($query) => $query->where('folder_id', $folderId))
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->when($accept, fn ($query) => $query->where('kind', $accept))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('filename', 'like', "%{$search}%")
                        ->orWhere('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->latest();
    }
}
