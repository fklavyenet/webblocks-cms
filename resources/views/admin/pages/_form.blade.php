@php
    $formSiteId = old('site_id', $page->site_id ?: ($selectedSiteId ?? $sites->first()?->id));
    $canEditContent = $canEditContent ?? true;
    $compactMode = $compactMode ?? false;
@endphp

<div class="{{ $compactMode ? 'wb-stack wb-gap-3 wb-admin-page-edit-form' : 'wb-stack wb-gap-4' }}">
    <div class="wb-grid wb-grid-2 wb-admin-page-edit-form-grid">
        <div class="{{ $compactMode ? 'wb-stack wb-gap-2' : 'wb-stack-4 wb-gap-1' }}">
            <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                @if ($page->exists)
                    <label>Site</label>
                    @if ($compactMode)
                        <div class="wb-admin-page-edit-readonly">{{ $page->site?->name ?? 'Site' }}</div>
                    @else
                        <input class="wb-input" type="text" value="{{ $page->site?->name ?? 'Site' }}" readonly>
                    @endif
                    <input type="hidden" name="site_id" value="{{ $page->site_id }}">
                    <span class="wb-text-sm wb-text-muted">Existing pages cannot be moved between sites from this form.</span>
                @else
                    <label for="site_id">Site</label>
                    <select id="site_id" name="site_id" class="wb-select" required>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) $formSiteId === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                <label for="title">Title</label>
                <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $page->title) }}" required>
            </div>
            <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                <label for="slug">Slug</label>
                <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $page->slug) }}">
            </div>
        </div>
        <div class="wb-stack wb-gap-2 wb-admin-page-edit-form-sidebar">
            <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                <label for="public_shell">Public Shell</label>
                <select id="public_shell" name="public_shell" class="wb-select">
                    <option value="default" @selected(old('public_shell', $page->publicShellPreset()) === 'default')>Default</option>
                    <option value="docs" @selected(old('public_shell', $page->publicShellPreset()) === 'docs')>Docs</option>
                </select>
                <span class="wb-text-sm wb-text-muted">Page-level outer shell. Default uses standard semantic slot wrappers. Docs automatically maps header, sidebar, and main slots to the docs shell wrappers.</span>
            </div>
            <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                <label>Locale</label>
                @if ($compactMode)
                    <div class="wb-admin-page-edit-readonly">English (default)</div>
                @else
                    <input class="wb-input" type="text" value="English (default)" disabled>
                @endif
            </div>
            @if ($page->exists)
                <div class="wb-field {{ $compactMode ? 'wb-stack wb-gap-1 wb-admin-page-edit-field' : 'wb-stack-2' }}">
                    <label>Workflow</label>
                    @if ($compactMode)
                        <div class="wb-admin-page-edit-readonly">{{ $page->workflowLabel() }}</div>
                    @else
                        <input class="wb-input" type="text" value="{{ $page->workflowLabel() }}" disabled>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if (! $canEditContent)
        <div class="wb-alert wb-alert-info">
            Content editing is locked while this page is {{ strtolower($page->workflowLabel()) }}. Move it back to draft to continue editing.
        </div>
    @endif
</div>
