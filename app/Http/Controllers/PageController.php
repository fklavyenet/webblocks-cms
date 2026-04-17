<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Pages\PublicPagePresenter;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PublicPagePresenter $presenter) {}

    public function home(): View
    {
        $homePage = Page::query()
            ->where('status', 'published')
            ->orderByRaw("case when slug = 'home' then 0 else 1 end")
            ->orderBy('title')
            ->first();

        if (! $homePage) {
            return view('welcome');
        }

        return $this->renderPage($homePage);
    }

    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return $this->renderPage($page);
    }

    private function renderPage(Page $page): View
    {
        $page->load([
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->where('status', 'published')
                ->with(['blockType', 'slotType', 'asset', 'blockAssets.asset'])
                ->orderBy('sort_order'),
            'blocks.children' => fn ($query) => $query
                ->where('status', 'published')
                ->with(['blockType', 'slotType', 'asset', 'blockAssets.asset'])
                ->orderBy('sort_order'),
        ]);

        return view('pages.show', $this->presenter->present($page));
    }
}
