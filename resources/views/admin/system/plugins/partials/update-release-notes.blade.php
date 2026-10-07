<section class="wb-stack wb-stack-3" aria-labelledby="{{ $updateModalId }}-release-notes">
    <h3 class="wb-m-0" id="{{ $updateModalId }}-release-notes">{{ $systemPluginsIndexText('update_release_notes') }}</h3>

    @if (! empty($plugin['catalog_update']['summary']))
        <p class="wb-mb-0">{{ $plugin['catalog_update']['summary'] }}</p>
    @endif

    @if (! empty($plugin['catalog_update']['note_items']))
        <div>
            <ul class="wb-marker-list wb-text-sm">
                @foreach ($plugin['catalog_update']['note_items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @elseif (empty($plugin['catalog_update']['summary']))
        <p class="wb-text-sm wb-text-muted wb-mb-0">{{ $systemPluginsIndexText('update_release_notes_unavailable') }}</p>
    @endif

    @if (! empty($plugin['catalog_update']['details_url']))
        <div class="wb-cluster">
            <a class="wb-btn wb-btn-secondary wb-btn-sm" href="{{ $plugin['catalog_update']['details_url'] }}" target="_blank" rel="noopener noreferrer">
                <i class="wb-icon wb-icon-external-link" aria-hidden="true"></i>{{ $systemPluginsIndexText('update_release_notes_link') }}
            </a>
        </div>
    @endif
</section>
