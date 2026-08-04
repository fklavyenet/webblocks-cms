@php
  use WebBlocks\Cms\Support\Icons\IconCatalog;
  use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
  use WebBlocks\Cms\Support\Translations\CmsTranslator;

  $iconPickerLocale = app(AdminLocaleResolver::class)->locale();
  $iconPickerTranslator = app(CmsTranslator::class);
  $iconPickerText = static fn (string $key) => $iconPickerTranslator->admin('icon_picker.'.$key, $iconPickerLocale);
  $iconPickerGroups = app(IconCatalog::class)->groupedPickerOptions('content');
  $iconPickerToneOptions = ['default', 'soft', 'brand', 'accent', 'highlight', 'bold', 'quiet'];
  $iconPickerBadgeToneOptions = ['neutral', 'info', 'success', 'warning', 'danger'];
  $iconPickerIsEmpty = $iconPickerGroups['suggested']->isEmpty() && $iconPickerGroups['all']->isEmpty();
@endphp

{{-- One modal serves every icon field on the page, including item rows added
     after load: a trigger carries its own state and the modal writes back to
     whichever one opened it. --}}
@push('overlays')
  <div class="wb-modal wb-modal-lg" id="wb_icon_picker_modal" role="dialog" aria-modal="true"
       aria-labelledby="wb_icon_picker_title" data-wb-icon-picker-modal hidden>
    <div class="wb-modal-dialog">
      <div class="wb-modal-header">
        <h2 class="wb-modal-title" id="wb_icon_picker_title">{{ $iconPickerText('title') }}</h2>

        <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $iconPickerText('close') }}">
          <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
        </button>
      </div>

      <div class="wb-modal-body wb-stack wb-gap-3 wb-picker-icon-body">
        <div class="wb-card wb-card-muted">
          <div class="wb-card-body wb-stack wb-gap-2">
            <span class="wb-text-sm wb-text-muted">{{ $iconPickerText('preview') }}</span>

            <div class="wb-cluster wb-cluster-2 wb-picker-icon-preview" data-wb-icon-picker-preview>
              <i data-wb-icon-picker-preview-icon aria-hidden="true" hidden></i>
              <span data-wb-icon-picker-preview-badge hidden></span>
              <span class="wb-text-muted" data-wb-icon-picker-preview-empty>{{ $iconPickerText('nothing_selected') }}</span>
            </div>
          </div>
        </div>

        <div class="wb-grid wb-grid-2">
          <div class="wb-stack wb-gap-1">
            <label for="wb_icon_picker_tone">{{ $iconPickerText('icon_tone') }}</label>
            <select id="wb_icon_picker_tone" class="wb-select" data-wb-icon-picker-tone>
              @foreach ($iconPickerToneOptions as $iconPickerTone)
                <option value="{{ $iconPickerTone }}">{{ $iconPickerText('tone_'.$iconPickerTone) }}</option>
              @endforeach
            </select>
          </div>

          <div class="wb-stack wb-gap-1">
            <label for="wb_icon_picker_badge_tone">{{ $iconPickerText('badge_tone') }}</label>
            <select id="wb_icon_picker_badge_tone" class="wb-select" data-wb-icon-picker-badge-tone>
              @foreach ($iconPickerBadgeToneOptions as $iconPickerBadgeTone)
                <option value="{{ $iconPickerBadgeTone }}">{{ $iconPickerText('badge_tone_'.$iconPickerBadgeTone) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        @if ($iconPickerIsEmpty)
          <div class="wb-empty">
            <div class="wb-empty-title">{{ $iconPickerText('empty_title') }}</div>
            <div class="wb-empty-text">{{ $iconPickerText('empty_text') }}</div>
          </div>
        @else
          <div class="wb-stack wb-gap-1">
            <label for="wb_icon_picker_search">{{ $iconPickerText('search') }}</label>
            <input type="search" id="wb_icon_picker_search" class="wb-input" autocomplete="off"
                   placeholder="{{ $iconPickerText('search_placeholder') }}" data-wb-icon-picker-search>
          </div>

          @foreach (['suggested', 'all'] as $iconPickerGroupKey)
            @if ($iconPickerGroups[$iconPickerGroupKey]->isNotEmpty())
              <div class="wb-stack wb-gap-2" data-wb-icon-picker-group>
                <span class="wb-text-sm wb-text-muted">{{ $iconPickerText($iconPickerGroupKey === 'suggested' ? 'suggested_icons' : 'all_icons') }}</span>

                <div class="wb-picker-icon-grid">
                  @foreach ($iconPickerGroups[$iconPickerGroupKey] as $iconPickerOption)
                    <button type="button" class="wb-picker-icon-option" data-wb-icon-picker-option
                            data-slug="{{ $iconPickerOption['slug'] }}"
                            data-label="{{ $iconPickerOption['label'] }}"
                            data-search="{{ strtolower($iconPickerOption['label'].' '.str_replace('-', ' ', $iconPickerOption['slug'])) }}"
                            aria-pressed="false">
                      <i class="wb-icon wb-icon-{{ $iconPickerOption['slug'] }}" aria-hidden="true"></i>
                      <span class="wb-picker-icon-option__label">{{ $iconPickerOption['label'] }}</span>
                    </button>
                  @endforeach
                </div>
              </div>
            @endif
          @endforeach

          <p class="wb-text-sm wb-text-muted" data-wb-icon-picker-no-results hidden>{{ $iconPickerText('no_results') }}</p>
        @endif
      </div>

      <div class="wb-modal-footer wb-flex wb-justify-between wb-gap-2">
        <button type="button" class="wb-btn wb-btn-secondary" data-wb-icon-picker-clear>{{ $iconPickerText('clear_icon') }}</button>

        <div class="wb-cluster wb-cluster-2">
          <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $iconPickerText('cancel') }}</button>
          <button type="button" class="wb-btn wb-btn-primary" data-wb-icon-picker-apply>{{ $iconPickerText('apply') }}</button>
        </div>
      </div>
    </div>
  </div>
@endpush

@push('admin-scripts')
  @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/icon-picker.js'])
@endpush
