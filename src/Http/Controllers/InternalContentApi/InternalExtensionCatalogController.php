<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use WebBlocks\Cms\Actions\Extensions\CatalogInstallStateConflict;
use WebBlocks\Cms\Actions\Extensions\InstallCatalogExtension;
use WebBlocks\Cms\Http\Requests\InternalContentApi\CatalogExtensionBrowseRequest;
use WebBlocks\Cms\Http\Requests\InternalContentApi\CatalogExtensionInstallRequest;
use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;
use WebBlocks\Cms\Support\Translations\LanguagePackCatalog;
use WebBlocks\Cms\Support\WebBlocks;

final class InternalExtensionCatalogController
{
  public function themes(CatalogExtensionBrowseRequest $request, ThemeCatalog $catalog): JsonResponse
  {
    abort_unless($catalog->enabled(), 503);
    $page = (int) ($request->validated('page') ?? 1);
    $result = $catalog->browse($page);

    return response()->json(['ok' => true, 'data' => $result['themes'], 'page' => $page, 'has_more' => $result['hasMore']]);
  }

  public function languages(LanguagePackCatalog $catalog): JsonResponse
  {
    abort_unless($catalog->enabled(), 503);

    return response()->json(['ok' => true, 'data' => array_values($catalog->browse()), 'cms_version' => WebBlocks::VERSION]);
  }

  public function install(CatalogExtensionInstallRequest $request, InstallCatalogExtension $installer, string $handle, string $packageType, string $operation): JsonResponse
  {
    $data = $request->validated();
    $context = new ExtensionOperation($packageType === 'theme' ? 'theme' : 'language_pack', $operation, 'download');
    $context->identify($handle, $data['version']);
    $context->targetCms(WebBlocks::VERSION);
    try {
      $installed = $packageType === 'theme'
        ? $installer->theme($handle, $data['version'], $data['checksum'], $operation === 'update', $context)
        : $installer->language($handle, $data['version'], $data['checksum'], $operation === 'update', $context);
    } catch (CatalogInstallStateConflict) {
      return response()->json(['ok' => false, 'code' => 'catalog_state_conflict', 'message' => $context->fail(), 'operation' => $context->context()], 409);
    } catch (Throwable $exception) {
      return response()->json(['ok' => false, 'code' => 'catalog_'.$operation.'_failed', 'message' => $context->fail(), 'operation' => $context->context()], $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 422);
    }

    return response()->json(['ok' => true, $operation === 'update' ? 'updated' : 'installed' => $installed], $operation === 'update' ? 200 : 201);
  }
}
