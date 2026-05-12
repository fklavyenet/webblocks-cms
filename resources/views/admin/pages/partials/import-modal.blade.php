@php
    $modalId = 'page-import-modal';
    $modalTitleId = $modalId.'-title';
    $modalDescriptionId = $modalId.'-description';
    $isOpen = ($pageImportOpen ?? false) || old('_page_import_modal') === $modalId;
    $selectedSiteId = (int) old('site_id', $pageImportSelectedSiteId ?? 0);
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Import Page</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Import one single-page JSON payload into the selected site as a new draft page.</span>
                </div>
                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close page import modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.pages.import.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                @csrf

                <input type="hidden" name="_page_import_modal" value="{{ $modalId }}">
                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any() && $isOpen)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Import Error</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-field wb-stack-2">
                        <label for="page_import_site_id">Site</label>
                        <select id="page_import_site_id" name="site_id" class="wb-select" required>
                            @foreach ($importSites as $site)
                                <option value="{{ $site->id }}" @selected($selectedSiteId === (int) $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                        @error('site_id')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-field wb-stack-2">
                        <label for="page_import_json_file">JSON file</label>
                        <input id="page_import_json_file" name="json_file" class="wb-input" type="file" accept="application/json,.json,text/plain" required>
                        <div class="wb-text-sm wb-text-muted">Use the documented `webblocks.cms.page.v1` payload schema. V1 always creates a new page and always imports as draft.</div>
                        @error('json_file')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-field wb-stack-2">
                        <label class="wb-checkbox" for="page_import_as_draft">
                            <input id="page_import_as_draft" type="checkbox" name="import_as_draft" value="1" checked disabled>
                            <span>Import as draft</span>
                        </label>
                        <input type="hidden" name="import_as_draft" value="1">
                        <div class="wb-text-sm wb-text-muted">This first version always imports pages as draft for safety.</div>
                    </div>
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                    <button type="submit" class="wb-btn wb-btn-primary">Import Page</button>
                </div>
            </form>
        </div>
    </div>
</div>
