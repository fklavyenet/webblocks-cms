@if ($localeRestoreCandidate)
    @php
        $localeRestoreLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
        $localeRestoreText = static fn ($key, $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_revisions.locale_restore.'.$key, $localeRestoreLocale, $replace);
        $localeRestoreTranslation = collect($localeRestoreCandidate->revision->snapshot['translations'] ?? [])->firstWhere('locale_id', $localeRestoreCandidate->restore_locale_id);
        $localeRestoreReview = app(\WebBlocks\Cms\Support\Pages\PageLocaleRestorePlanner::class)->reviewCandidate($localeRestoreCandidate);
        $localeRestoreCode = \WebBlocks\Cms\Models\Locale::find($localeRestoreCandidate->restore_locale_id)?->code ?? '—';
    @endphp
    <div class="wb-alert wb-alert-info wb-m-0" role="status">
        <div>
            <strong>{{ $localeRestoreText('title') }}: {{ $localeRestoreCode }} — {{ $localeRestoreText('scopes.'.$localeRestoreCandidate->restore_scope) }}</strong>
            @foreach ($localeRestoreReview['issues'] as $localeRestoreIssue)
                <p role="alert">{{ $localeRestoreText('errors.'.$localeRestoreIssue['code']) }}</p>
            @endforeach
            <p>{{ $localeRestoreText('preserved') }}</p>
            <p>{{ $localeRestoreText('preview_languages') }}</p>
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $localeRestoreText('proposed_url') }}">
                    <tbody>
                        <tr><th scope="row">{{ $localeRestoreText('proposed_url') }}</th><td>{{ $localeRestoreReview['proposed_url'] ?? '—' }}</td></tr>
                        <tr><th scope="row">{{ rtrim($localeRestoreText('proposed_path'), ': ') }}</th><td>{{ $localeRestoreTranslation['path'] ?? '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
            <p>{{ $localeRestoreText('excluded') }}</p>
        </div>
    </div>
@endif
