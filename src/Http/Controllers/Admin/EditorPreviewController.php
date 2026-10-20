<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use WebBlocks\Cms\Http\Requests\Admin\EditorPreviewRequest;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\EditorPreview;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;

final class EditorPreviewController
{
  public function __invoke(EditorPreviewRequest $request, Page $page, EditorPreview $preview, PublicPagePresenter $presenter): JsonResponse
  {
    $graph = $preview->page($request, $page);
    $runtimeRequest = request();
    foreach ($request->validated('pagination', []) as $key => $value) {
      abort_unless(preg_match('/^wb_collection_([1-9][0-9]*)_page$/D', $key, $matches) && $graph->blocks->contains('id', (int) $matches[1]), 422);
      $runtimeRequest->query->set($key, $value);
    }
    $originalLocale = app()->getLocale();
    $runtimeRequest->attributes->set('cms_editor_preview', true);
    app()->setLocale($request->validated('locale'));
    try {
      $html = view('webblocks-cms::public.pages.show', [
        ...$presenter->present($graph, preview: true, locale: $request->validated('locale')),
        'previewMode' => true,
      ])->render();

      return response()->json(['html' => $html, 'issues' => $runtimeRequest->attributes->get('cms_editor_preview_issues', []), 'rendered_at' => now()->toIso8601String()])
        ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    } finally {
      app()->setLocale($originalLocale);
      $runtimeRequest->attributes->remove('cms_editor_preview');
    }
  }
}
