<section class="wb-card" data-plugin-dashboard-widget="{{ $widget->key() }}" data-plugin-handle="{{ $widget->pluginHandle() }}">
    @if ($widget->viewName() !== null)
        @include($widget->viewName(), $widget->viewData())
    @else
        <div class="wb-card-header"><strong>{{ $widget->titleText() }}</strong></div>
        <div class="wb-card-body wb-stack wb-stack-2">
            @if ($widget->valueText() !== null)<strong>{{ $widget->valueText() }}</strong>@endif
            @if ($widget->descriptionText() !== null)<div class="wb-text-sm wb-text-muted">{{ $widget->descriptionText() }}</div>@endif
            @if ($widget->urlValue() !== null)<a href="{{ $widget->urlValue() }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.open') }}</a>@endif
        </div>
    @endif
</section>
