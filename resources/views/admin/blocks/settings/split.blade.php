@php
  $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.split_settings.'.$key, $adminLocale);
@endphp

<div class="wb-stack wb-gap-3">
  <div class="wb-stack wb-gap-1">
    <label for="split_width">{{ $adminText('width_label') }}</label>
    <select id="split_width" name="split_width" class="wb-select">
      <option value="" @selected(old('split_width', $block->appearanceSetting('width')) !== 'full')>{{ $adminText('auto') }}</option>
      <option value="full" @selected(old('split_width', $block->appearanceSetting('width')) === 'full')>{{ $adminText('full') }}</option>
    </select>
    <div class="wb-text-sm wb-text-muted">{{ $adminText('width_help') }}</div>
  </div>

  <div class="wb-stack wb-gap-1">
    <label for="split_align">{{ $adminText('align_label') }}</label>
    <select id="split_align" name="split_align" class="wb-select">
      <option value="" @selected(old('split_align', $block->appearanceSetting('items_alignment')) === null)>{{ $adminText('center') }}</option>
      <option value="start" @selected(old('split_align', $block->appearanceSetting('items_alignment')) === 'start')>{{ $adminText('start') }}</option>
      <option value="end" @selected(old('split_align', $block->appearanceSetting('items_alignment')) === 'end')>{{ $adminText('end') }}</option>
      <option value="stretch" @selected(old('split_align', $block->appearanceSetting('items_alignment')) === 'stretch')>{{ $adminText('stretch') }}</option>
    </select>
    <div class="wb-text-sm wb-text-muted">{{ $adminText('align_help') }}</div>
  </div>

  <div class="wb-stack wb-gap-1">
    <label for="split_gap">{{ $adminText('gap_label') }}</label>
    <select id="split_gap" name="split_gap" class="wb-select">
      <option value="" @selected(old('split_gap', $block->appearanceSetting('gap')) === null)>{{ $adminText('default') }}</option>
      @foreach (['0', '1', '2', '3', '4', '6', '8'] as $gap)
        <option value="{{ $gap }}" @selected(old('split_gap', $block->appearanceSetting('gap')) === $gap)>{{ $gap }}</option>
      @endforeach
    </select>
    <div class="wb-text-sm wb-text-muted">{{ $adminText('gap_help') }}</div>
  </div>
</div>
