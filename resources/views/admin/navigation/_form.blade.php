@php
    $isPage = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_PAGE;
    $isUrl = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_CUSTOM_URL;
    $isGroup = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_GROUP;
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="menu_key">Menu</label>
            <select id="menu_key" name="menu_key" class="wb-select" required>
                @foreach ($menuOptions as $key => $label)
                    <option value="{{ $key }}" @selected(old('menu_key', $item->menu_key ?: \App\Models\NavigationItem::MENU_PRIMARY) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">Label / Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $item->title) }}" placeholder="Shown in the menu">
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="link_type">Link Source</label>
            <select id="link_type" name="link_type" class="wb-select" required>
                <option value="page" @selected(old('link_type', $item->link_type ?: 'page') === 'page')>Page</option>
                <option value="custom_url" @selected(old('link_type', $item->link_type ?: 'page') === 'custom_url')>Custom URL</option>
                <option value="group" @selected(old('link_type', $item->link_type ?: 'page') === 'group')>Group</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="parent_id">Parent Item</label>
            <select id="parent_id" name="parent_id" class="wb-select">
                <option value="">No parent</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $item->parent_id) === (string) $parent['id'])>{{ $parent['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="page_id">Page</label>
            <select id="page_id" name="page_id" class="wb-select" @disabled(! $isPage)>
                <option value="">Select a page</option>
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}" @selected((string) old('page_id', $item->page_id) === (string) $page->id)>{{ $page->title }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">Custom URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $item->url) }}" placeholder="https://example.com/path" @disabled(! $isUrl)>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="target">Target</label>
            <select id="target" name="target" class="wb-select" @disabled(! $isUrl)>
                <option value="_self" @selected(old('target', $item->target ?: '_self') === '_self')>_self</option>
                <option value="_blank" @selected(old('target', $item->target) === '_blank')>_blank</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="visibility">Display</label>
            <select id="visibility" name="visibility" class="wb-select" required>
                <option value="visible" @selected(old('visibility', $item->visibility ?: 'visible') === 'visible')>Visible</option>
                <option value="hidden" @selected(old('visibility', $item->visibility) === 'hidden')>Hidden</option>
            </select>
        </div>
    </div>

    <input type="hidden" name="position" value="{{ old('position', $item->position ?: 1) }}">

    <div class="wb-row wb-row-middle wb-justify-between wb-gap-2">
        <a href="{{ route('admin.navigation.index', ['menu_key' => old('menu_key', $item->menu_key ?: \App\Models\NavigationItem::MENU_PRIMARY)]) }}" class="wb-btn wb-btn-secondary">Back</a>
        <button type="submit" class="wb-btn wb-btn-primary">Save Navigation Item</button>
    </div>
</div>
