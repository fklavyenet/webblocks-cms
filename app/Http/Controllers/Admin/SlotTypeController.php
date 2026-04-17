<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SlotType;
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
                ->paginate(15)
                ->withQueryString(),
        ]);
    }
}
