@php
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $heroButtons = $block->children->filter(fn ($child) => $child->typeSlug() === 'button')->values();
    $primaryButton = $heroButtons->get(0);
    $secondaryButton = $heroButtons->get(1);
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>
                <div class="wb-alert-title">Hero Translation Ownership</div>
                <div>Eyebrow, title, intro, and CTA labels are translated per locale. CTA URLs, variant, and layout stay shared across locales.</div>
            </div>
        </div>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Translated Fields</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="subtitle">Eyebrow / Label</label>
                    <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="title">Title</label>
                    <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content">Subtitle / Intro</label>
                <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-3">
                    <div>
                        <strong>Primary CTA</strong>
                        <div class="wb-text-sm wb-text-muted">Label is translated. URL stays shared.</div>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="primary_cta_label">Primary CTA Label</label>
                        <input id="primary_cta_label" name="primary_cta_label" class="wb-input" type="text" value="{{ old('primary_cta_label', $primaryButton?->title) }}">
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="primary_cta_url">Primary CTA URL</label>
                        <input id="primary_cta_url" name="primary_cta_url" class="wb-input" type="text" value="{{ old('primary_cta_url', $primaryButton?->url) }}" @disabled($isNonDefaultLocale)>
                        <div class="wb-text-sm wb-text-muted">
                            @if ($isNonDefaultLocale)
                                Shared CTA URLs can only be changed in the default locale.
                            @else
                                Leave blank to omit the primary CTA.
                            @endif
                        </div>
                    </div>
                </div>

                <div class="wb-stack wb-gap-3">
                    <div>
                        <strong>Secondary CTA</strong>
                        <div class="wb-text-sm wb-text-muted">Label is translated. URL stays shared.</div>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="secondary_cta_label">Secondary CTA Label</label>
                        <input id="secondary_cta_label" name="secondary_cta_label" class="wb-input" type="text" value="{{ old('secondary_cta_label', $secondaryButton?->title) }}">
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="secondary_cta_url">Secondary CTA URL</label>
                        <input id="secondary_cta_url" name="secondary_cta_url" class="wb-input" type="text" value="{{ old('secondary_cta_url', $secondaryButton?->url) }}" @disabled($isNonDefaultLocale)>
                        <div class="wb-text-sm wb-text-muted">
                            @if ($isNonDefaultLocale)
                                Shared CTA URLs can only be changed in the default locale.
                            @else
                                Leave blank to omit the secondary CTA.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Shared Fields</strong></div>
        <div class="wb-card-body wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label for="variant">Variant</label>
                <select id="variant" name="variant" class="wb-select" @disabled($isNonDefaultLocale)>
                    @foreach ([
                        'default' => 'Default',
                        'muted' => 'Muted',
                        'accent' => 'Accent',
                        'soft' => 'Soft',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'default') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="layout">Layout</label>
                <select id="layout" name="layout" class="wb-select" @disabled($isNonDefaultLocale)>
                    @foreach ([
                        'left' => 'Left',
                        'centered' => 'Centered',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('layout', $settings['layout'] ?? ($block->variant === 'centered' ? 'centered' : 'left')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="wb-text-sm wb-text-muted">Shared presentation controls stay stable across locales and sites.</div>
            </div>
        </div>
    </div>
</div>
