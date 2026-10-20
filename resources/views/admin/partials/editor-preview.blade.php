@php($editorPreview = app(\WebBlocks\Cms\Support\Pages\EditorPreview::class)->form(request()->user(), $previewPage, $previewBlock ?? null, $previewLocale ?? null))
@if ($editorPreview)
    <div class="wb-editor-preview-controls wb-stack wb-gap-2" data-wb-editor-preview
         data-bridge="{{ asset('cms/js/admin/editor-preview-frame.js') }}" data-base="{{ url('/') }}/" data-endpoint="{{ $editorPreview['endpoint'] }}" data-form-locale="{{ $editorPreview['locale'] }}"
         data-last-render="{{ __('webblocks-cms::preview.last_render') }}" data-block-id="{{ $editorPreview['block'] }}" data-roots="{{ $editorPreview['roots'] }}"
         @foreach (['ready', 'pending', 'loading', 'current', 'failed', 'source_failed', 'unsupported', 'auth_failed'] as $message)
             data-message-{{ str_replace('_', '-', $message) }}="{{ __('webblocks-cms::preview.'.$message) }}"
         @endforeach>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <button type="button" class="wb-btn wb-btn-secondary wb-editor-preview-toggle" data-preview-toggle @disabled(! $editorPreview['supported']) aria-expanded="false">{{ __('webblocks-cms::preview.open') }}</button>
            <a href="{{ $editorPreview['separate'] }}?locale={{ $editorPreview['locale'] }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">{{ __('webblocks-cms::preview.separate') }}</a>
        </div>
        <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::preview.help') }}</p>
        @unless ($editorPreview['supported'])<p class="wb-alert wb-alert-info">{{ __('webblocks-cms::preview.unsupported') }}</p>@endunless
        <aside class="wb-card wb-editor-preview-panel" data-preview-panel hidden aria-label="{{ __('webblocks-cms::preview.title') }}">
            <div class="wb-card-header wb-stack wb-gap-2">
                <div class="wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ __('webblocks-cms::preview.title') }}</strong>
                    <button type="button" class="wb-btn wb-btn-sm wb-btn-secondary" data-preview-close>{{ __('webblocks-cms::preview.close') }}</button>
                </div>
                <span class="wb-text-sm">#{{ $previewPage->id }} · {{ $previewPage->title }} · {{ $previewPage->workflowLabel() }}@if ($previewBlock ?? null) · #{{ $previewBlock->id }} {{ $previewBlock->typeName() }}@endif · {{ strtoupper($editorPreview['locale']) }}</span>
                <span class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::preview.scope') }}</span>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <label class="wb-stack wb-gap-1">{{ __('webblocks-cms::preview.locale') }}
                        <select class="wb-select" data-preview-locale>
                            @foreach ($editorPreview['locales'] as $locale)
                                <option value="{{ $locale->code }}" @selected($locale->code === $editorPreview['locale'])>{{ $locale->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="wb-stack wb-gap-1">{{ __('webblocks-cms::preview.width') }}
                        <select class="wb-select" data-preview-width>
                            <option value="desktop">{{ __('webblocks-cms::preview.desktop') }}</option>
                            <option value="phone">{{ __('webblocks-cms::preview.phone') }}</option>
                        </select>
                    </label>
                    <button type="button" class="wb-btn wb-btn-primary" data-preview-refresh>{{ __('webblocks-cms::preview.refresh') }}</button>
                </div>
                <span class="wb-text-sm" data-preview-status role="status" aria-live="polite">{{ __('webblocks-cms::preview.ready') }}</span>
                <span class="wb-text-sm wb-text-muted" data-preview-time></span>
            </div>
            <div class="wb-editor-preview-viewport" data-preview-viewport data-width="desktop">
                <iframe title="{{ __('webblocks-cms::preview.title') }}" data-preview-frame sandbox="allow-scripts" referrerpolicy="no-referrer"></iframe>
            </div>
        </aside>
    </div>
@endif
