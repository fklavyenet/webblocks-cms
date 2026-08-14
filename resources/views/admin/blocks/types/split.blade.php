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

  <div class="wb-text-sm wb-text-muted">{{ $adminText('split_help') }}</div>
</div>
