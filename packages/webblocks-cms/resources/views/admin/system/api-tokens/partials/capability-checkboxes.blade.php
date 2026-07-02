@php
    $fieldPrefix = $fieldPrefix ?? 'api_token_capability';
    $selectedCapabilities = $selectedCapabilities ?? [];
    $showErrors = $showErrors ?? false;
    $capabilityGroups = $capabilityGroups ?? [
        [
            'key' => 'default',
            'label' => 'Capabilities',
            'description' => 'Choose what this token is allowed to do.',
            'capabilities' => $defaultCapabilities,
        ],
        [
            'key' => 'advanced',
            'label' => 'Advanced capabilities',
            'description' => 'Grant only to trusted operator tools.',
            'capabilities' => $advancedCapabilities,
        ],
    ];
    $selectedCount = count(array_intersect($selectedCapabilities, collect($capabilityGroups)->pluck('capabilities')->flatten()->all()));
    $selectedTotal = max($selectedCount, count(array_unique($selectedCapabilities)));
@endphp

<div class="wb-field wb-api-token-capabilities">
    <div class="wb-stack wb-gap-3">
        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-gap-1">
                <div class="wb-label">Capabilities</div>
                <div class="wb-text-sm wb-text-muted">Choose grouped permissions for this token.</div>
            </div>
            <span class="wb-status-pill wb-status-info">{{ $selectedCount }}/{{ $selectedTotal }} selected</span>
        </div>

        <div class="wb-api-token-capability-groups">
            @foreach ($capabilityGroups as $group)
                @php
                    $groupCapabilities = $group['capabilities'] ?? [];
                    $groupSelectedCount = count(array_intersect($selectedCapabilities, $groupCapabilities));
                    $groupId = $fieldPrefix.'_group_'.Str::slug($group['key'] ?? $group['label']);
                @endphp

                <details class="wb-api-token-capability-group" @if (($group['key'] ?? null) === 'page-building') open @endif>
                    <summary class="wb-api-token-capability-summary" id="{{ $groupId }}">
                        <span class="wb-api-token-capability-summary-copy">
                            <strong>{{ $group['label'] }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $group['description'] }}</span>
                        </span>
                        <span class="wb-status-pill {{ $groupSelectedCount > 0 ? 'wb-status-active' : 'wb-status-info' }}">{{ $groupSelectedCount }}/{{ count($groupCapabilities) }}</span>
                    </summary>

                    <div class="wb-api-token-capability-list" aria-labelledby="{{ $groupId }}">
                        @foreach ($groupCapabilities as $capability)
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
                                    <span class="wb-text-sm wb-text-muted">{{ $capabilityLabels[$capability] ?? $capability }}</span>
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
