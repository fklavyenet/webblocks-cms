<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\BulkDeleteMediaRequest;
use WebBlocks\Cms\Http\Requests\Admin\MediaFolderRequest;
use WebBlocks\Cms\Http\Requests\Admin\MediaUpdateRequest;
use WebBlocks\Cms\Http\Requests\Admin\MediaUploadRequest;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\Media\MediaBulkDeleter;
use WebBlocks\Cms\Support\Media\MediaDeleter;
use WebBlocks\Cms\Support\Media\MediaIndexState;
use WebBlocks\Cms\Support\Media\MediaInUseException;
use WebBlocks\Cms\Support\Media\MediaUploader;
use WebBlocks\Cms\Support\Media\MediaUsageFilter;
use WebBlocks\Cms\Support\Media\MediaUsageResolver;
use WebBlocks\Cms\Support\Users\AdminAuthorization;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class MediaController extends Controller
{
  /**
     * @var array<int, string>
     */
  private const ALLOWED_SORTS = [
    'created_at',
    'updated_at',
    'title',
    'filename',
    'kind',
    'folder',
    'usage',
  ];

  public function __construct(
    private readonly MediaUsageFilter $mediaUsageFilter,
    private readonly MediaUsageResolver $mediaUsageResolver,
    private readonly MediaIndexState $mediaIndexState,
    private readonly AdminAuthorization $authorization,
    private readonly MediaDeleter $mediaDeleter,
    private readonly MediaBulkDeleter $mediaBulkDeleter,
    private readonly MediaUploader $mediaUploader,
  ) {}

  public function index(): View
  {
    $user = request()->user();
    $selectedFolderId = request()->integer('folder_id') ?: null;
    $search = trim((string) request('search'));
    $kind = request()->string('kind')->toString();
    $usage = request()->string('usage')->toString();
    $sort = request()->string('sort')->toString();
    $direction = Str::lower(request()->string('direction')->toString()) === 'asc' ? 'asc' : 'desc';
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

    if (! in_array($sort, self::ALLOWED_SORTS, true)) {
      $sort = 'updated_at';
    }

    $totalMediaCount = $this->authorization->scopeMediaForUser(Media::query(), $user)->count();

    $mediaPaginator = $this->mediaListingQuery($user, $selectedFolderId, $search, $kind, $usage, $sort, $direction)
      ->paginate(AdminPagination::perPage())
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

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.media.index', [
      'folders' => $this->folderOptions(),
      'assets' => $media,
      'media' => $media,
      'selectedFolderId' => $selectedFolderId,
      'search' => $search,
      'kind' => $kind,
      'usage' => $usage,
      'sort' => $sort,
      'direction' => $direction,
      'viewMode' => $view,
      'totalMediaCount' => $totalMediaCount,
      'filteredMediaCount' => $media->total(),
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

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.media.edit', [
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
    $returnUrl = $this->mediaIndexState->safeReturnUrlFromRequest(request());

    try {
      $this->mediaDeleter->delete($media);
    } catch (MediaInUseException $exception) {
      return redirect()
        ->route('admin.media.edit', array_filter([
          'media' => $media,
          'return_url' => $returnUrl,
        ]))
        ->withErrors(['asset' => 'Media cannot be deleted because it is in use. '.$exception->summary()]);
    }

    return redirect()
      ->to($returnUrl ?: route('admin.media.index'))
      ->with('status', 'Media deleted successfully.');
  }

  public function bulkDestroy(BulkDeleteMediaRequest $request): RedirectResponse
  {
    $result = $this->mediaBulkDeleter->deleteSelected($request->user(), $request->validated('media_ids'));
    $returnUrl = $this->mediaIndexState->safeReturnUrlFromRequest($request);

    $redirect = redirect()
      ->to($returnUrl ?: route('admin.media.index'))
      ->with($result->deletedCount() > 0 ? 'status' : 'bulk_status', $result->message());

    if ($result->hasFailures()) {
      $redirect->withErrors(['media' => implode(' ', $result->failureMessages())]);
    }

    return $redirect;
  }

  public function store(MediaUploadRequest $request): RedirectResponse
  {
    $this->mediaUploader->upload($request->file('file'), $request->validated(), $request->user()?->id);

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

  private function folderOptions()
  {
    return MediaFolder::query()
      ->withCount('assets')
      ->with('parent')
      ->orderBy('name')
      ->get();
  }

  private function mediaListingQuery($user, ?int $folderId = null, ?string $search = null, ?string $kind = null, ?string $usage = null, string $sort = 'updated_at', string $direction = 'desc')
  {
    $query = $this->authorization->scopeMediaForUser(Media::query(), $user)
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
      });

    return $this->applySorting($query, $sort, $direction);
  }

  private function applySorting(Builder $query, string $sort, string $direction): Builder
  {
    return match ($sort) {
      'created_at', 'updated_at', 'filename', 'kind' => $query
        ->orderBy('media.'.$sort, $direction)
        ->orderByDesc('media.updated_at')
        ->orderByDesc('media.id'),
      'title' => $query
        ->orderByRaw('case when media.title is null or trim(media.title) = ? then 1 else 0 end asc', [''])
        ->orderBy('media.title', $direction)
        ->orderBy('media.filename', $direction)
        ->orderByDesc('media.id'),
      'folder' => $query
        ->leftJoin('media_folders', 'media_folders.id', '=', 'media.folder_id')
        ->select('media.*')
        ->orderByRaw('case when media_folders.name is null or trim(media_folders.name) = ? then 1 else 0 end asc', [''])
        ->orderBy('media_folders.name', $direction)
        ->orderByRaw('case when media.title is null or trim(media.title) = ? then 1 else 0 end asc', [''])
        ->orderBy('media.title', $direction)
        ->orderBy('media.filename', $direction)
        ->orderByDesc('media.id'),
      'usage' => $query
        ->withCount([
          'blocks as direct_usage_count',
          'blockMedia as related_media_usage_count',
          'sitesUsingAsFavicon as favicon_usage_count',
          'sitesUsingAsSocialImage as social_image_usage_count',
          'pageTranslationsUsingAsOgImage as seo_usage_count',
        ])
        ->orderByRaw('(direct_usage_count + related_media_usage_count + favicon_usage_count + social_image_usage_count + seo_usage_count) '.$direction)
        ->orderByRaw('case when media.title is null or trim(media.title) = ? then 1 else 0 end asc', [''])
        ->orderBy('media.title', $direction)
        ->orderBy('media.filename', $direction)
        ->orderByDesc('media.id'),
      default => $query
        ->orderBy('media.updated_at', 'desc')
        ->orderByDesc('media.id'),
    };
  }
}
