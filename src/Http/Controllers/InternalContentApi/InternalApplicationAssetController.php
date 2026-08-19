<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Applications\ApplicationAssetStore;

class InternalApplicationAssetController extends Controller
{
  public function __construct(private readonly ApplicationAssetStore $assets) {}

  public function index(Site $site, string $application): JsonResponse
  {
    $record = $this->application($application);

    return $this->ok(['site_id' => $site->id, 'application_handle' => $record->handle, 'assets' => array_map(fn (array $asset): array => $this->present($asset, false), $this->assets->all($site, $record))]);
  }

  public function show(Site $site, string $application, string $type, string $filename): JsonResponse
  {
    return $this->ok(['asset' => $this->present($this->assets->read($site, $this->application($application), $type, $filename))]);
  }

  public function update(Request $request, Site $site, string $application, string $type, string $filename): JsonResponse
  {
    $validator = Validator::make($request->all(), ['contents' => ['required', 'string', 'max:1000000'], 'expected_checksum' => ['nullable', 'string', 'size:64']]);
    if ($validator->fails()) {
    return $this->invalid($validator->errors()->first());
    }

    try {
      $asset = $this->assets->write($site, $this->application($application), $type, $filename, (string) $validator->validated()['contents'], $validator->validated()['expected_checksum'] ?? null);
    } catch (RuntimeException $exception) {
      return $this->invalid($exception->getMessage());
    }

    return $this->ok(['asset' => $this->present($asset), 'writes' => [['type' => 'application_asset', 'id' => $application.'/'.$filename]]]);
  }

  public function destroy(Request $request, Site $site, string $application, string $type, string $filename): JsonResponse
  {
    $validator = Validator::make($request->all(), ['expected_checksum' => ['required', 'string', 'size:64']]);
    if ($validator->fails()) {
    return $this->invalid($validator->errors()->first());
    }

    try {
      $record = $this->application($application);
      $current = $this->assets->read($site, $record, $type, $filename);
      $references = collect($current['type'] === 'css' ? $record->css_assets : $record->js_assets)
        ->map(fn (array|string $asset): string => is_array($asset) ? (string) ($asset['path'] ?? '') : $asset);

      if ($references->contains($current['public_path'])) {
        return $this->invalid('Remove this path from the Embedded Application definition before deleting its file.');
      }

      $asset = $this->assets->delete($site, $record, $type, $filename, $validator->validated()['expected_checksum']);
    } catch (RuntimeException $exception) {
      return $this->invalid($exception->getMessage());
    }

    return $this->ok(['deleted_asset' => $this->present($asset)]);
  }

  private function application(string $handle): EmbeddedApplication
  {
    return EmbeddedApplication::query()->where('handle', $handle)->firstOrFail();
  }

  private function present(array $asset, bool $includeContents = true): array
  {
    unset($asset['absolute_path']);

    if (! $includeContents) {
      unset($asset['contents']);
    }

    return $asset;
  }

  private function ok(array $payload): JsonResponse
  {
    return response()->json(['ok' => true, ...$payload, 'warnings' => [], 'errors' => []]);
  }

  private function invalid(string $message): JsonResponse
  {
    return response()->json(['ok' => false, 'warnings' => [], 'errors' => [['path' => 'application_asset', 'message' => $message]]], 422);
  }
}
