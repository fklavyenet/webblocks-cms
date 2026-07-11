@php
    $isPage = old('link_type', $item->link_type ?: \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) === \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE;
    $isUrl = old('link_type', $item->link_type ?: \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) === \WebBlocks\Cms\Models\NavigationItem::LINK_CUSTOM_URL;
    $isGroup = old('link_type', $item->link_type ?: \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP;
    $cancelUrl = $cancelUrl ?? route('admin.navigation.index', ['site_id' => old('site_id', $item->site_id ?: $site->id), 'menu_key' => old('menu_key', $item->menu_key ?: \WebBlocks\Cms\Models\NavigationItem::MENU_PRIMARY)]);
    $iconOptions = ($iconCatalog ?? app(\WebBlocks\Cms\Support\Icons\IconCatalog::class))->navigationPickerOptions(old('icon', $item->icon), $item->icon);
    $cancelType = $cancelType ?? 'link';
    $navigationFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.navigation_form.'.$key, $replace);
    $submitLabel = $submitLabel ?? ($item->exists ? $navigationFormText('save_changes') : $navigationFormText('create'));
    $formActionsContainerClass = $formActionsContainerClass ?? null;
@endphp

<div class="wb-stack wb-gap-4">
    <input type="hidden" name="site_id" value="{{ old('site_id', $item->site_id ?: $site->id) }}">

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label>{{ $navigationFormText('site') }}</label>
            <input class="wb-input" type="text" value="{{ $site->name }}" disabled>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="menu_key">{{ $navigationFormText('menu') }}</label>
            <select id="menu_key" name="menu_key" class="wb-select" required>
                @foreach ($menuOptions as $key => $label)
                    <option value="{{ $key }}" @selected(old('menu_key', $item->menu_key ?: \WebBlocks\Cms\Models\NavigationItem::MENU_PRIMARY) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('menu_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $navigationFormText('label_title') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $item->title) }}" placeholder="{{ $navigationFormText('label_title_placeholder') }}">
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('label_title_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-form-source-field>
            <label for="link_type">{{ $navigationFormText('link_source') }}</label>
            <select id="link_type" name="link_type" class="wb-select" required data-navigation-link-type>
                <option value="page" @selected(old('link_type', $item->link_type ?: 'page') === 'page')>{{ $navigationFormText('page') }}</option>
                <option value="custom_url" @selected(old('link_type', $item->link_type ?: 'page') === 'custom_url')>{{ $navigationFormText('custom_url') }}</option>
                <option value="group" @selected(old('link_type', $item->link_type ?: 'page') === 'group')>{{ $navigationFormText('group') }}</option>
            </select>
            <div class="wb-text-sm wb-text-muted" data-navigation-link-type-copy>
                @if ($isGroup)
                    {{ $navigationFormText('group_help') }}
                @elseif ($isUrl)
                    {{ $navigationFormText('custom_url_help') }}
                @else
                    {{ $navigationFormText('page_help') }}
                @endif
            </div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="parent_id">{{ $navigationFormText('parent_group') }}</label>
            <select id="parent_id" name="parent_id" class="wb-select">
                <option value="">{{ $navigationFormText('no_parent_group') }}</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent['id'] }}" @selected((string) old('parent_id', $item->parent_id) === (string) $parent['id'])>{{ $parent['label'] }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('parent_group_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-page-field @if ($isGroup || $isUrl) hidden @endif>
            <label for="page_id">{{ $navigationFormText('page') }}</label>
            <select id="page_id" name="page_id" class="wb-select" @disabled(! $isPage)>
                <option value="">{{ $navigationFormText('select_page') }}</option>
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}" @selected((string) old('page_id', $item->page_id) === (string) $page->id)>{{ $page->title }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('page_field_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1" data-navigation-url-field @if ($isGroup || $isPage) hidden @endif>
            <label for="url">{{ $navigationFormText('custom_url') }}</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $item->url) }}" placeholder="{{ $navigationFormText('url_placeholder') }}" @disabled(! $isUrl)>
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('url_help') }}</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1" data-navigation-target-field @if (! $isUrl) hidden @endif>
            <label for="target">{{ $navigationFormText('target') }}</label>
            <select id="target" name="target" class="wb-select" @disabled(! $isUrl)>
                <option value="_self" @selected(old('target', $item->target ?: '_self') === '_self')>_self</option>
                <option value="_blank" @selected(old('target', $item->target) === '_blank')>_blank</option>
            </select>
            <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('target_help') }}</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="visibility">{{ $navigationFormText('display') }}</label>
            <select id="visibility" name="visibility" class="wb-select" required>
                <option value="visible" @selected(old('visibility', $item->visibility ?: 'visible') === 'visible')>{{ $navigationFormText('visible') }}</option>
                <option value="hidden" @selected(old('visibility', $item->visibility) === 'hidden')>{{ $navigationFormText('hidden') }}</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="icon">{{ $navigationFormText('icon') }}</label>
        <select id="icon" name="icon" class="wb-select">
            <option value="">{{ $navigationFormText('no_icon') }}</option>
            @foreach ($iconOptions as $icon)
                <option value="{{ $icon['slug'] }}" @selected(old('icon', $item->icon) === $icon['slug'])>{{ $icon['label'] }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $navigationFormText('icon_help') }}</div>
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
                    var copyTexts = {
                        group: @js($navigationFormText('group_help')),
                        customUrl: @js($navigationFormText('custom_url_help')),
                        page: @js($navigationFormText('page_help'))
                    };

                    copy.textContent = isGroup
                        ? copyTexts.group
                        : (isUrl
                            ? copyTexts.customUrl
                            : copyTexts.page);
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
