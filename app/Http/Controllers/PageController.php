<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Pages\PageRouteResolver;
use App\Support\Pages\PublicPagePresenter;
use App\Support\Visitors\VisitorEventLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicPagePresenter $presenter,
        private readonly PageRouteResolver $routeResolver,
        private readonly VisitorEventLogger $visitorEventLogger,
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

        return $this->renderPage($request, $homePage);
    }

    public function show(Request $request, string $localeOrSlug, ?string $slug = null): View
    {
        $resolvedSlug = $slug ?? $localeOrSlug;

        $page = $this->routeResolver->findPublishedPage($request, $resolvedSlug);

        abort_unless($page, 404);

        return $this->renderPage($request, $page);
    }

    private function renderPage(Request $request, Page $page): View
    {
        abort_if($page->isSharedSlotSourcePage(), 404);

        $page->load([
            'site',
            'translations.locale',
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->where('status', 'published')
                ->with($this->publishedBlockRelations())
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        $this->visitorEventLogger->logPageView($request, $page);

        return view('pages.show', $this->presenter->present($page));
    }

    private function publishedBlockRelations(): array
    {
        return [
            'blockType',
            'slotType',
            'asset',
            'blockAssets.asset',
            'textTranslations',
            'buttonTranslations',
            'imageTranslations',
            'contactFormTranslations',
            'children' => fn ($query) => $query
                ->where('status', 'published')
                ->with($this->publishedBlockRelations())
                ->orderBy('sort_order')
                ->orderBy('id'),
        ];
    }
}
