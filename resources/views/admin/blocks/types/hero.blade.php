@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.blocks.hero.'.$key, $adminLocale, $replace);
    $settings = is_array($block->settings) ? $block->settings : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);
    $isNonDefaultLocale = isset($activeLocale) && ! $isDefaultLocale;
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>
                <div class="wb-alert-title">{{ $adminText('translation_title') }}</div>
                <div>{{ $adminText('translation_help') }}</div>
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
                    <label for="title">{{ $adminText('title_label') }}</label>
                    <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="content">{{ $adminText('intro_label') }}</label>
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
                        'default' => $adminText('variant_default'),
                        'muted' => $adminText('variant_muted'),
                        'accent' => $adminText('variant_accent'),
                        'soft' => $adminText('variant_soft'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('variant', $block->variant ?: 'default') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="layout">{{ $adminText('layout_label') }}</label>
                <select id="layout" name="layout" class="wb-select" @disabled($isNonDefaultLocale)>
                    @foreach ([
                        'left' => $adminText('layout_left'),
                        'centered' => $adminText('layout_centered'),
                        'split' => $adminText('layout_split'),
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('layout', $settings['layout'] ?? ($block->variant === 'centered' ? 'centered' : 'left')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="wb-text-sm wb-text-muted">{{ $adminText('presentation_help') }}</div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="title_tag">{{ $adminText('title_tag_label') }}</label>
                <select id="title_tag" name="title_tag" class="wb-select" @disabled($isNonDefaultLocale)>
                    @foreach (['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('title_tag', $settings['title_tag'] ?? 'h1') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="wb-text-sm wb-text-muted">{!! $adminText('title_tag_help') !!}</div>
            </div>
          </div>
        </div>
    </div>
</div>
