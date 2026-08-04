@php
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $iconFieldLocale = app(AdminLocaleResolver::class)->locale();
  $iconFieldTranslator = app(CmsTranslator::class);
  $iconFieldText = static fn (string $key) => $iconFieldTranslator->admin('icon_picker.'.$key, $iconFieldLocale);

  // Names are passed in because the same field appears both as a block field
  // (icon_slug) and inside repeated item rows (column_items[3][icon_slug]).
  $iconFieldSlugName = $slugName;
  $iconFieldToneName = $toneName;
  $iconFieldBadgeToneName = $badgeToneName ?? null;
  $iconFieldSlug = (string) ($slug ?? '');
  $iconFieldTone = (string) ($tone ?? 'default');
  $iconFieldBadgeTone = (string) ($badgeTone ?? 'neutral');
  $iconFieldLabel = $label ?? $iconFieldText('icon');
@endphp

<div class="wb-stack wb-gap-1" data-wb-icon-field>
  <span class="wb-label">{{ $iconFieldLabel }}</span>

  <input type="hidden" name="{{ $iconFieldSlugName }}" value="{{ $iconFieldSlug }}" data-wb-icon-field-slug>
  <input type="hidden" name="{{ $iconFieldToneName }}" value="{{ $iconFieldTone }}" data-wb-icon-field-tone>
  @if ($iconFieldBadgeToneName)
    <input type="hidden" name="{{ $iconFieldBadgeToneName }}" value="{{ $iconFieldBadgeTone }}" data-wb-icon-field-badge-tone>
  @endif

  <button type="button" class="wb-btn wb-btn-secondary wb-picker-icon-trigger" data-wb-icon-picker-open
          data-choose-label="{{ $iconFieldText('choose_icon') }}"
          data-change-label="{{ $iconFieldText('change_icon') }}">
    <i data-wb-icon-field-preview aria-hidden="true" hidden></i>
    <span data-wb-icon-field-label>{{ $iconFieldSlug === '' ? $iconFieldText('choose_icon') : $iconFieldText('change_icon') }}</span>
  </button>
</div>

@once
  @include('webblocks-cms::admin.blocks.partials.icon-picker-modal')
@endonce
