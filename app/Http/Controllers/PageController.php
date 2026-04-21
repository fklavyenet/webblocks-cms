<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Pages\PageRouteResolver;
use App\Support\Pages\PublicPagePresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicPagePresenter $presenter,
        private readonly PageRouteResolver $routeResolver,
    ) {}

    public function home(Request $request): View
    {
        $homePage = $this->routeResolver->findPublishedPage($request);

        if (! $homePage) {
            if ($request->route('locale')) {
                abort(404);
            }

            return view('welcome');
        }

        return $this->renderPage($homePage);
    }

    public function show(Request $request, string $localeOrSlug, ?string $slug = null): View
    {
        $resolvedSlug = $slug ?? $localeOrSlug;

        $page = $this->routeResolver->findPublishedPage($request, $resolvedSlug);

        abort_unless($page, 404);

        return $this->renderPage($page);
    }

    private function renderPage(Page $page): View
    {
        $page->load([
            'site',
            'translations.locale',
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->where('status', 'published')
                ->with(['blockType', 'slotType', 'asset', 'blockAssets.asset', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
                ->orderBy('sort_order'),
            'blocks.children' => fn ($query) => $query
                ->where('status', 'published')
                ->with(['blockType', 'slotType', 'asset', 'blockAssets.asset', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
                ->orderBy('sort_order'),
        ]);

        return view('pages.show', $this->presenter->present($page));
    }
}
