@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $text = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('embedded_applications.'.$key, $locale);
    $editing = $application->exists;
    $jsAssets = old('js_assets', $application->js_assets ?? []);
    $settings = old('settings', collect($application->settings_schema ?? [])->map(fn ($value, $key) => ['key' => $key, ...$value, 'values' => isset($value['values']) ? implode(', ', $value['values']) : ''])->values()->all());
    $jsAssets = array_pad($jsAssets, max(3, count($jsAssets)), []);
    $settings = array_pad($settings, max(5, count($settings)), []);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $editing ? $text('edit_title') : $text('create_title'), 'heading' => $editing ? $text('edit_title') : $text('create_title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $editing ? $text('edit_title') : $text('create_title'), 'description' => $text('form_description')])
    @include('webblocks-cms::admin.partials.flash')
    <div class="wb-card">
        <form method="POST" action="{{ $editing ? route('admin.embedded-applications.update', $application) : route('admin.embedded-applications.store') }}" class="wb-stack wb-gap-0">
            @csrf
            @if ($editing) @method('PUT') @endif
            <div class="wb-card-body wb-stack wb-gap-4">
                @if ($errors->any())<div class="wb-alert wb-alert-danger"><div>{{ $errors->first() }}</div></div>@endif
                <div class="wb-alert wb-alert-warning"><div><strong>{{ $text('trust_title') }}</strong><div>{{ $text('trust_help') }}</div></div></div>
                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1"><label for="name">{{ $text('name') }}</label><input class="wb-input" id="name" name="name" required value="{{ old('name', $application->name) }}"></div>
                    <div class="wb-stack wb-gap-1"><label for="handle">{{ $text('handle') }}</label><input class="wb-input" id="handle" name="handle" required value="{{ old('handle', $application->handle) }}"></div>
                    <div class="wb-stack wb-gap-1"><label for="version">{{ $text('version') }}</label><input class="wb-input" id="version" name="version" required value="{{ old('version', $application->version ?: '1.0.0') }}"></div>
                    <div class="wb-stack wb-gap-1"><label for="render_mode">{{ $text('mode') }}</label><select class="wb-select" id="render_mode" name="render_mode"><option value="iframe" @selected(old('render_mode', $application->render_mode ?: 'iframe') === 'iframe')>iframe</option><option value="inline" @selected(old('render_mode', $application->render_mode) === 'inline')>inline</option></select></div>
                </div>
                <div class="wb-stack wb-gap-1"><label for="description">{{ $text('application_description') }}</label><textarea class="wb-textarea" id="description" name="description" rows="3">{{ old('description', $application->description) }}</textarea></div>
                <div class="wb-grid wb-grid-3">
                    <div class="wb-stack wb-gap-1"><label for="entry_url">{{ $text('entry_url') }}</label><input class="wb-input" id="entry_url" name="entry_url" placeholder="/applications/example/index.html" value="{{ old('entry_url', $application->entry_url) }}"></div>
                    <div class="wb-stack wb-gap-1"><label for="mount_element">{{ $text('mount_element') }}</label><select class="wb-select" id="mount_element" name="mount_element">@foreach(['div','section','canvas'] as $element)<option value="{{ $element }}" @selected(old('mount_element', $application->mount_element ?: 'div') === $element)>{{ $element }}</option>@endforeach</select></div>
                    <div class="wb-stack wb-gap-1"><label for="mount_classes">{{ $text('mount_classes') }}</label><input class="wb-input" id="mount_classes" name="mount_classes" value="{{ old('mount_classes', $application->mount_classes) }}"></div>
                </div>
                <div class="wb-stack wb-gap-1"><label for="css_urls">{{ $text('css_urls') }}</label><textarea class="wb-textarea" id="css_urls" name="css_urls" rows="3" placeholder="/applications/example/app.css">{{ old('css_urls', implode("\n", $application->css_assets ?? [])) }}</textarea><span class="wb-text-sm wb-text-muted">{{ $text('one_url_per_line') }}</span></div>
                <div class="wb-stack wb-gap-2"><strong>{{ $text('js_assets') }}</strong>@foreach($jsAssets as $index => $asset)<div class="wb-grid wb-grid-3"><input class="wb-input" name="js_assets[{{ $index }}][path]" placeholder="/applications/example/app.js" value="{{ $asset['path'] ?? '' }}"><select class="wb-select" name="js_assets[{{ $index }}][type]"><option value="classic" @selected(($asset['type'] ?? 'classic') === 'classic')>classic</option><option value="module" @selected(($asset['type'] ?? '') === 'module')>module</option></select><select class="wb-select" name="js_assets[{{ $index }}][load_position]"><option value="body_end" @selected(($asset['load_position'] ?? 'body_end') === 'body_end')>body_end</option><option value="head" @selected(($asset['load_position'] ?? '') === 'head')>head</option></select></div>@endforeach</div>
                <div class="wb-stack wb-gap-2"><strong>{{ $text('supports') }}</strong><div class="wb-cluster wb-cluster-4"><label><input type="checkbox" name="supports_locale" value="1" @checked(old('supports_locale', $application->supports['locale'] ?? false))> locale</label><label><input type="checkbox" name="supports_theme" value="1" @checked(old('supports_theme', $application->supports['theme'] ?? false))> theme</label><label><input type="checkbox" name="supports_fullscreen" value="1" @checked(old('supports_fullscreen', $application->supports['fullscreen'] ?? false))> fullscreen</label></div></div>
                <div class="wb-stack wb-gap-2"><strong>{{ $text('settings_schema') }}</strong><span class="wb-text-sm wb-text-muted">{{ $text('settings_help') }}</span>@foreach($settings as $index => $setting)<div class="wb-card wb-card-muted"><div class="wb-card-body wb-stack wb-gap-2"><div class="wb-grid wb-grid-4"><input class="wb-input" name="settings[{{ $index }}][key]" placeholder="key" value="{{ $setting['key'] ?? '' }}"><select class="wb-select" name="settings[{{ $index }}][type]">@foreach(['string','boolean','integer','enum'] as $type)<option value="{{ $type }}" @selected(($setting['type'] ?? 'string') === $type)>{{ $type }}</option>@endforeach</select><input class="wb-input" name="settings[{{ $index }}][default]" placeholder="default" value="{{ isset($setting['default']) ? (is_bool($setting['default']) ? ($setting['default'] ? 'true' : 'false') : $setting['default']) : '' }}"><input class="wb-input" name="settings[{{ $index }}][values]" placeholder="enum: small, medium, large" value="{{ $setting['values'] ?? '' }}"></div><div class="wb-grid wb-grid-3"><input class="wb-input" type="number" name="settings[{{ $index }}][min]" placeholder="integer min" value="{{ $setting['min'] ?? '' }}"><input class="wb-input" type="number" name="settings[{{ $index }}][max]" placeholder="integer max" value="{{ $setting['max'] ?? '' }}"><input class="wb-input" type="number" min="1" max="10000" name="settings[{{ $index }}][max_length]" placeholder="string max length" value="{{ $setting['max_length'] ?? '' }}"></div></div></div>@endforeach</div>
                <label><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $application->exists ? $application->is_enabled : true))> {{ $text('enabled') }}</label>
            </div>
            <div class="wb-card-footer"><x-webblocks-cms::admin.form-actions :cancel-url="route('admin.embedded-applications.index')" :submit-label="$text('save')" /></div>
        </form>
    </div>
    @if ($editing)<div class="wb-card wb-mt-4"><div class="wb-card-body"><button class="wb-btn wb-btn-danger" type="button" data-wb-toggle="modal" data-wb-target="#delete-embedded-application-{{ $application->id }}" aria-haspopup="dialog">{{ $text('delete') }}</button></div></div>@endif
@endsection

@if ($editing)
    @push('modals')
        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
            'id' => 'delete-embedded-application-'.$application->id,
            'title' => $text('delete'),
            'description' => $text('delete_description'),
            'action' => route('admin.embedded-applications.destroy', $application),
            'method' => 'DELETE',
            'submitLabel' => $text('delete'),
        ])
            <p>{{ $text('delete_confirm') }} <strong>{{ $application->name }}</strong>?</p>
        @endcomponent
    @endpush
@endif
