@php
    $modalId = 'page-import-modal';
    $modalTitleId = $modalId.'-title';
    $modalDescriptionId = $modalId.'-description';
    $isOpen = ($pageImportOpen ?? false) || old('_page_import_modal') === $modalId;
    $selectedSiteId = (int) old('site_id', $pageImportSelectedSiteId ?? 0);
    $pageImportLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pageImportText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_import.'.$key, $pageImportLocale, $replace);
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-wb-admin-close-url="{{ $closeUrl }}" @if ($isOpen) data-wb-admin-autoload-overlay hidden @else hidden @endif>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $pageImportText('title') }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $pageImportText('description') }}</span>
                </div>
                <a href="{{ $closeUrl }}" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $pageImportText('close_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.pages.import.store') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4" data-wb-admin-dirty-form data-wb-admin-dirty-close-confirm="{{ $pageImportText('discard_changes') }}">
                @csrf

                <input type="hidden" name="_page_import_modal" value="{{ $modalId }}">
                <input type="hidden" name="return_url" value="{{ $pageReturnUrl }}">

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any() && $isOpen)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $pageImportText('import_error') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-field wb-stack-2">
                        <label for="page_import_site_id">{{ $pageImportText('site') }}</label>
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
                        <label for="page_import_json_file">{{ $pageImportText('json_file') }}</label>
                        <input id="page_import_json_file" name="json_file" class="wb-input" type="file" accept="application/json,.json,text/plain" required>
                        <div class="wb-text-sm wb-text-muted">{{ $pageImportText('json_help') }}</div>
                        @error('json_file')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-field wb-stack-2">
                        <label class="wb-check" for="page_import_as_draft">
                            <input id="page_import_as_draft" type="checkbox" name="import_as_draft" value="1" checked disabled>
                            <span>{{ $pageImportText('import_as_draft') }}</span>
                        </label>
                        <input type="hidden" name="import_as_draft" value="1">
                        <div class="wb-text-sm wb-text-muted">{{ $pageImportText('draft_help') }}</div>
                    </div>
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $pageImportText('cancel') }}</a>
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $pageImportText('import_page') }}</button>
                </div>
            </form>
        </div>
</div>
