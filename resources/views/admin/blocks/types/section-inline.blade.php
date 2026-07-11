@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.section_inline.'.$key, $adminLocale);
@endphp

<div class="wb-grid wb-grid-2">
    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_title">{{ $adminText('title_label') }}</label>
        <input id="block_{{ $index }}_title" name="{{ $prefix }}[title]" class="wb-input" type="text" value="{{ old("{$prefix}.title", $block->title) }}">
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_variant">{{ $adminText('variant_label') }}</label>
        <select id="block_{{ $index }}_variant" name="{{ $prefix }}[variant]" class="wb-select">
            @foreach ([
                'default' => $adminText('variant_default'),
                'muted' => $adminText('variant_muted'),
                'accent' => $adminText('variant_accent'),
                'wide' => $adminText('variant_wide'),
            ] as $variant => $label)
                <option value="{{ $variant }}" @selected(old("{$prefix}.variant", $block->variant ?: 'default') === $variant)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="wb-stack wb-gap-1">
    <label for="block_{{ $index }}_content">{{ $adminText('intro_label') }}</label>
    <textarea id="block_{{ $index }}_content" name="{{ $prefix }}[content]" class="wb-textarea" rows="6">{{ old("{$prefix}.content", $block->content) }}</textarea>
</div>
