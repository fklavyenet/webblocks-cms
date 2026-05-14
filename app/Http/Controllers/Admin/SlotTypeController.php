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
        return view('admin.slot-types.index', [
            'slotTypes' => SlotType::query()
                ->withCount('blocks')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(AdminPagination::perPage())
                ->withQueryString(),
        ]);
    }
}
