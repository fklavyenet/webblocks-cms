@php
    $formSiteId = old('site_id', $page->site_id ?: ($selectedSiteId ?? $sites->first()?->id));
    $canEditContent = $canEditContent ?? true;
    $pageFormLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pageFormText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_form.'.$key, $pageFormLocale, $replace);
@endphp

@php
    $pageLayoutOptions = $pageLayoutOptions ?? app(\WebBlocks\Cms\Support\Pages\PageLayoutManager::class)->pageSelectionOptions(old('public_shell', $page->publicShellPreset()));
@endphp

<div class="wb-stack wb-gap-4">
    @if ($page->isSystemPage())
        <div class="wb-alert wb-alert-info" role="status">{{ __('webblocks-cms::system_pages.editor_help') }}</div>
        @if ($systemPageKey ?? null)
            <table class="wb-table wb-table-break"><tbody><tr><th scope="row">{{ __('webblocks-cms::system_pages.screen') }}</th><td>{{ $systemPageLabel ?? $page->title }}</td></tr></tbody></table>
            <div class="wb-accordion" data-wb-accordion>
                <details class="wb-accordion-item">
                    <summary class="wb-accordion-trigger" id="system-page-details-trigger">
                        <span>{{ __('webblocks-cms::system_pages.technical_details') }}</span>
                    </summary>
                    <div class="wb-accordion-body">
                    <div id="system-page-details-panel" role="region" aria-labelledby="system-page-details-trigger">
                        <table class="wb-table wb-table-break"><tbody><tr><th scope="row">{{ __('webblocks-cms::system_pages.technical_identifier') }}</th><td><code>{{ $systemPageKey }}</code></td></tr></tbody></table>
                    </div>
                </div>
                </details>
            </div>
        @endif
    @endif
    @php
        $layoutText = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_layout_picker.'.$key, $pageFormLocale);
        $selectedLayout = old('public_shell', $page->publicShellPreset());
        $selectedSidebars = old('column_sidebars_submitted')
            ? old('column_sidebars', [])
            : ($page->exists ? $page->slots->map(fn ($slot) => $slot->slotSlug())->all() : []);
        $layoutDiagrams = [
            'default' => ['header', 'main', 'footer'],
            'article' => ['header', 'main', 'footer'],
            'docs' => ['sidebar', 'header', 'main', 'footer'],
            'columns' => ['header', 'sidebar-left', 'main', 'sidebar-right', 'footer'],
        ];
    @endphp
    <div class="wb-stack wb-gap-2 wb-page-layout-picker">
        <div class="wb-label" id="public-shell-label">{{ $pageFormText('page_layout') }}</div>
        <div class="wb-radio-group wb-page-layout-choices" role="radiogroup" aria-labelledby="public-shell-label">
            @foreach ($pageLayoutOptions as $layoutOption)
                @php
                    $value = $layoutOption['value'];
                    $diagram = $layoutDiagrams[$value] ?? [];
                    $label = isset($layoutDiagrams[$value]) ? $layoutText($value) : $layoutOption['label'];
                @endphp
                <label class="wb-radio-card">
                    <input type="radio" name="public_shell" value="{{ $value }}" @checked($selectedLayout === $value) @disabled(! $canEditContent)>
                    <span class="wb-radio-card-body">
                        <strong>{{ $label }}</strong>
                        @if ($diagram !== [])
                            <span class="wb-page-layout-diagram" data-layout="{{ $value }}" aria-hidden="true">
                                @foreach ($diagram as $slot)
                                    <span data-region="{{ $slot }}">{{ $layoutText($slot) }}</span>
                                @endforeach
                            </span>
                        @endif
                        @if (isset($layoutDiagrams[$value]))
                            <span class="wb-text-muted wb-text-sm">{{ $layoutText($value.'_help') }}</span>
                        @elseif ($layoutOption['description'] ?? null)
                            <span class="wb-text-muted wb-text-sm">{{ $layoutOption['description'] }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
        @error('public_shell')
            <div class="wb-alert wb-alert-danger" role="alert">{{ $message }}</div>
        @enderror
        @if ($canEditContent)
            <div class="wb-page-layout-sidebars wb-cluster wb-cluster-4 wb-flex-wrap" role="group" aria-label="{{ $layoutText('sidebar_options') }}">
                <input type="hidden" name="column_sidebars_submitted" value="1">
                @foreach (['sidebar-left', 'sidebar-right'] as $side)
                    <label class="wb-check">
                        <input type="checkbox" name="column_sidebars[]" value="{{ $side }}" @checked(in_array($side, (array) $selectedSidebars, true))>
                        <span>{{ $layoutText($side) }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('column_sidebars')
            <div class="wb-alert wb-alert-danger" role="alert">{{ $message }}</div>
        @enderror
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack-4 wb-gap-1">
            <div class="wb-stack-2 wb-field">
                @if ($page->exists)
                    <label>{{ $pageFormText('site') }}</label>
                    <input class="wb-input" type="text" value="{{ $page->site?->name ?? $pageFormText('site') }}" readonly>
                    <input type="hidden" name="site_id" value="{{ $page->site_id }}">
                    <span class="wb-text-sm wb-text-muted">{{ $pageFormText('existing_site_help') }}</span>
                @else
                    <label for="site_id">{{ $pageFormText('site') }}</label>
                    <select id="site_id" name="site_id" class="wb-select" required>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) $formSiteId === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="wb-stack-2 wb-field">
                <label for="title">{{ $pageFormText('title') }}</label>
                <input id="title" name="title" @disabled(! $canEditContent) class="wb-input" type="text" value="{{ old('title', $page->title) }}" required>
            </div>
            <div class="wb-stack-2 wb-field">
                <label for="slug">{{ $pageFormText('slug') }}</label>
                <input id="slug" name="slug" @readonly($page->isSystemPage()) @disabled(! $canEditContent) class="wb-input" type="text" value="{{ old('slug', $page->slug) }}">
            </div>
        </div>
        <div class="wb-stack wb-gap-2">
            <div class="wb-stack-2 wb-field">
                <label>{{ $pageFormText('locale') }}</label>
                <input class="wb-input" type="text" value="{{ $pageFormText('default_locale') }}" disabled>
            </div>
            @if ($page->exists)
                <div class="wb-stack-2 wb-field">
                    <label>{{ $pageFormText('workflow') }}</label>
                    <input class="wb-input" type="text" value="{{ $page->workflowLabel() }}" disabled>
                </div>
            @endif
        </div>
    </div>

    @if (! $canEditContent)
        <div class="wb-alert wb-alert-info">
            {{ __('webblocks-cms::acl.edit_locked') }}
        </div>
    @endif
</div>
