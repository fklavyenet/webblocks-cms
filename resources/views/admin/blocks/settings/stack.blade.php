@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.stack_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-1">
  <label for="stack_spacing">{{ $adminText('spacing_label') }}</label>
  <select id="stack_spacing" name="stack_spacing" class="wb-select">
    <option value="" @selected(old('stack_spacing', $block->appearanceSetting('spacing')) === null)>{{ $adminText('default') }}</option>
    @foreach (['1', '2', '3', '4', '6', '8'] as $spacing)
      <option value="{{ $spacing }}" @selected(old('stack_spacing', $block->appearanceSetting('spacing')) === $spacing)>{{ $spacing }}</option>
    @endforeach
  </select>
  <div class="wb-text-sm wb-text-muted">{{ $adminText('spacing_help') }}</div>
</div>
