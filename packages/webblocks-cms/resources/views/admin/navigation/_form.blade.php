@php
    $isPage = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_PAGE;
    $isUrl = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_CUSTOM_URL;
    $isGroup = old('link_type', $item->link_type ?: \App\Models\NavigationItem::LINK_PAGE) === \App\Models\NavigationItem::LINK_GROUP;
    $cancelUrl = $cancelUrl ?? route('admin.navigation.index', ['site_id' => old('site_id', $item->site_id ?: $site->id), 'menu_key' => old('menu_key', $item->menu_key ?: \App\Models\NavigationItem::MENU_PRIMARY)]);
    $iconOptions = ($iconCatalog ?? app(\App\Support\Icons\IconCatalog::class))->navigationPickerOptions(old('icon', $item->icon), $item->icon);
    $cancelType = $cancelType ?? 'link';
    $submitLabel = $submitLabel ?? ($item->exists ? 'Save Changes' : 'Create');
    $formActionsContainerClass = $formActionsContainerClass ?? null;
@endphp

<div class="wb-stack wb-gap-4">
    <input type="hidden" name="site_id" value="{{ old('site_id', $item->site_id ?: $site->id) }}">

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label>Site</label>
            <input class="wb-input" type="text" value="{{ $site->name }}" disabled>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="menu_key">Menu</label>
            <select id="menu_key" name="menu_key" class="wb-select" required>
                @foreach ($menuOptions as $key => $label)
                    <option value="{{ $key }}" @selected(old('menu_key', $item->menu_key ?: \App\Models\NavigationItem::MENU_PRIMARY) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">This form stays within the currently selected site menu.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">Label / Title</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $item->title) }}" placeholder="Shown in the menu">
            <div class="wb-text-sm wb-text-muted">Groups need a label. Page links can leave this blank to reuse the page title.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-form-source-field>
            <label for="link_type">Link Source</label>
            <select id="link_type" name="link_type" class="wb-select" required data-navigation-link-type>
                <option value="page" @selected(old('link_type', $item->link_type ?: 'page') === 'page')>Page</option>
                <option value="custom_url" @selected(old('link_type', $item->link_type ?: 'page') === 'custom_url')>Custom URL</option>
                <option value="group" @selected(old('link_type', $item->link_type ?: 'page') === 'group')>Group</option>
            </select>
            <div class="wb-text-sm wb-text-muted" data-navigation-link-type-copy>
                @if ($isGroup)
                    Groups render as collapsible parent sections and can contain child navigation items.
                @elseif ($isUrl)
                    Custom URL items link directly to a path or external destination.
                @else
                    Page items link to a page from this site.
                @endif
            </div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="parent_id">Parent Group</label>
            <select id="parent_id" name="parent_id" class="wb-select">
                <option value="">No parent group</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $item->parent_id) === (string) $parent['id'])>{{ $parent['label'] }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">Select a group to nest this item underneath it. Normal links cannot be parents.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-page-field @if ($isGroup || $isUrl) hidden @endif>
            <label for="page_id">Page</label>
            <select id="page_id" name="page_id" class="wb-select" @disabled(! $isPage)>
                <option value="">Select a page</option>
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}" @selected((string) old('page_id', $item->page_id) === (string) $page->id)>{{ $page->title }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">Only pages from the current site can be linked here.</div>
        </div>

        <div class="wb-stack wb-gap-1" data-navigation-url-field @if ($isGroup || $isPage) hidden @endif>
            <label for="url">Custom URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $item->url) }}" placeholder="https://example.com/path" @disabled(! $isUrl)>
            <div class="wb-text-sm wb-text-muted">Use a full URL for external links or a leading slash for an internal path.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-target-field @if (! $isUrl) hidden @endif>
            <label for="target">Target</label>
            <select id="target" name="target" class="wb-select" @disabled(! $isUrl)>
                <option value="_self" @selected(old('target', $item->target ?: '_self') === '_self')>_self</option>
                <option value="_blank" @selected(old('target', $item->target) === '_blank')>_blank</option>
            </select>
            <div class="wb-text-sm wb-text-muted">Only custom URLs can open in a new tab.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="visibility">Display</label>
            <select id="visibility" name="visibility" class="wb-select" required>
                <option value="visible" @selected(old('visibility', $item->visibility ?: 'visible') === 'visible')>Visible</option>
                <option value="hidden" @selected(old('visibility', $item->visibility) === 'hidden')>Hidden</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="icon">Icon</label>
        <select id="icon" name="icon" class="wb-select">
            <option value="">No icon</option>
            @foreach ($iconOptions as $icon)
                <option value="{{ $icon['slug'] }}" @selected(old('icon', $item->icon) === $icon['slug'])>{{ $icon['label'] }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">Optional icon slug from the active navigation-context catalog. The full icon catalog stays on System -> Icons.</div>
    </div>

    <input type="hidden" name="position" value="{{ old('position', $item->position ?: 1) }}">

    <script>
        (function () {
            var currentScript = document.currentScript;

            if (!currentScript) {
                return;
            }

            var form = currentScript.closest('form');

            if (!form || form.dataset.navigationFormReady === '1') {
                return;
            }

            form.dataset.navigationFormReady = '1';

            var linkType = form.querySelector('[data-navigation-link-type]');
            var pageField = form.querySelector('[data-navigation-page-field]');
            var urlField = form.querySelector('[data-navigation-url-field]');
            var targetField = form.querySelector('[data-navigation-target-field]');
            var copy = form.querySelector('[data-navigation-link-type-copy]');
            var pageInput = form.querySelector('#page_id');
            var urlInput = form.querySelector('#url');
            var targetInput = form.querySelector('#target');

            if (!linkType) {
                return;
            }

            function sync() {
                var type = linkType.value;
                var isPage = type === 'page';
                var isUrl = type === 'custom_url';
                var isGroup = type === 'group';

                if (pageField) {
                    pageField.hidden = !isPage;
                }

                if (urlField) {
                    urlField.hidden = !isUrl;
                }

                if (targetField) {
                    targetField.hidden = !isUrl;
                }

                if (pageInput) {
                    pageInput.disabled = !isPage;
                }

                if (urlInput) {
                    urlInput.disabled = !isUrl;
                }

                if (targetInput) {
                    targetInput.disabled = !isUrl;
                }

                if (copy) {
                    copy.textContent = isGroup
                        ? 'Groups render as collapsible parent sections and can contain child navigation items.'
                        : (isUrl
                            ? 'Custom URL items link directly to a path or external destination.'
                            : 'Page items link to a page from this site.');
                }
            }

            linkType.addEventListener('change', sync);
            sync();
        })();
    </script>

    @if ($formActionsContainerClass)
        <x-webblocks-cms::admin.form-actions
            :cancel-url="$cancelUrl"
            :cancel-type="$cancelType"
            :submit-label="$submitLabel"
            :container-class="$formActionsContainerClass"
        />
    @endif
</div>
