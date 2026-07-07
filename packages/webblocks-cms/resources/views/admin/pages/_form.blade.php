@php
    $formSiteId = old('site_id', $page->site_id ?: ($selectedSiteId ?? $sites->first()?->id));
    $canEditContent = $canEditContent ?? true;
    $pageFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_form.'.$key, $replace);
@endphp

@php
    $pageLayoutOptions = $pageLayoutOptions ?? app(\WebBlocks\Cms\Support\Pages\PageLayoutManager::class)->pageSelectionOptions(old('public_shell', $page->publicShellPreset()));
@endphp

<div class="wb-stack wb-gap-4">
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
                <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $page->title) }}" required>
            </div>
            <div class="wb-stack-2 wb-field">
                <label for="slug">{{ $pageFormText('slug') }}</label>
                <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $page->slug) }}">
            </div>
        </div>
        <div class="wb-stack wb-gap-2">
            <div class="wb-stack-2 wb-field">
                <label for="public_shell">{{ $pageFormText('page_layout') }}</label>
                <select id="public_shell" name="public_shell" class="wb-select">
                    @foreach ($pageLayoutOptions as $layoutOption)
                        <option value="{{ $layoutOption['value'] }}" @selected(old('public_shell', $page->publicShellPreset()) === $layoutOption['value'])>{{ $layoutOption['label'] }}</option>
                    @endforeach
                </select>
                <span class="wb-text-sm wb-text-muted">{{ $pageFormText('page_layout_help') }}</span>
            </div>
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
            {{ $pageFormText('content_locked', ['workflow' => strtolower($page->workflowLabel())]) }}
        </div>
    @endif
</div>
