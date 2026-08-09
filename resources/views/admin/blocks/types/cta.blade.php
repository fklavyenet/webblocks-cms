@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.cta.'.$key, $adminLocale);
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

        </div>
    </div>

    <div class="wb-alert wb-alert-info">
        <div>{{ $adminText('buttons_help') }}</div>
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
