<div class="wb-page-header">
    <div class="wb-page-header-main">
        @isset($breadcrumb)
            {!! $breadcrumb !!}
        @endisset

        <div class="wb-cluster wb-cluster-2">
            <h1 class="wb-page-header-title">{{ $title }}</h1>

            @isset($count)
                <span class="wb-status-pill wb-status-info">{{ $count }}</span>
            @endisset
        </div>

        @isset($description)
            <p class="wb-page-subtitle">{{ $description }}</p>
        @endisset

        @isset($context)
            <div class="wb-page-subtitle">
                {!! $context !!}
            </div>
        @endisset
    </div>

    @isset($actions)
        <div class="wb-page-actions">
            {!! $actions !!}
        </div>
    @endisset
</div>
