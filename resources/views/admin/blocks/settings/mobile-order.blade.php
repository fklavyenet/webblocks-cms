@php
  $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
  $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
  $mobileOrderText = fn (string $key) => $translator->admin('blocks.mobile_order_settings.'.$key, $locale);
@endphp

<div class="wb-stack wb-gap-1">
  <label for="mobile_order">{{ $mobileOrderText('label') }}</label>
  <select id="mobile_order" name="mobile_order" class="wb-select" aria-describedby="mobile-order-help" @disabled($isNonDefaultLocale ?? false)>
    @foreach (\WebBlocks\Cms\Support\Blocks\MobileContentOrder::OPTIONS as $order)
      <option value="{{ $order }}" @selected(old('mobile_order', $block->appearanceSetting('mobile_order') ?? 'default') === $order)>{{ $mobileOrderText($order) }}</option>
    @endforeach
  </select>
  <div class="wb-text-sm wb-text-muted" id="mobile-order-help">{{ $mobileOrderText('help') }}</div>
</div>
