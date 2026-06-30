<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiPresenter;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotSyncer;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Sites\SiteAssetWriteException;

class InternalSiteController extends Controller
{
  public function __construct(
    private readonly InternalContentApiPresenter $presenter,
    private readonly PageLayoutSlotSyncer $slotSyncer,
    private readonly SiteAssetStore $siteAssets,
  ) {}

  public function updatePublicTheme(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('sites', 'public_theme_preset')) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Site public theme presets are not available until the latest site schema has been applied.'],
      ]);
    }

    $preset = trim((string) $request->input('public_theme_preset', $request->input('theme')));

    if (! in_array($preset, Site::PUBLIC_THEME_PRESETS, true)) {
      return $this->validationError([
        ['path' => 'site.public_theme_preset', 'message' => 'Public theme preset must be one of: '.implode(', ', Site::PUBLIC_THEME_PRESETS).'.'],
      ]);
    }

    $site->forceFill(['public_theme_preset' => $preset])->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'writes' => [['type' => 'site_public_theme_preset', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function updateBranding(Request $request, Site $site): JsonResponse
  {
    if (! Schema::hasColumn('sites', 'favicon_media_id') || ! Schema::hasColumn('sites', 'social_image_media_id')) {
      return $this->validationError([
        ['path' => 'site.branding', 'message' => 'Site branding media fields are not available until the latest site schema has been applied.'],
      ]);
    }

    $validator = Validator::make($request->all(), [
      'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
      'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
      'favicon_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
      'social_image_media_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Media::class, 'id')],
    ]);

    if ($validator->fails()) {
      return $this->validationError(collect($validator->errors()->toArray())
        ->map(fn (array $messages, string $field) => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all());
    }

    $data = $validator->validated();

    foreach (['favicon_media_id' => 'Favicon', 'social_image_media_id' => 'Social image'] as $field => $label) {
      if (! array_key_exists($field, $data) || $data[$field] === null) {
        continue;
      }

      $media = Media::query()->find((int) $data[$field]);

      if (! $media?->isImage()) {
        return $this->validationError([
          ['path' => $field, 'message' => $label.' media must be an image from Media.'],
        ]);
      }
    }

    if ($data === []) {
      return $this->validationError([
        ['path' => 'site.branding', 'message' => 'Provide at least one site branding field.'],
      ]);
    }

    $site->forceFill($data)->save();

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales', 'faviconMedia', 'socialImageMedia'])),
      'writes' => [['type' => 'site_branding', 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function showAsset(Site $site, string $type): JsonResponse
  {
    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'asset' => $this->apiAsset($this->siteAssets->read($site, $this->normalizeAssetType($type))),
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function updateAsset(Request $request, Site $site, string $type): JsonResponse
  {
    $type = $this->normalizeAssetType($type);

    $validator = Validator::make($request->all(), [
      'contents' => ['required', 'string', 'max:300000'],
      'expected_checksum' => ['nullable', 'string', 'size:64'],
    ]);

    if ($validator->fails()) {
      return $this->validationError(collect($validator->errors()->toArray())
        ->map(fn (array $messages, string $field) => [
          'path' => $field,
          'message' => $messages[0] ?? 'Invalid value.',
        ])
        ->values()
        ->all());
    }

    $data = $validator->validated();

    try {
      $asset = $this->siteAssets->write(
        $site,
        $type,
        (string) $data['contents'],
        $data['expected_checksum'] ?? null
      );
    } catch (SiteAssetWriteException $exception) {
      return response()->json([
        'ok' => false,
        'asset' => [
          'type' => $type,
          'readiness' => $exception->readiness,
        ],
        'warnings' => [],
        'errors' => [
          ['path' => 'asset.write', 'message' => $exception->getMessage()],
        ],
      ], 422);
    } catch (RuntimeException $exception) {
      return $this->validationError([
        ['path' => 'expected_checksum', 'message' => $exception->getMessage()],
      ]);
    }

    return response()->json([
      'ok' => true,
      'site' => $this->presenter->site($site->fresh(['locales'])),
      'asset' => $this->apiAsset($asset),
      'writes' => [['type' => 'site_asset_'.$type, 'id' => $site->id]],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  public function syncPageLayoutSlots(Page $page): JsonResponse
  {
    $before = $page->slots()->count();
    $added = $this->slotSyncer->seedInitialSlots($page, $page->publicShellPreset());
    $page = $page->fresh(['site.locales', 'translations.locale', 'slots.slotType', 'slots.sharedSlot']);

    return response()->json([
      'ok' => true,
      'page' => $this->presenter->page($page),
      'added_count' => $added,
      'before_count' => $before,
      'after_count' => $page->slots->count(),
      'writes' => $added > 0 ? [['type' => 'page_layout_slots_sync', 'id' => $page->id]] : [],
      'warnings' => [],
      'errors' => [],
    ]);
  }

  private function validationError(array $errors): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'warnings' => [],
      'errors' => $errors,
    ], 422);
  }

  private function normalizeAssetType(string $type): string
  {
    if (in_array($type, SiteAssetStore::TYPES, true)) {
      return $type;
    }

    throw new HttpResponseException($this->validationError([
      ['path' => 'type', 'message' => 'Site asset type must be one of: '.implode(', ', SiteAssetStore::TYPES).'.'],
    ]));
  }

  private function apiAsset(array $asset): array
  {
    unset($asset['absolute_path']);

    return $asset;
  }
}
