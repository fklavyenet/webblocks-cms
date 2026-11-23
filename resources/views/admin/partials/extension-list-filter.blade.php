<div class="wb-card wb-card-muted">
    <div class="wb-card-body">
        @include('webblocks-cms::admin.partials.listing-filters', [
            'action' => $indexUrl,
            'search' => [
                'id' => $id,
                'name' => 'search',
                'value' => $term,
                'label' => __('webblocks-cms::admin.common.search'),
                'placeholder' => __('webblocks-cms::admin.extensions_ui.search_placeholder'),
            ],
            'showReset' => $term !== '',
            'resetUrl' => $indexUrl,
        ])
    </div>
</div>
