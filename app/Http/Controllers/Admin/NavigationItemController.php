<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NavigationItemReorderRequest;
use App\Http\Requests\Admin\NavigationItemRequest;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Support\Navigation\NavigationTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NavigationItemController extends Controller
{
    public function __construct(private readonly NavigationTree $tree) {}

    public function index(): View
    {
        $menuKey = request('menu_key', NavigationItem::MENU_PRIMARY);

        if (! in_array($menuKey, NavigationItem::menuKeys(), true)) {
            $menuKey = NavigationItem::MENU_PRIMARY;
        }

        return view('admin.navigation.index', [
            'activeMenuKey' => $menuKey,
            'menuOptions' => NavigationItem::menuOptions(),
            'items' => $this->tree->buildMenuTree($menuKey),
            'pages' => Page::query()->orderBy('title')->get(),
            'newItem' => new NavigationItem(['menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_PAGE, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'newGroup' => new NavigationItem(['menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_GROUP, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'editableItems' => NavigationItem::query()->forMenu($menuKey)->with('page')->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        $menuKey = request('menu_key', NavigationItem::MENU_PRIMARY);

        return view('admin.navigation.create', [
            'item' => new NavigationItem(['menu_key' => $menuKey, 'link_type' => NavigationItem::LINK_PAGE, 'visibility' => NavigationItem::VISIBILITY_VISIBLE]),
            'pages' => Page::query()->orderBy('title')->get(),
            'parents' => $this->tree->parentOptions($menuKey),
            'menuOptions' => NavigationItem::menuOptions(),
            'linkTypes' => NavigationItem::linkTypes(),
        ]);
    }

    public function store(NavigationItemRequest $request): RedirectResponse
    {
        NavigationItem::create($this->validatedData($request));

        return redirect()
            ->route('admin.navigation.index', ['menu_key' => $request->string('menu_key')->toString()])
            ->with('status', 'Navigation item created successfully.');
    }

    public function edit(NavigationItem $navigation): View
    {
        return view('admin.navigation.edit', [
            'item' => $navigation,
            'pages' => Page::query()->orderBy('title')->get(),
            'parents' => $this->tree->parentOptions($navigation->menu_key, $navigation->id),
            'menuOptions' => NavigationItem::menuOptions(),
            'linkTypes' => NavigationItem::linkTypes(),
        ]);
    }

    public function update(NavigationItemRequest $request, NavigationItem $navigation): RedirectResponse
    {
        $navigation->update($this->validatedData($request));

        return redirect()
            ->route('admin.navigation.index', ['menu_key' => $navigation->fresh()->menu_key])
            ->with('status', 'Navigation item updated successfully.');
    }

    public function destroy(NavigationItem $navigation): RedirectResponse
    {
        $menuKey = $navigation->menu_key;
        $navigation->delete();

        return redirect()
            ->route('admin.navigation.index', ['menu_key' => $menuKey])
            ->with('status', 'Navigation item deleted successfully.');
    }

    public function reorder(NavigationItemReorderRequest $request): JsonResponse
    {
        $menuKey = $request->string('menu_key')->toString();
        $items = $this->tree->validateAndNormalizeTreePayload($menuKey, $request->validated('items'));

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
        ]);
    }

    public function toggleVisibility(NavigationItem $navigation): RedirectResponse
    {
        $navigation->update([
            'visibility' => $navigation->isVisible() ? NavigationItem::VISIBILITY_HIDDEN : NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return redirect()
            ->route('admin.navigation.index', ['menu_key' => $navigation->menu_key])
            ->with('status', 'Navigation item updated successfully.');
    }

    private function validatedData(NavigationItemRequest $request): array
    {
        $data = $request->validated();

        $data['title'] = trim((string) ($data['title'] ?? '')) ?: null;
        $data['url'] = trim((string) ($data['url'] ?? '')) ?: null;
        $data['target'] = trim((string) ($data['target'] ?? '')) ?: null;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['position'] = (int) ($data['position'] ?? 1);
        $data['visibility'] = $data['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE;
        $data['is_system'] = (bool) ($request->route('navigation')?->is_system ?? false);

        if ($data['link_type'] === NavigationItem::LINK_PAGE) {
            $data['url'] = null;

            if (! $data['title'] && ! empty($data['page_id'])) {
                $data['title'] = Page::query()->find($data['page_id'])?->title;
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
                ->forMenu($data['menu_key'])
                ->where('parent_id', $data['parent_id'])
                ->max('position');

            $data['position'] = ((int) $maxPosition) + 1;
        }

        return $data;
    }
}
