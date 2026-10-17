<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use WebBlocks\Cms\Http\Requests\Admin\DynamicContentRequest;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\Support\ContentSources\DynamicContentInventory;

final class DynamicContentController extends Controller
{
  public function index(DynamicContentRequest $request, DynamicContentInventory $inventory): View
  {
    Gate::authorize('access-admin');
    $data = $inventory->build($request->user(), $request->integer('site') ?: null);
    $search = mb_substr(trim((string) $request->input('search', '')), 0, 160);
    $handle = trim((string) $request->input('source', ''));
    $tab = in_array($request->input('tab'), ['loops', 'bindings', 'issues'], true) ? $request->input('tab') : 'sources';
    $selected = $handle !== '' ? $data['sources']->get($handle) : null;
    abort_if($handle !== '' && ! $selected, 404);
    $rows = $data['usages']->filter(fn ($row) => ($handle === '' || $row['source'] === $handle)
      && ($search === '' || str_contains(mb_strtolower($row['path'].' '.$row['source'].' '.$row['owner'].' '.$row['field']), mb_strtolower($search))));
    $state = $request->input('state', '');
    if ($state !== '') {
    $rows = $rows->where('status', $state);
    }
    $rows = match ($tab) {
      'loops' => $rows->where('kind', 'loop'),
      'bindings' => $rows->where('kind', 'binding'),
      'issues' => $rows->filter(fn ($row) => ($data['sources']->get($row['source'])['state'] ?? 'missing') !== 'declared'
        || ($row['kind'] === 'binding' && ! isset($data['sources']->get($row['source'])['fields'][$row['field']]))
        || ($row['kind'] === 'binding' && $row['record'] === '@item' && ! $row['loop_id'])
        || ($row['kind'] === 'loop' && ! $row['template_valid'])),
      default => $rows,
    };
    $sources = $data['sources']->filter(fn ($source) => $search === '' || str_contains(mb_strtolower($source['label'].' '.$source['handle'].' '.$source['plugin']), mb_strtolower($search)));

    $items = ($tab === 'sources' ? $sources : $rows)->values();
    $paginator = new LengthAwarePaginator($items->forPage($request->integer('page', 1), 25)->values(), $items->count(), 25, $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
    AdminPagination::redirectOutOfRange($paginator);

    return view('webblocks-cms::admin.dynamic-content.index', $data + compact('selected', 'search', 'handle', 'tab', 'paginator', 'state') + ['rows' => $tab === 'sources' ? collect() : $paginator, 'filteredSources' => $tab === 'sources' ? $paginator : collect()]);
  }
}
