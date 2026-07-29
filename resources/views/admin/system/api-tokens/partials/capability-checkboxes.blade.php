@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.api_tokens.capabilities.'.$key, $adminLocale, $replace);
    // Falls back to a plugin-provided label when the CMS has no translation key
    // (plugin capability groups ship their own labels via apiCapabilities()).
    $adminTextOr = fn (string $key, string $default, array $replace = []) => $adminTranslator->getOrDefault('admin.api_tokens.capabilities.'.$key, $default, $adminLocale, $replace);
    $capabilityLabels = $capabilityLabels ?? [];
    $fieldPrefix = $fieldPrefix ?? 'api_token_capability';
    $selectedCapabilities = $selectedCapabilities ?? [];
    $showErrors = $showErrors ?? false;
    $capabilityGroups = $capabilityGroups ?? [
        [
            'key' => 'default',
            'label' => $adminText('default_group'),
            'description' => $adminText('default_description'),
            'capabilities' => $defaultCapabilities,
        ],
        [
            'key' => 'advanced',
            'label' => $adminText('advanced_group'),
            'description' => $adminText('advanced_description'),
            'capabilities' => $advancedCapabilities,
        ],
    ];
    $allGroupCapabilities = collect($capabilityGroups)->pluck('capabilities')->flatten()->unique()->values()->all();
    $selectedCount = count(array_intersect($selectedCapabilities, $allGroupCapabilities));
    $selectedTotal = count($allGroupCapabilities);
@endphp

<div class="wb-field wb-api-token-capabilities" data-wb-capability-scope>
    <div class="wb-stack wb-gap-3">
        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-gap-1">
                <div class="wb-label">{{ $adminText('title') }}</div>
                <div class="wb-text-sm wb-text-muted">{{ $adminText('description') }}</div>
            </div>
            <span
                class="wb-status-pill wb-status-info"
                data-wb-capability-total
                data-wb-capability-total-template="{{ $adminText('selected_count', ['selected' => '__SELECTED__', 'total' => '__TOTAL__']) }}"
            >{{ $adminText('selected_count', ['selected' => $selectedCount, 'total' => $selectedTotal]) }}</span>
        </div>

        <div class="wb-api-token-capability-groups">
            @foreach ($capabilityGroups as $group)
                @php
                    $groupCapabilities = $group['capabilities'] ?? [];
                    $groupSelectedCount = count(array_intersect($selectedCapabilities, $groupCapabilities));
                    $groupId = $fieldPrefix.'_group_'.Str::slug($group['key'] ?? $group['label']);
                    $groupLocaleKey = str_replace('-', '_', (string) ($group['key'] ?? 'default'));
                    $groupLabel = $adminTextOr('groups.'.$groupLocaleKey.'.label', (string) ($group['label'] ?? $group['key'] ?? ''));
                    $groupDescription = $adminTextOr('groups.'.$groupLocaleKey.'.description', (string) ($group['description'] ?? ''));
                @endphp

                <details class="wb-api-token-capability-group" data-wb-capability-group>
                    <summary class="wb-api-token-capability-summary" id="{{ $groupId }}">
                        <span class="wb-api-token-capability-summary-copy">
                            <strong>{{ $groupLabel }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $groupDescription }}</span>
                        </span>
                        <span class="wb-status-pill {{ $groupSelectedCount > 0 ? 'wb-status-active' : 'wb-status-info' }}" data-wb-capability-group-count>{{ $groupSelectedCount }}/{{ count($groupCapabilities) }}</span>
                    </summary>

                    <div class="wb-api-token-capability-list" aria-labelledby="{{ $groupId }}">
                        @foreach ($groupCapabilities as $capability)
                            @php($capabilityLocaleKey = str_replace(['.', '-'], '_', $capability))
                            <label class="wb-check wb-api-token-capability-option" for="{{ $fieldPrefix }}_{{ Str::slug($capability) }}">
                                <input
                                    id="{{ $fieldPrefix }}_{{ Str::slug($capability) }}"
                                    name="capabilities[]"
                                    type="checkbox"
                                    value="{{ $capability }}"
                                    @checked(in_array($capability, $selectedCapabilities, true))
                                >
                                <span class="wb-api-token-capability-copy">
                                    <strong>{{ $capability }}</strong>
                                    <span class="wb-text-sm wb-text-muted">{{ $adminTextOr('labels.'.$capabilityLocaleKey, $capabilityLabels[$capability] ?? '') }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        @if ($showErrors)
            @error('capabilities')
                <div class="wb-field-error">{{ $message }}</div>
            @enderror
            @error('capabilities.*')
                <div class="wb-field-error">{{ $message }}</div>
            @enderror
        @endif
    </div>
</div>
