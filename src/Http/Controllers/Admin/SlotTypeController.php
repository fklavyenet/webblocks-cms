<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Queries\AdminTableSort;
use WebBlocks\Cms\Support\Admin\AdminPagination;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class SlotTypeController extends Controller
{
  public function index(): View
  {
    $totalCount = SlotType::query()->count();
    $slotTypes = SlotType::query()
      ->withCount('blocks')
      ->orderBy('sort_order')
      ->orderBy('name')
      ->tap(fn ($query) => AdminTableSort::for('slot-types')->apply($query))
      ->paginate(AdminPagination::perPage())
      ->withQueryString();

    AdminPagination::redirectOutOfRange($slotTypes);

    return view(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.slot-types.index', [
      'slotTypes' => $slotTypes,
      'totalCount' => $totalCount,
      'filteredCount' => $slotTypes->total(),
    ]);
  }
}
