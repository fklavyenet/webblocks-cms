<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\SlotType;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'pages' => Page::count(),
                'media' => Asset::count(),
                'blocks' => Block::count(),
                'slotTypes' => SlotType::count(),
                'blockTypes' => BlockType::count(),
                'draftPages' => Page::query()->where('status', 'draft')->count(),
                'publishedPages' => Page::query()->where('status', 'published')->count(),
            ],
            'recentPages' => Page::query()
                ->with('slots.slotType')
                ->latest()
                ->limit(5)
                ->get(),
            'recentAssets' => Asset::query()
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
