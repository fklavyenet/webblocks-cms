<article class="wb-card wb-cms-catalog-card">
    @if (isset($artworkUrl))
        <a href="{{ $detailUrl }}" class="wb-card-media wb-card-media--aspect-wide">
            <img src="{{ $artworkUrl }}" alt="{{ $artworkAlt }}" loading="lazy">
        </a>
    @elseif (isset($coverIcon))
        <div class="wb-card-media wb-card-media--aspect-wide wb-cms-catalog-cover" aria-hidden="true">
            <i class="wb-icon wb-icon-{{ $coverIcon }}"></i>
        </div>
    @endif

    <div class="wb-card-body wb-stack wb-stack-2">
        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-cms-catalog-heading">
            <h2 class="wb-card-title" @isset($language) lang="{{ $language }}" @endisset>
                @isset($detailUrl)
                    <a href="{{ $detailUrl }}">{{ $name }}</a>
                @else
                    {{ $name }}
                @endisset
            </h2>
            @isset($titleBadge)
                <span class="wb-badge">{{ $titleBadge }}</span>
            @endisset
        </div>
        {{ $slot }}
        @isset($metadata)
            <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted wb-cms-catalog-meta">
                {{ $metadata }}
            </div>
        @endisset
    </div>

    @isset($actions)
        <div class="wb-card-footer">{{ $actions }}</div>
    @endisset
</article>
