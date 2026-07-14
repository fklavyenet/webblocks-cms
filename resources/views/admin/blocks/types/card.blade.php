@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.layout_shell.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
    <div class="wb-stack wb-gap-1">
        <label for="name">{{ $adminText('name') }}</label>
        <input id="name" name="name" class="wb-input" type="text" maxlength="100" value="{{ old('name', $block->layoutAdminName()) }}">
        <div class="wb-text-sm wb-text-muted">{{ $adminText('admin_name_help') }}</div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="variant">{{ $adminText('variant_label') }}</label>
        <select id="variant" name="variant" class="wb-select">
            @foreach ([
                '' => $adminText('variant_default'),
                'flat' => $adminText('variant_flat'),
                'muted' => $adminText('variant_muted'),
                'highlight' => $adminText('variant_highlight'),
                'accent' => $adminText('variant_accent'),
            ] as $value => $label)
                <option value="{{ $value }}" @selected(old('variant', (string) ($block->variant ?? '')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('variant_help') }}</div>
    </div>

    @include('webblocks-cms::admin.blocks.types.partials.background-media-fields')

    <div class="wb-text-sm wb-text-muted">{{ $adminText('card_help') }}</div>
</div>
