<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaFolderRequest;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Support\Media\MediaIndexState;
use App\Support\Media\MediaKindResolver;
use App\Support\Media\MediaUsageFilter;
use App\Support\Media\MediaUsageResolver;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaUsageFilter $mediaUsageFilter,
        private readonly MediaUsageResolver $mediaUsageResolver,
        private readonly MediaIndexState $mediaIndexState,
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
        $previewMediaId = request()->integer('preview') ?: null;
        $usageMediaId = request()->integer('usage_media') ?: request()->integer('usage_asset') ?: null;

        if (! in_array($kind, [Media::KIND_IMAGE, Media::KIND_VIDEO, Media::KIND_DOCUMENT, Media::KIND_OTHER], true)) {
            $kind = '';
        }

        if (! in_array($usage, ['used', 'unused'], true)) {
            $usage = '';
        }

        $mediaPaginator = $this->mediaListingQuery(request()->user(), $selectedFolderId, $search, $kind, $usage)
            ->paginate($view === 'grid' ? 24 : 20)
            ->withQueryString();

        $mediaPaginator->getCollection()->transform(function (Media $media) {
            $usages = $this->mediaUsageResolver->resolve($media);
            $media->setRelation('resolvedUsages', $usages);
            $media->setAttribute('resolved_usage_count', $usages->count());

            return $media;
        });

        $media = $mediaPaginator;
        $previewMedia = $previewMediaId
            ? ($media->getCollection()->firstWhere('id', $previewMediaId) ?: $this->authorization->scopeMediaForUser(Media::query(), request()->user())->with(['folder', 'uploader'])->find($previewMediaId))
            : null;
        $usageMedia = $usageMediaId
            ? ($media->getCollection()->firstWhere('id', $usageMediaId) ?: $this->authorization->scopeMediaForUser(Media::query(), request()->user())->with(['folder', 'uploader'])->find($usageMediaId))
            : null;

        if ($previewMedia instanceof Media && ! $previewMedia->relationLoaded('resolvedUsages')) {
            $previewMedia->setRelation('resolvedUsages', $this->mediaUsageResolver->resolve($previewMedia));
        }

        if ($usageMedia instanceof Media && ! $usageMedia->relationLoaded('resolvedUsages')) {
            $usageMedia->setRelation('resolvedUsages', $this->mediaUsageResolver->resolve($usageMedia));
        }

        return view('admin.media.index', [
            'folders' => $this->folderOptions(),
            'assets' => $media,
            'media' => $media,
            'selectedFolderId' => $selectedFolderId,
            'search' => $search,
            'kind' => $kind,
            'usage' => $usage,
            'viewMode' => $view,
            'previewAsset' => $previewMedia,
            'previewMedia' => $previewMedia,
            'usageAsset' => $usageMedia,
            'usageMedia' => $usageMedia,
            'openModal' => in_array($openModal, ['upload-asset', 'new-folder'], true) ? $openModal : null,
        ]);
    }

    public function show(Media $media): RedirectResponse
    {
        $this->authorization->abortUnlessMediaAccess(request()->user(), $media);

        return redirect()->route('admin.media.edit', array_filter([
            'media' => $media,
            'return_url' => $this->mediaIndexState->safeReturnUrlFromRequest(request()),
        ]));
    }

    public function edit(Media $media): View
    {
        $this->authorization->abortUnlessMediaAccess(request()->user(), $media);

        $usages = $this->mediaUsageResolver->resolve($media);

        return view('admin.media.edit', [
            'asset' => $media->load('folder'),
            'media' => $media->load('folder'),
            'folders' => $this->folderOptions(),
            'usages' => $usages,
            'mediaReturnUrl' => $this->mediaIndexState->returnUrl(request()),
            'showDeleteModal' => request()->string('modal')->toString() === 'delete-media',
            'showFileDetailsModal' => request()->string('modal')->toString() === 'file-details',
        ]);
    }

    public function update(MediaUpdateRequest $request, Media $media): RedirectResponse
    {
        $this->authorization->abortUnlessMediaAccess($request->user(), $media);
        $media->update($request->validated());

        return redirect()
            ->to($request->safeReturnUrl() ?: route('admin.media.index'))
            ->with('status', 'Media updated successfully.');
    }

    public function destroy(Media $media): RedirectResponse
    {
        $this->authorization->abortUnlessMediaAccess(request()->user(), $media);
        $usages = $this->mediaUsageResolver->resolve($media);
        $returnUrl = $this->mediaIndexState->safeReturnUrlFromRequest(request());

        if ($usages->isNotEmpty()) {
            $summary = $usages->take(3)->map(fn (array $usage) => $usage['context'].': '.$usage['label'])->implode(', ');

            return redirect()
                ->route('admin.media.edit', array_filter([
                    'media' => $media,
                    'return_url' => $returnUrl,
                ]))
                ->withErrors(['asset' => 'Media cannot be deleted because it is in use. '.$summary]);
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return redirect()
            ->to($returnUrl ?: route('admin.media.index'))
            ->with('status', 'Media deleted successfully.');
    }

    public function store(MediaUploadRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $mimeType = $file?->getMimeType();
        $extension = strtolower($file?->getClientOriginalExtension() ?: $file?->extension() ?: '');
        $kind = MediaKindResolver::resolve($mimeType, $extension);
        $disk = 'public';
        $directory = 'media/'.MediaKindResolver::directoryFor($kind);
        $filename = $this->buildFilename($file, $extension);
        $path = $file->storeAs($directory, $filename, $disk);

        $dimensions = $this->imageDimensions($file, $kind);

        Media::create([
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
            ->with('status', 'Media uploaded successfully.');
    }

    public function storeFolder(MediaFolderRequest $request): RedirectResponse
    {
        $folder = MediaFolder::create($request->validated());

        return redirect()
            ->route('admin.media.index', ['folder_id' => $folder->id])
            ->with('status', 'Folder created successfully.');
    }

    private function buildFilename(UploadedFile $file, string $extension): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return trim($name !== '' ? $name : 'media').'-'.Str::lower(Str::random(10)).($extension !== '' ? '.'.$extension : '');
    }

    private function imageDimensions(UploadedFile $file, string $kind): array
    {
        if ($kind !== Media::KIND_IMAGE) {
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
        return MediaFolder::query()
            ->with('parent')
            ->orderBy('name')
            ->get();
    }

    private function mediaListingQuery($user, ?int $folderId = null, ?string $search = null, ?string $kind = null, ?string $usage = null)
    {
        return $this->authorization->scopeMediaForUser(Media::query(), $user)
            ->with(['folder', 'uploader'])
            ->when($folderId, fn ($query) => $query->where('folder_id', $folderId))
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->tap(fn ($query) => $this->mediaUsageFilter->apply($query, $usage))
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
