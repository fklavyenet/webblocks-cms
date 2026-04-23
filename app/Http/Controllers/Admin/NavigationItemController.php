<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NavigationItemReorderRequest;
use App\Http\Requests\Admin\NavigationItemRequest;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Site;
use App\Support\Navigation\NavigationTree;
use App\Support\Users\AdminAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NavigationItemController extends Controller
{
    public function __construct(
        private readonly NavigationTree $tree,
        private readonly AdminAuthorization $authorization,
    ) {}

    public function index(): View
    {
        $menuKey = request('menu_key', NavigationItem::MENU_PRIMARY);
        $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), request()->user())->get();
        $siteId = request()->integer('site_id') ?: $sites->first()?->id;

        if (! in_array($menuKey, NavigationItem::menuKeys(), true)) {
            $menuKey = NavigationItem::MENU_PRIMARY;
        }

        $site = $sites->firstWhere('id', $siteId) ?? abort(403);

        return view('admin.navigation.index', [
            'site' => $site,
            'sites' => $sites,
            'activeMenuKey' => $menuKey,
            'menuOptions' => NavigationItem::menuOptions(),
            'items' => $this->tree->buildMenuTree($menuKey, $site),
            'pages' => Page::query()->where('site_id', $site->id)->with('translations')->orderBy('title')->get(),
            'newItem' => new NavigationItem(['site_id' => $site->id, 'menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_PAGE, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'newGroup' => new NavigationItem(['site_id' => $site->id, 'menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_GROUP, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'editableItems' => NavigationItem::query()->forSite($site)->forMenu($menuKey)->with('page')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $menuKey = request('menu_key', NavigationItem::MENU_PRIMARY);
        $sites = $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), request()->user())->get();
        $site = $sites->firstWhere('id', request()->integer('site_id')) ?? $sites->first() ?? abort(403);

        return view('admin.navigation.create', [
            'item' => new NavigationItem(['site_id' => $site->id, 'menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_PAGE, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'pages' => Page::query()->where('site_id', $site->id)->with('translations')->orderBy('title')->get(),
            'parents' => $this->tree->parentOptions($menuKey, $site),
            'menuOptions' => NavigationItem::menuOptions(),
            'linkTypes' => NavigationItem::linkTypes(),
            'site' => $site,
            'sites' => $sites,
        ]);
    }

    public function store(NavigationItemRequest $request): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $request->integer('site_id'));
        NavigationItem::create($this->validatedData($request));

        return redirect()
            ->route('admin.navigation.index', ['site_id' => $request->integer('site_id'), 'menu_key' => $request->string('menu_key')->toString()])
            ->with('status', 'Navigation item created successfully.');
    }

    public function edit(NavigationItem $navigation): View
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $navigation);

        return view('admin.navigation.edit', [
            'item' => $navigation,
            'pages' => Page::query()->where('site_id', $navigation->site_id)->with('translations')->orderBy('title')->get(),
            'parents' => $this->tree->parentOptions($navigation->menu_key, $navigation->site_id, $navigation->id),
            'menuOptions' => NavigationItem::menuOptions(),
            'linkTypes' => NavigationItem::linkTypes(),
            'site' => $navigation->site,
            'sites' => $this->authorization->scopeSitesForUser(Site::query()->primaryFirst()->orderBy('name'), request()->user())->get(),
        ]);
    }

    public function update(NavigationItemRequest $request, NavigationItem $navigation): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $navigation);
        $this->authorization->abortUnlessSiteAccess($request->user(), $request->integer('site_id'));
        $navigation->update($this->validatedData($request));

        return redirect()
            ->route('admin.navigation.index', ['site_id' => $navigation->fresh()->site_id, 'menu_key' => $navigation->fresh()->menu_key])
            ->with('status', 'Navigation item updated successfully.');
    }

    public function destroy(NavigationItem $navigation): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $navigation);
        $menuKey = $navigation->menu_key;
        $navigation->delete();

        return redirect()
            ->route('admin.navigation.index', ['site_id' => $navigation->site_id, 'menu_key' => $menuKey])
            ->with('status', 'Navigation item deleted successfully.');
    }

    public function reorder(NavigationItemReorderRequest $request): JsonResponse
    {
        $this->authorization->abortUnlessSiteAccess($request->user(), $request->integer('site_id'));
        $menuKey = $request->string('menu_key')->toString();
        $siteId = $request->integer('site_id');
        $items = $this->tree->validateAndNormalizeTreePayload($menuKey, $siteId, $request->validated('items'));

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                NavigationItem::query()
                    ->whereKey($item['id'])
                    ->update([
                        'parent_id' => $item['parent_id'],
                        'position' => $item['position'],
                    ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Saved',
            'menu_key' => $menuKey,
            'site_id' => $siteId,
        ]);
    }

    public function toggleVisibility(NavigationItem $navigation): RedirectResponse
    {
        $this->authorization->abortUnlessSiteAccess(request()->user(), $navigation);
        $navigation->update([
            'visibility' => $navigation->isVisible() ? NavigationItem::VISIBILITY_HIDDEN : NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return redirect()
            ->route('admin.navigation.index', ['site_id' => $navigation->site_id, 'menu_key' => $navigation->menu_key])
            ->with('status', 'Navigation item updated successfully.');
    }

    private function validatedData(NavigationItemRequest $request): array
    {
        $data = $request->validated();
        $data['site_id'] = (int) $data['site_id'];

        $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
        $data['url'] = trim((string) ($data['url'] ?? '')) ?: null;
        $data['target'] = trim((string) ($data['target'] ?? '')) ?: null;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['position'] = (int) ($data['position'] ?? 1);
        $data['visibility'] = $data['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE;
        $data['is_system'] = (bool) ($request->route('navigation')?->is_system ?? false);

        if ($data['link_type'] === NavigationItem::LINK_PAGE) {
            $data['url'] = null;
            $data['site_id'] = Page::query()->find($data['page_id'])?->site_id ?? $data['site_id'];

            if (! $data['title'] && ! empty($data['page_id'])) {
                $data['title'] = Page::query()->with('translations')->find($data['page_id'])?->name;
            }
        }

        if ($data['link_type'] === NavigationItem::LINK_CUSTOM_URL) {
            $data['page_id'] = null;
        }

        if ($data['link_type'] === NavigationItem::LINK_GROUP) {
            $data['page_id'] = null;
            $data['url'] = null;
            $data['target'] = null;
        }

        if (! $request->filled('position')) {
            $maxPosition = NavigationItem::query()
                ->forSite($data['site_id'])
                ->forMenu($data['menu_key'])
                ->where('parent_id', $data['parent_id'])
                ->max('position');

            $data['position'] = ((int) $maxPosition) + 1;
        }

        return $data;
    }
}
