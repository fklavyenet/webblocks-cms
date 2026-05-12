@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="sticky_navbar_mode">Sticky Mode</label>
            <select id="sticky_navbar_mode" name="sticky_navbar_mode" class="wb-select">
                @foreach (['sticky' => 'Sticky', 'fixed' => 'Fixed', 'static' => 'Static'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sticky_navbar_mode', $settings['sticky_mode'] ?? 'sticky') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sticky_navbar_variant">Visual Variant</label>
            <select id="sticky_navbar_variant" name="sticky_navbar_variant" class="wb-select">
                @foreach (['light' => 'Light', 'transparent' => 'Transparent', 'dark' => 'Dark'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sticky_navbar_variant', $settings['visual_variant'] ?? 'light') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <input type="hidden" name="sticky_navbar_compact" value="0">
            <label class="wb-inline-flex wb-items-center wb-gap-2" for="sticky_navbar_compact">
                <input id="sticky_navbar_compact" name="sticky_navbar_compact" type="checkbox" value="1" @checked(old('sticky_navbar_compact', array_key_exists('compact', $settings) ? (bool) $settings['compact'] : true))>
                <span>Compact height</span>
            </label>
            <div class="wb-text-sm wb-text-muted">Keeps the navbar close to the compact fklavye-style rhythm.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sticky_navbar_container_width">Container Width</label>
            <select id="sticky_navbar_container_width" name="sticky_navbar_container_width" class="wb-select">
                <option value="" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === '')>Default</option>
                <option value="sm" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === 'sm')>Small</option>
                <option value="md" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === 'md')>Medium</option>
                <option value="lg" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === 'lg')>Large</option>
                <option value="xl" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === 'xl')>Extra Large</option>
                <option value="full" @selected(old('sticky_navbar_container_width', $settings['width'] ?? '') === 'full')>Full</option>
            </select>
            <div class="wb-text-sm wb-text-muted">Uses the shipped WebBlocks container width classes only.</div>
        </div>
    </div>
</div>
