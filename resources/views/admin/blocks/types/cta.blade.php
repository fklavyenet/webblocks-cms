@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.cta.'.$key, $adminLocale);
    $ctaButtons = $block->children->filter(fn ($child) => in_array($child->typeSlug(), ['button', 'button_link'], true))->values();
    $primaryButton = $ctaButtons->get(0);
    $secondaryButton = $ctaButtons->get(1);
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>
                <div class="wb-alert-title">{{ $adminText('translation_title') }}</div>
                <div>{{ $adminText('locale_help') }}</div>
            </div>
        </div>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('translated_fields') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="subtitle">{{ $adminText('eyebrow_label') }}</label>
                    <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle) }}">
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="title">{{ $adminText('heading_label') }}</label>
                    <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}">
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content">{{ $adminText('body_label') }}</label>
                <textarea id="content" name="content" class="wb-textarea" rows="5">{{ old('content', $block->content) }}</textarea>
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-3">
                    <div>
                        <strong>{{ $adminText('primary_cta') }}</strong>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('cta_help') }}</div>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="primary_cta_label">{{ $adminText('primary_label') }}</label>
                        <input id="primary_cta_label" name="primary_cta_label" class="wb-input" type="text" value="{{ old('primary_cta_label', $primaryButton?->title) }}">
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="primary_cta_url">{{ $adminText('primary_url') }}</label>
                        <input id="primary_cta_url" name="primary_cta_url" class="wb-input" type="text" value="{{ old('primary_cta_url', $primaryButton?->url) }}" @disabled($isNonDefaultLocale)>
                        <div class="wb-text-sm wb-text-muted">
                            @if ($isNonDefaultLocale)
                                {{ $adminText('shared_url_locked') }}
                            @else
                                {{ $adminText('primary_url_help') }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="wb-stack wb-gap-3">
                    <div>
                        <strong>{{ $adminText('secondary_cta') }}</strong>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('cta_help') }}</div>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="secondary_cta_label">{{ $adminText('secondary_label') }}</label>
                        <input id="secondary_cta_label" name="secondary_cta_label" class="wb-input" type="text" value="{{ old('secondary_cta_label', $secondaryButton?->title) }}">
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label for="secondary_cta_url">{{ $adminText('secondary_url') }}</label>
                        <input id="secondary_cta_url" name="secondary_cta_url" class="wb-input" type="text" value="{{ old('secondary_cta_url', $secondaryButton?->url) }}" @disabled($isNonDefaultLocale)>
                        <div class="wb-text-sm wb-text-muted">
                            @if ($isNonDefaultLocale)
                                {{ $adminText('shared_url_locked') }}
                            @else
                                {{ $adminText('secondary_url_help') }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('shared_fields') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            @if (! $isNonDefaultLocale)
                @include('webblocks-cms::admin.blocks.types.partials.background-media-fields')
            @endif

            <div class="wb-grid wb-grid-2">
            <div class="wb-stack wb-gap-1">
                <label for="variant">{{ $adminText('variant_label') }}</label>
                <select id="variant" name="variant" class="wb-select" @disabled($isNonDefaultLocale)>
                    @foreach ([
                        'default' => $adminText('default'),
                        'muted' => $adminText('muted'),
                        'accent' => $adminText('accent'),
                        'soft' => $adminText('soft'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'default') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            </div>
        </div>
    </div>
</div>
