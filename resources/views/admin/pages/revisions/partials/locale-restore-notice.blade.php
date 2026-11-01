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
            <p>{{ $localeRestoreText('proposed_url') }}: {{ $localeRestoreReview['proposed_url'] ?? '—' }}</p>
            <p>{{ $localeRestoreText('proposed_path') }}: {{ $localeRestoreTranslation['path'] ?? '—' }}</p>
            <p>{{ $localeRestoreText('excluded') }}</p>
        </div>
    </div>
@endif
