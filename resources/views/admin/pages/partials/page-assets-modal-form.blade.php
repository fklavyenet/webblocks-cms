@php
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isJs = $asset->type === 'js';
    $extension = $isJs ? 'js' : 'css';
    $pageAssetsLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pageAssetsText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_assets.'.$key, $pageAssetsLocale, $replace);
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-wb-admin-close-url="{{ $closeUrl }}" data-wb-admin-autoload-overlay hidden>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $modalTitle }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $modalDescription }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $pageAssetsText('close_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="{{ $pageAssetsText('discard_changes') }}">
                @csrf
                @if ($formMethod !== 'POST')
                    @method($formMethod)
                @endif

                <input type="hidden" name="_page_asset_modal" value="{{ $modalKey }}">
                <input type="hidden" name="_page_asset_id" value="{{ $asset->id ?? '' }}">
                <input type="hidden" name="_page_asset_type" value="{{ $asset->type }}">
                <input type="hidden" name="_page_asset_close_url" value="{{ $closeUrl }}">
                <input type="hidden" name="_page_settings_tab" value="page-assets">
                <input type="hidden" name="return_url" value="{{ request('return_url') }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $pageAssetsText('validation_error') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div class="wb-cluster wb-cluster-2">
                                <span class="wb-status-pill {{ $isJs ? 'wb-status-pending' : 'wb-status-info' }}">{{ strtoupper($asset->type) }}</span>
                                <span class="wb-text-sm wb-text-muted">{{ $pageAssetsText('type_fixed') }}</span>
                            </div>
                            <div>{{ $pageAssetsText('suggested_base') }} <code>{{ $suggestedBase }}</code></div>
                        </div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="page_asset_path_{{ $asset->id ?? $asset->type }}">{{ $pageAssetsText('path') }}</label>
                        <input id="page_asset_path_{{ $asset->id ?? $asset->type }}" name="path" class="wb-input" type="text" value="{{ $asset->path }}" placeholder="/site/{website}/{page}/file.{{ $extension }}" required>
                        <span class="wb-text-sm wb-text-muted">{!! $pageAssetsText('path_help') !!}</span>
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="page_asset_sort_{{ $asset->id ?? $asset->type }}">{{ $pageAssetsText('sort_order') }}</label>
                            <input id="page_asset_sort_{{ $asset->id ?? $asset->type }}" name="sort_order" class="wb-input" type="number" min="0" value="{{ $asset->sort_order }}">
                        </div>

                        <div class="wb-stack wb-gap-2 wb-justify-center">
                            <label class="wb-check" for="page_asset_enabled_{{ $asset->id ?? $asset->type }}">
                                <input id="page_asset_enabled_{{ $asset->id ?? $asset->type }}" type="hidden" name="is_enabled" value="0">
                                <input id="page_asset_enabled_{{ $asset->id ?? $asset->type }}" type="checkbox" name="is_enabled" value="1" @checked($asset->is_enabled)>
                                <span>{{ $pageAssetsText('enabled') }}</span>
                            </label>
                        </div>
                    </div>

                    @if ($isJs)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3">
                                <strong>{{ $pageAssetsText('javascript_options') }}</strong>
                                <div class="wb-grid wb-grid-3 wb-gap-3">
                                    <label class="wb-check" for="page_asset_defer_{{ $asset->id ?? $asset->type }}">
                                        <input type="hidden" name="is_defer" value="0">
                                        <input id="page_asset_defer_{{ $asset->id ?? $asset->type }}" type="checkbox" name="is_defer" value="1" @checked($asset->is_defer)>
                                        <span>{{ $pageAssetsText('defer') }}</span>
                                    </label>

                                    <label class="wb-check" for="page_asset_async_{{ $asset->id ?? $asset->type }}">
                                        <input type="hidden" name="is_async" value="0">
                                        <input id="page_asset_async_{{ $asset->id ?? $asset->type }}" type="checkbox" name="is_async" value="1" @checked($asset->is_async)>
                                        <span>{{ $pageAssetsText('async') }}</span>
                                    </label>

                                    <label class="wb-check" for="page_asset_module_{{ $asset->id ?? $asset->type }}">
                                        <input type="hidden" name="is_module" value="0">
                                        <input id="page_asset_module_{{ $asset->id ?? $asset->type }}" type="checkbox" name="is_module" value="1" @checked($asset->is_module)>
                                        <span>{{ $pageAssetsText('module') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$submitLabel"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
</div>
