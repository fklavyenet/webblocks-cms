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

                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="Close site variable modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="Discard site variable changes?">
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
                                <div class="wb-alert-title">Validation Error</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div>Public token: <code>{{ $publicToken }}</code></div>
                            <div class="wb-text-sm wb-text-muted">Keys are normalized to lowercase snake_case. Replacement happens only in public rendering and search indexing.</div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_label_{{ $siteVariable->id ?? 'new' }}">Label</label>
                            <input id="site_variable_label_{{ $siteVariable->id ?? 'new' }}" name="label" class="wb-input" type="text" value="{{ $siteVariable->label }}">
                            <span class="wb-text-sm wb-text-muted">Optional admin-facing name.</span>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_key_{{ $siteVariable->id ?? 'new' }}">Key</label>
                            <input id="site_variable_key_{{ $siteVariable->id ?? 'new' }}" name="key" class="wb-input" type="text" value="{{ $siteVariable->key }}" required>
                        </div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="site_variable_value_{{ $siteVariable->id ?? 'new' }}">Value</label>
                        <textarea id="site_variable_value_{{ $siteVariable->id ?? 'new' }}" name="value" class="wb-input" rows="6">{{ $siteVariable->value }}</textarea>
                        <span class="wb-text-sm wb-text-muted">Stored as plain text. HTML is not executed by site-variable replacement.</span>
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="site_variable_sort_{{ $siteVariable->id ?? 'new' }}">Sort Order</label>
                            <input id="site_variable_sort_{{ $siteVariable->id ?? 'new' }}" name="sort_order" class="wb-input" type="number" min="0" value="{{ $siteVariable->sort_order }}">
                        </div>

                        <div class="wb-stack wb-gap-2 wb-justify-center">
                            <label class="wb-checkbox" for="site_variable_enabled_{{ $siteVariable->id ?? 'new' }}">
                                <input type="hidden" name="is_enabled" value="0">
                                <input id="site_variable_enabled_{{ $siteVariable->id ?? 'new' }}" type="checkbox" name="is_enabled" value="1" @checked($siteVariable->is_enabled)>
                                <span>Enabled</span>
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
