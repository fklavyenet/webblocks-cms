<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotType;
use App\Support\Admin\AdminPagination;
use Illuminate\View\View;

class SlotTypeController extends Controller
{
    public function index(): View
    {
        $totalCount = SlotType::query()->count();
        $slotTypes = SlotType::query()
            ->withCount('blocks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(AdminPagination::perPage())
            ->withQueryString();

        return view('admin.slot-types.index', [
            'slotTypes' => $slotTypes,
            'totalCount' => $totalCount,
            'filteredCount' => $slotTypes->total(),
        ]);
    }
}
