@if ($translationRemovals !== [])
    @php
        $revisionRemovalLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
        $revisionRemovalText = static fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('page_revisions.'.$key, $revisionRemovalLocale);
    @endphp
    <div class="wb-alert wb-alert-warning wb-m-0" role="alert">
        <div>
            <strong>{{ $revisionRemovalText('translation_removal_title') }}</strong>
            <p>{{ $revisionRemovalText('translation_removal_help') }}</p>
            <ul>
                @foreach ($translationRemovals as $translation)
                    <li>{{ $translation['name'] }} ({{ $translation['code'] }}) — {{ $translation['path'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
