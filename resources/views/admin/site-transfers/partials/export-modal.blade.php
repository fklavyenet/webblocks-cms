@php
    $modalId = $modalId ?? 'siteTransferExportModal';
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $selectedSite = $selectedSite ?? null;
    $sites = $sites ?? collect();
    $show = $show ?? false;
    $modalTitle = $modalTitle ?? 'Export Site';
    $modalDescription = $modalDescription ?? 'Create a portable site export package using the current site transfer workflow.';
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

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close export site modal">
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
                                <div class="wb-alert-title">Export Error</div>
                                <div>{{ $errors->first('site_export') ?: $errors->first($siteFieldName) ?: $errors->first('includes_media') }}</div>
                            </div>
                        </div>
                    @endif

                    @if ($selectedSite)
                        <input type="hidden" name="{{ $siteFieldName }}" value="{{ $selectedSite->id }}">

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-grid wb-grid-2">
                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">Site name</span>
                                    <strong>{{ $selectedSite->name }}</strong>
                                </div>

                                <div class="wb-stack wb-gap-1">
                                    <span class="wb-text-sm wb-text-muted">Site handle</span>
                                    <code>{{ $selectedSite->handle }}</code>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="wb-stack wb-gap-2 wb-field">
                            <label for="{{ $modalId }}SiteId">Site</label>
                            <select id="{{ $modalId }}SiteId" name="{{ $siteFieldName }}" class="wb-select" required>
                                <option value="">Select a site</option>

                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) $selectedSiteId === $site->id)>{{ $site->name }} ({{ $site->handle }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="wb-stack wb-gap-2 wb-field">
                        <label class="wb-checkbox" for="{{ $modalId }}IncludesMedia">
                            <input id="{{ $modalId }}IncludesMedia" type="checkbox" name="includes_media" value="1" @checked(old('includes_media', true))>
                            <span>Include media/assets</span>
                        </label>

                        <div class="wb-text-sm wb-text-muted">When enabled, the export package includes referenced asset records and the actual files from CMS-managed storage.</div>
                    </div>
                </div>

                <x-admin.form-actions
                    :cancel-url="$closeUrl"
                    submit-label="Export Site"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
