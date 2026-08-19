@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $text = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('embedded_applications.'.$key, $locale);
    $editing = $application->exists;
    $jsAssets = old('js_assets', $application->js_assets ?? []);
    $settings = old('settings', collect($application->settings_schema ?? [])->map(fn ($value, $key) => ['key' => $key, ...$value, 'values' => isset($value['values']) ? implode(', ', $value['values']) : ''])->values()->all());
    $jsAssets = array_pad($jsAssets, max(3, count($jsAssets)), []);
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
                <div class="wb-card" data-wb-application-settings
                    data-edit-label="{{ $text('edit_setting') }}"
                    data-delete-label="{{ $text('delete_setting') }}"
                    data-no-default="{{ $text('no_default') }}"
                    data-no-constraints="{{ $text('no_constraints') }}"
                    data-enum-label="{{ $text('enum_values') }}"
                    data-min-label="{{ $text('integer_min') }}"
                    data-max-label="{{ $text('integer_max') }}"
                    data-max-length-label="{{ $text('string_max_length') }}"
                    data-duplicate-key="{{ $text('duplicate_setting_key') }}"
                    data-add-title="{{ $text('add_setting_title') }}"
                    data-edit-title="{{ $text('edit_setting_title') }}">
                    <div class="wb-card-header wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div>
                            <strong>{{ $text('settings_schema') }}</strong>
                            <div class="wb-text-sm wb-text-muted">{{ $text('settings_help') }}</div>
                        </div>
                        <button class="wb-btn wb-btn-primary" type="button" data-wb-setting-add data-wb-toggle="modal" data-wb-target="#embedded-application-setting-modal" aria-haspopup="dialog">
                            <i class="wb-icon wb-icon-plus" aria-hidden="true"></i> {{ $text('add_setting') }}
                        </button>
                    </div>
                    <div class="wb-card-body">
                        <div class="wb-alert wb-alert-info" data-wb-settings-empty @if(count($settings) > 0) hidden @endif><div>{{ $text('settings_empty') }}</div></div>
                        <div class="wb-table-wrap" data-wb-settings-table @if(count($settings) === 0) hidden @endif>
                            <table class="wb-table wb-table-striped wb-table-hover">
                                <thead><tr><th>{{ $text('setting_key') }}</th><th>{{ $text('setting_type') }}</th><th>{{ $text('setting_default') }}</th><th>{{ $text('setting_constraints') }}</th><th>{{ $text('actions') }}</th></tr></thead>
                                <tbody data-wb-settings-rows>
                                    @foreach($settings as $index => $setting)
                                        @php
                                            $default = isset($setting['default']) ? (is_bool($setting['default']) ? ($setting['default'] ? 'true' : 'false') : $setting['default']) : '';
                                            $constraints = collect([
                                                !empty($setting['values']) ? $text('enum_values').': '.$setting['values'] : null,
                                                isset($setting['min']) && $setting['min'] !== '' ? $text('integer_min').': '.$setting['min'] : null,
                                                isset($setting['max']) && $setting['max'] !== '' ? $text('integer_max').': '.$setting['max'] : null,
                                                isset($setting['max_length']) && $setting['max_length'] !== '' ? $text('string_max_length').': '.$setting['max_length'] : null,
                                            ])->filter()->implode(' · ');
                                        @endphp
                                        <tr data-wb-setting-row>
                                            <td data-wb-setting-summary="key">{{ $setting['key'] ?? '' }}</td>
                                            <td><code data-wb-setting-summary="type">{{ $setting['type'] ?? 'string' }}</code></td>
                                            <td data-wb-setting-summary="default">{{ $default !== '' ? $default : $text('no_default') }}</td>
                                            <td data-wb-setting-summary="constraints">{{ $constraints ?: $text('no_constraints') }}</td>
                                            <td class="wb-table-actions">
                                                <div class="wb-flex wb-items-center wb-gap-2">
                                                    <button class="wb-action-btn wb-action-btn-edit" type="button" data-wb-setting-edit data-wb-toggle="modal" data-wb-target="#embedded-application-setting-modal" title="{{ $text('edit_setting') }}" aria-label="{{ $text('edit_setting') }}"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></button>
                                                    <button class="wb-action-btn wb-action-btn-delete" type="button" data-wb-setting-delete title="{{ $text('delete_setting') }}" aria-label="{{ $text('delete_setting') }}"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                                </div>
                                                @foreach(['key','type','default','values','min','max','max_length'] as $field)<input type="hidden" data-wb-setting-field="{{ $field }}" name="settings[{{ $index }}][{{ $field }}]" value="{{ $field === 'default' ? $default : ($setting[$field] ?? '') }}">@endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <label><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $application->exists ? $application->is_enabled : true))> {{ $text('enabled') }}</label>
            </div>
            <div class="wb-card-footer"><x-webblocks-cms::admin.form-actions :cancel-url="route('admin.embedded-applications.index')" :submit-label="$text('save')" /></div>
        </form>
    </div>
    @if ($editing)<div class="wb-card wb-mt-4"><div class="wb-card-body"><button class="wb-btn wb-btn-danger" type="button" data-wb-toggle="modal" data-wb-target="#delete-embedded-application-{{ $application->id }}" aria-haspopup="dialog">{{ $text('delete') }}</button></div></div>@endif
@endsection

@push('modals')
    <div class="wb-modal wb-modal-lg" id="embedded-application-setting-modal" role="dialog" aria-modal="true" aria-labelledby="embedded-application-setting-modal-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="embedded-application-setting-modal-title" data-wb-setting-modal-title>{{ $text('add_setting_title') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $text('cancel') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <div class="wb-modal-body wb-stack wb-gap-4">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-stack wb-gap-1"><label for="setting-modal-key">{{ $text('setting_key') }}</label><input class="wb-input" id="setting-modal-key" data-wb-setting-input="key" required maxlength="64" pattern="[a-z][a-z0-9_]{0,63}"></div>
                    <div class="wb-stack wb-gap-1"><label for="setting-modal-type">{{ $text('setting_type') }}</label><select class="wb-select" id="setting-modal-type" data-wb-setting-input="type">@foreach(['string','boolean','integer','enum'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                </div>
                <div class="wb-stack wb-gap-1"><label for="setting-modal-default">{{ $text('setting_default') }}</label><input class="wb-input" id="setting-modal-default" data-wb-setting-input="default"></div>
                <div class="wb-stack wb-gap-1" data-wb-setting-group="enum"><label for="setting-modal-values">{{ $text('enum_values') }}</label><input class="wb-input" id="setting-modal-values" data-wb-setting-input="values" placeholder="small, medium, large"></div>
                <div class="wb-grid wb-grid-2" data-wb-setting-group="integer">
                    <div class="wb-stack wb-gap-1"><label for="setting-modal-min">{{ $text('integer_min') }}</label><input class="wb-input" type="number" id="setting-modal-min" data-wb-setting-input="min"></div>
                    <div class="wb-stack wb-gap-1"><label for="setting-modal-max">{{ $text('integer_max') }}</label><input class="wb-input" type="number" id="setting-modal-max" data-wb-setting-input="max"></div>
                </div>
                <div class="wb-stack wb-gap-1" data-wb-setting-group="string"><label for="setting-modal-max-length">{{ $text('string_max_length') }}</label><input class="wb-input" type="number" min="1" max="10000" id="setting-modal-max-length" data-wb-setting-input="max_length"></div>
            </div>
            <div class="wb-modal-footer"><button class="wb-btn wb-btn-primary" type="button" data-wb-setting-save>{{ $text('save_setting') }}</button><button class="wb-btn wb-btn-secondary" type="button" data-wb-setting-cancel data-wb-dismiss="modal">{{ $text('cancel') }}</button></div>
        </div>
    </div>
@endpush

@push('admin-scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/embedded-application-settings.js'])
@endpush

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
