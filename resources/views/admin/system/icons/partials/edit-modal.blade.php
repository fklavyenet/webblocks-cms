@php
    $adminLocale = $adminLocale ?? app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $adminTranslator = $adminTranslator ?? app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = $adminText ?? static fn (string $key, array $replace = []) => $adminTranslator->admin('icons.'.$key, $adminLocale, $replace);
    $modalId = 'iconEditModal-'.$icon->id;
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isOpen = old('_icon_modal', request('modal')) === 'edit-icon' && (int) old('_icon_id', request('icon')) === $icon->id;
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $adminText('edit_title', ['label' => $icon->label]) }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $adminText('edit_description') }}</span>
                </div>
                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_edit_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.system.icons.update', $icon) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                <input type="hidden" name="_icon_modal" value="edit-icon">
                <input type="hidden" name="_icon_id" value="{{ $icon->id }}">
                <input type="hidden" name="_icon_index_url" value="{{ $closeUrl }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any() && $isOpen)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('validation_error') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-cluster wb-cluster-2 wb-items-center">
                            <i class="wb-icon {{ $icon->css_class }}" aria-hidden="true"></i>
                            <div class="wb-stack wb-gap-1">
                                <strong><code>{{ $icon->slug }}</code></strong>
                                <span class="wb-text-sm wb-text-muted">{{ $icon->css_class }} | {{ $icon->source }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="icon_label_{{ $icon->id }}">{{ $adminText('label') }}</label>
                            <input id="icon_label_{{ $icon->id }}" name="label" class="wb-input" type="text" value="{{ old('label', $icon->label) }}" required>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="icon_sort_order_{{ $icon->id }}">{{ $adminText('sort_order') }}</label>
                            <input id="icon_sort_order_{{ $icon->id }}" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $icon->sort_order) }}" required>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-1">
                            <label for="icon_contexts_{{ $icon->id }}">{{ $adminText('contexts') }}</label>
                            <input id="icon_contexts_{{ $icon->id }}" name="contexts" class="wb-input" type="text" value="{{ old('contexts', implode(', ', $icon->contexts ?? [])) }}" placeholder="{{ $adminText('contexts_placeholder') }}">
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <label for="icon_categories_{{ $icon->id }}">{{ $adminText('categories') }}</label>
                            <input id="icon_categories_{{ $icon->id }}" name="categories" class="wb-input" type="text" value="{{ old('categories', implode(', ', $icon->categories ?? [])) }}" placeholder="{{ $adminText('categories_placeholder') }}">
                        </div>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="icon_keywords_{{ $icon->id }}">{{ $adminText('keywords') }}</label>
                        <input id="icon_keywords_{{ $icon->id }}" name="keywords" class="wb-input" type="text" value="{{ old('keywords', implode(', ', $icon->keywords ?? [])) }}" placeholder="{{ $adminText('keywords_placeholder') }}">
                    </div>

                    <label class="wb-checkbox" for="icon_is_active_{{ $icon->id }}">
                        <input id="icon_is_active_{{ $icon->id }}" name="is_active" type="checkbox" value="1" @checked(old('is_active', $icon->is_active))>
                        <span>{{ $adminText('active') }}</span>
                    </label>
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$adminText('save_changes')"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
