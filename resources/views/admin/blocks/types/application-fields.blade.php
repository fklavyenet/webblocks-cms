@php
    $registry = app(\WebBlocks\Cms\Support\Applications\ApplicationRegistry::class);
    $applications = $registry->all();
    $selectedHandle = old($oldPrefix.'application_handle', (string) $block->setting('application_handle', ''));
    $selectedDefinition = $applications->get($selectedHandle);
    $storedApplicationSettings = $block->applicationSettings();
    $fieldName = fn (string $name) => $namePrefix === '' ? $name : $namePrefix.'['.$name.']';
    $fieldId = fn (string $name) => $idPrefix.$name;
    $settingName = fn (string $name) => $namePrefix === '' ? 'application_settings['.$name.']' : $namePrefix.'[application_settings]['.$name.']';
    $settingOld = fn (string $name, mixed $default = null) => old($oldPrefix.'application_settings.'.$name, $storedApplicationSettings[$name] ?? $default);
    $boolValue = fn (string $field, bool $current) => old($oldPrefix.$field, $current ? '1' : '0') === '1';
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="{{ $fieldId('application_handle') }}">{{ $adminText('application_label') }}</label>
        <select id="{{ $fieldId('application_handle') }}" name="{{ $fieldName('application_handle') }}" class="wb-select" required>
            <option value="">{{ $adminText('application_placeholder') }}</option>
            @foreach ($applications as $application)
                <option value="{{ $application->handle }}" @selected($selectedHandle === $application->handle) @disabled(! $application->isReady())>
                    {{ $application->name }} — {{ $application->renderMode }}{{ $application->isReady() ? '' : ' ('.$adminText('not_ready').')' }}
                </option>
            @endforeach
        </select>
        <span class="wb-text-sm wb-text-muted">{{ $adminText('application_help') }}</span>
    </div>

    @if ($selectedDefinition)
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-1">
                <strong>{{ $selectedDefinition->name }} · {{ $selectedDefinition->version }}</strong>
                <span class="wb-text-sm wb-text-muted">{{ $selectedDefinition->description ?? $adminText('manifest_application') }}</span>
            </div>
        </div>

        @if ($selectedDefinition->settingsSchema !== [])
            <div class="wb-grid wb-grid-2">
                @foreach ($selectedDefinition->settingsSchema as $key => $schema)
                    @php($value = $settingOld($key, $schema['default'] ?? null))
                    <div class="wb-stack wb-gap-1">
                        <label for="{{ $fieldId('application_setting_'.$key) }}">{{ str($key)->headline() }}</label>
                        @if ($schema['type'] === 'boolean')
                            <select id="{{ $fieldId('application_setting_'.$key) }}" name="{{ $settingName($key) }}" class="wb-select">
                                <option value="1" @selected(filter_var($value, FILTER_VALIDATE_BOOL))>{{ $adminText('option_yes') }}</option>
                                <option value="0" @selected(! filter_var($value, FILTER_VALIDATE_BOOL))>{{ $adminText('option_no') }}</option>
                            </select>
                        @elseif ($schema['type'] === 'enum')
                            <select id="{{ $fieldId('application_setting_'.$key) }}" name="{{ $settingName($key) }}" class="wb-select">
                                @foreach ($schema['values'] as $option)
                                    <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                id="{{ $fieldId('application_setting_'.$key) }}"
                                name="{{ $settingName($key) }}"
                                class="wb-input"
                                type="{{ $schema['type'] === 'integer' ? 'number' : 'text' }}"
                                value="{{ $value }}"
                                @if (isset($schema['min'])) min="{{ $schema['min'] }}" @endif
                                @if (isset($schema['max'])) max="{{ $schema['max'] }}" @endif
                                @if (isset($schema['max_length'])) maxlength="{{ $schema['max_length'] }}" @endif
                            >
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_width') }}">{{ $adminText('width_label') }}</label>
            <select id="{{ $fieldId('application_width') }}" name="{{ $fieldName('application_width') }}" class="wb-select" required>
                @foreach (['content', 'wide', 'full'] as $option)
                    <option value="{{ $option }}" @selected(old($oldPrefix.'application_width', $block->setting('width', 'content')) === $option)>{{ $adminText('width.'.$option) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_loading') }}">{{ $adminText('loading_label') }}</label>
            <select id="{{ $fieldId('application_loading') }}" name="{{ $fieldName('application_loading') }}" class="wb-select" required>
                @foreach (['lazy', 'eager'] as $option)
                    <option value="{{ $option }}" @selected(old($oldPrefix.'application_loading', $block->setting('loading', 'lazy')) === $option)>{{ $adminText('loading.'.$option) }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_aspect_ratio') }}">{{ $adminText('aspect_ratio_label') }}</label>
            <select id="{{ $fieldId('application_aspect_ratio') }}" name="{{ $fieldName('application_aspect_ratio') }}" class="wb-select" required>
                @foreach (['auto', '16/9', '4/3', '1/1'] as $option)
                    <option value="{{ $option }}" @selected(old($oldPrefix.'application_aspect_ratio', $block->setting('aspect_ratio', 'auto')) === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_min_height') }}">{{ $adminText('min_height_label') }}</label>
            <input id="{{ $fieldId('application_min_height') }}" name="{{ $fieldName('application_min_height') }}" class="wb-input" type="number" min="0" max="2000" value="{{ old($oldPrefix.'application_min_height', $block->setting('min_height', 0)) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_show_loading_state') }}">{{ $adminText('show_loading_state_label') }}</label>
            <select id="{{ $fieldId('application_show_loading_state') }}" name="{{ $fieldName('application_show_loading_state') }}" class="wb-select">
                <option value="1" @selected($boolValue('application_show_loading_state', (bool) $block->setting('show_loading_state', true)))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('application_show_loading_state', (bool) $block->setting('show_loading_state', true)))>{{ $adminText('option_no') }}</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="{{ $fieldId('application_show_failure_state') }}">{{ $adminText('show_failure_state_label') }}</label>
            <select id="{{ $fieldId('application_show_failure_state') }}" name="{{ $fieldName('application_show_failure_state') }}" class="wb-select">
                <option value="1" @selected($boolValue('application_show_failure_state', (bool) $block->setting('show_failure_state', false)))>{{ $adminText('option_yes') }}</option>
                <option value="0" @selected(! $boolValue('application_show_failure_state', (bool) $block->setting('show_failure_state', false)))>{{ $adminText('option_no') }}</option>
            </select>
        </div>
    </div>
</div>
