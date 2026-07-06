@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = $adminLocale ?? app(AdminLocaleResolver::class)->locale();
    $adminTranslator = $adminTranslator ?? app(CmsTranslator::class);
    $adminText = $adminText ?? static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $modalId = $modalId ?? 'siteTransferExportModal';
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $selectedSite = $selectedSite ?? null;
    $sites = $sites ?? collect();
    $show = $show ?? false;
    $modalTitle = $modalTitle ?? $adminText('site_transfers.export_site');
    $modalDescription = $modalDescription ?? $adminText('site_transfers.export_site_default_description');
    $closeUrl = $closeUrl ?? route('admin.site-transfers.exports.index');
    $formAction = $formAction ?? route('admin.site-transfers.exports.store');
    $modalKey = $modalKey ?? 'create-export';
    $siteFieldName = $siteFieldName ?? 'site_id';
    $selectedSiteId = old($siteFieldName, $selectedSite?->id);
    $hasExportErrors = $errors->has('site_export') || $errors->has($siteFieldName) || $errors->has('includes_media');
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $show) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $show ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $modalTitle }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $modalDescription }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('site_transfers.close_export_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-4">
                @csrf
                <input type="hidden" name="_site_export_modal" value="{{ $modalKey }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($show && $hasExportErrors)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('site_transfers.export_error') }}</div>
                                <div>{{ $errors->first('site_export') ?: $errors->first($siteFieldName) ?: $errors->first('includes_media') }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($selectedSite)
                        <input type="hidden" name="{{ $siteFieldName }}" value="{{ $selectedSite->id }}">

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.site_name') }}</span>
                                    <strong>{{ $selectedSite->name }}</strong>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.site_handle') }}</span>
                                    <code>{{ $selectedSite->handle }}</code>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2 wb-field">
                            <label for="{{ $modalId }}SiteId">{{ $adminText('site_transfers.site') }}</label>
                            <select id="{{ $modalId }}SiteId" name="{{ $siteFieldName }}" class="wb-select" required>
                                <option value="">{{ $adminText('site_transfers.select_site') }}</option>

                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) $selectedSiteId === $site->id)>{{ $site->name }} ({{ $site->handle }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="wb-stack wb-gap-2 wb-field">
                        <label class="wb-checkbox" for="{{ $modalId }}IncludesMedia">
                            <input id="{{ $modalId }}IncludesMedia" type="checkbox" name="includes_media" value="1" @checked(old('includes_media', true))>
                            <span>{{ $adminText('site_transfers.include_media_files') }}</span>
                        </label>

                        <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.include_media_help') }}</div>
                    </div>
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$adminText('site_transfers.export_site')"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
