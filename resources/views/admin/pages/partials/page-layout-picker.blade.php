@php
    $canEditContent = $canEditContent ?? true;
    $pageFormLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $pageLayoutOptions = $pageLayoutOptions ?? app(\WebBlocks\Cms\Support\Pages\PageLayoutManager::class)->pageSelectionOptions(old('public_shell', $page->publicShellPreset()));
    $layoutText = fn (string $key) => $translator->admin('page_layout_picker.'.$key, $pageFormLocale);
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

<section class="wb-card wb-page-layout-picker" aria-labelledby="public-shell-label">
    <div class="wb-card-header">
        <strong id="public-shell-label">{{ $translator->admin('page_form.page_layout', $pageFormLocale) }}</strong>
    </div>
    <div class="wb-card-body wb-stack wb-gap-2">
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
                        <span class="wb-cluster wb-cluster-between wb-cluster-2">
                            <strong>{{ $label }}</strong>
                            <i class="wb-icon wb-icon-check wb-page-layout-selected" aria-hidden="true"></i>
                        </span>
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
</section>
