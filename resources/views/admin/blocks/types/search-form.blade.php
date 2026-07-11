@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.search_form.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>{{ $adminText('locale_help') }}</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">{{ $adminText('label') }}</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title ?: 'Search') }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="content">{{ $adminText('placeholder_label') }}</label>
            <input id="content" name="content" class="wb-input" type="text" value="{{ old('content', $block->content ?: 'Search this site') }}" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="subtitle">{{ $adminText('button_label') }}</label>
            <input id="subtitle" name="subtitle" class="wb-input" type="text" value="{{ old('subtitle', $block->subtitle ?: 'Search') }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="variant">{{ $adminText('variant_label') }}</label>
            <select id="variant" name="variant" class="wb-select">
                @foreach ([
                    'primary' => $adminText('primary'),
                    'secondary' => $adminText('secondary'),
                ] as $variant => $label)
                    <option value="{{ $variant }}" @selected(old('variant', $block->variant ?: 'primary') === $variant)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <input type="hidden" name="show_button" value="0">
        <label class="wb-cluster wb-cluster-2" for="search_form_show_button">
            <input id="search_form_show_button" name="show_button" type="checkbox" value="1" @checked(old('show_button', $block->setting('show_button', true)))>
            <span>{{ $adminText('show_button') }}</span>
        </label>
    </div>
</div>
