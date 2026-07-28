@php
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $publicToken = '{{ site.'.($siteVariable->key ?: 'variable_key').' }}';
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-wb-admin-close-url="{{ $closeUrl }}" data-wb-admin-autoload-overlay hidden>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $modalTitle }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $modalDescription }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('close_site_variable_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="{{ $adminText('discard_site_variable_changes') }}">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <input type="hidden" name="_site_variable_modal" value="{{ $modalKey }}">
                <input type="hidden" name="_site_variable_id" value="{{ $siteVariable->id ?? '' }}">
                <input type="hidden" name="_site_variable_close_url" value="{{ $closeUrl }}">
                <input type="hidden" name="_site_tab" value="variables">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('validation_error') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div>{{ $adminText('public_token') }} <code>{{ $publicToken }}</code></div>
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('site_variable_key_help') }}</div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_label_{{ $siteVariable->id ?? 'new' }}">{{ $adminText('label') }}</label>
                            <input id="site_variable_label_{{ $siteVariable->id ?? 'new' }}" name="label" class="wb-input" type="text" value="{{ $siteVariable->label }}">
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('optional_admin_name') }}</span>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_key_{{ $siteVariable->id ?? 'new' }}">{{ $adminText('key') }}</label>
                            <input id="site_variable_key_{{ $siteVariable->id ?? 'new' }}" name="key" class="wb-input" type="text" value="{{ $siteVariable->key }}" required>
                        </div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_variable_value_{{ $siteVariable->id ?? 'new' }}">{{ $adminText('value') }}</label>
                        <textarea id="site_variable_value_{{ $siteVariable->id ?? 'new' }}" name="value" class="wb-input" rows="6">{{ $siteVariable->value }}</textarea>
                        <span class="wb-text-sm wb-text-muted">{{ $adminText('site_variable_value_help') }}</span>
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_sort_{{ $siteVariable->id ?? 'new' }}">{{ $adminText('sort_order') }}</label>
                            <input id="site_variable_sort_{{ $siteVariable->id ?? 'new' }}" name="sort_order" class="wb-input" type="number" min="0" value="{{ $siteVariable->sort_order }}">
                        </div>

                        <div class="wb-stack wb-gap-2 wb-justify-center">
                            <label class="wb-check" for="site_variable_enabled_{{ $siteVariable->id ?? 'new' }}">
                                <input type="hidden" name="is_enabled" value="0">
                                <input id="site_variable_enabled_{{ $siteVariable->id ?? 'new' }}" type="checkbox" name="is_enabled" value="1" @checked($siteVariable->is_enabled)>
                                <span>{{ $adminText('enabled') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$submitLabel"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
</div>
