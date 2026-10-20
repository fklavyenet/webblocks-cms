<?php

namespace WebBlocks\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\EditorRecovery\EditorRecovery;

class ApplyEditorRecovery
{
  public function handle(Request $request, Closure $next)
  {
    if ($request->isMethod('GET') && $request->query('_editor_recovery_restore') !== null) {
      $name = $request->route()?->getName();
      abort_unless(in_array($name, ['admin.pages.edit', 'admin.blocks.edit', 'admin.pages.slots.blocks'], true), 404);
      $block = $request->route('block');
      $page = $request->route('page') ?? $block?->page;
      $blockId = $block?->id ?? ($name === 'admin.pages.slots.blocks' ? $request->integer('edit') : null);
      abort_unless($page instanceof Page && (int) $request->query('_editor_recovery_block') === (int) $blockId, 404);
      app(EditorRecovery::class)->consumeRestore($request, $page, $blockId);
    }

    return $next($request);
  }
}
