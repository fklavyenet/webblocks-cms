@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $uiManagerText = fn (string $key, array $replace = [], ?string $fallback = null): string => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
        ->plugin('webblocks-ui-manager', 'admin.'.$key, $adminLocale, $replace, $fallback);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $uiManagerText('releases.singular_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $title,
        'description' => $uiManagerText('releases.form_description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <form method="POST" action="{{ $formAction }}">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $uiManagerText('releases.metadata') }}</strong></div>
            <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                <div class="wb-stack wb-gap-1">
                    <label for="version">{{ $uiManagerText('releases.version') }}</label>
                    <input id="version" name="version" class="wb-input" value="{{ old('version', $release->version) }}" required>
                    @error('version')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="label">{{ $uiManagerText('releases.label') }}</label>
                    <input id="label" name="label" class="wb-input" value="{{ old('label', $release->label) }}">
                    @error('label')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="status">{{ $uiManagerText('releases.status') }}</label>
                    <select id="status" name="status" class="wb-select">
                        @foreach (['draft' => 'Draft', 'prepared' => 'Prepared', 'published' => 'Published', 'blocked' => 'Blocked', 'publish_failed' => 'Publish Failed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $release->status ?: 'draft') === $value)>{{ $uiManagerText('statuses.'.$value, fallback: $label) }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="cdn_base_path">{{ $uiManagerText('releases.cdn_base_path') }}</label>
                    <input id="cdn_base_path" name="cdn_base_path" class="wb-input" value="{{ old('cdn_base_path', $release->cdn_base_path) }}">
                    @error('cdn_base_path')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="cdn_base_url">{{ $uiManagerText('releases.cdn_base_url') }}</label>
                    <input id="cdn_base_url" name="cdn_base_url" class="wb-input" value="{{ old('cdn_base_url', $release->cdn_base_url) }}">
                    @error('cdn_base_url')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
                <div class="wb-stack wb-gap-1">
                    <label for="notes">{{ $uiManagerText('releases.notes') }}</label>
                    <textarea id="notes" name="notes" class="wb-textarea" rows="5">{{ old('notes', $release->notes) }}</textarea>
                    @error('notes')<div class="wb-text-sm wb-text-danger">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('webblocks.plugins.webblocks_ui_manager.releases.index')" :submit-label="$uiManagerText('releases.save_release')" />
            </div>
        </div>
    </form>
@endsection
