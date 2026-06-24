@php
    $fieldPrefix = $fieldPrefix ?? 'api_token_capability';
    $selectedCapabilities = $selectedCapabilities ?? [];
    $showErrors = $showErrors ?? false;
@endphp

<div class="wb-field">
    <div class="wb-stack wb-gap-3">
        <div class="wb-stack wb-gap-1">
            <div class="wb-label">Capabilities</div>
            <div class="wb-text-sm wb-text-muted">Choose what this token is allowed to do.</div>
        </div>

        <div class="wb-stack wb-gap-2">
            @foreach ($defaultCapabilities as $capability)
                <label class="wb-check" for="{{ $fieldPrefix }}_{{ Str::slug($capability) }}">
                    <input
                        id="{{ $fieldPrefix }}_{{ Str::slug($capability) }}"
                        name="capabilities[]"
                        type="checkbox"
                        value="{{ $capability }}"
                        @checked(in_array($capability, $selectedCapabilities, true))
                    >
                    <span>{{ $capability }} <span class="wb-text-muted">- {{ $capabilityLabels[$capability] ?? $capability }}</span></span>
                </label>
            @endforeach
        </div>

        <div class="wb-stack wb-gap-2">
            <div class="wb-stack wb-gap-1">
                <strong>Advanced capabilities</strong>
                <div class="wb-text-sm wb-text-muted">Grant only to trusted operator tools.</div>
            </div>

            @foreach ($advancedCapabilities as $capability)
                <label class="wb-check" for="{{ $fieldPrefix }}_{{ Str::slug($capability) }}">
                    <input
                        id="{{ $fieldPrefix }}_{{ Str::slug($capability) }}"
                        name="capabilities[]"
                        type="checkbox"
                        value="{{ $capability }}"
                        @checked(in_array($capability, $selectedCapabilities, true))
                    >
                    <span>{{ $capability }} <span class="wb-text-muted">- {{ $capabilityLabels[$capability] ?? $capability }}</span></span>
                </label>
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
