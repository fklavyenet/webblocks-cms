@php
    $selects = [[ 'id' => 'engagement_site', 'name' => 'site', 'label' => $adminText('site'), 'selected' => $filters['site'] ?? '', 'placeholder' => $adminText('all_sites'), 'options' => $sites->pluck('name', 'id')->all() ]];
    if ($kind !== 'overview') {
        $selects[] = ['id' => 'engagement_page', 'name' => 'page_id', 'label' => $adminText('source'), 'selected' => $filters['page_id'] ?? '', 'placeholder' => $adminText('all_pages'), 'options' => $pages->mapWithKeys(fn ($page) => [$page->id => $page->title ?: $page->slug])->all()];
        $selects[] = $kind === 'comments'
            ? ['id' => 'engagement_status', 'name' => 'status', 'label' => $adminText('status'), 'selected' => $filters['status'] ?? '', 'placeholder' => $adminText('all_statuses'), 'options' => collect($statuses)->mapWithKeys(fn ($status) => [$status => $adminText('status_'.$status)])->all()]
            : ['id' => 'engagement_rating', 'name' => 'rating', 'label' => $adminText('rating'), 'selected' => $filters['rating'] ?? '', 'placeholder' => $adminText('all_ratings'), 'options' => collect($ratingOptions)->mapWithKeys(fn ($value) => [$value => (string) $value])->all()];
    } else {
        $selects[] = ['id' => 'engagement_sort', 'name' => 'sort', 'label' => $adminText('sort'), 'selected' => $filters['sort'] ?? 'pending', 'placeholder' => null, 'options' => collect(['pending', 'votes', 'average', 'activity'])->mapWithKeys(fn ($value) => [$value => $adminText('sort_'.$value)])->all()];
    }
@endphp
<div class="wb-card wb-card-muted"><div class="wb-card-body">
    @include('webblocks-cms::admin.partials.listing-filters', [
        'action' => $filterRoute,
        'search' => $kind === 'overview' ? null : ['id' => 'engagement_search', 'name' => 'search', 'label' => $adminText('search'), 'value' => $filters['search'] ?? '', 'placeholder' => $adminText($kind === 'comments' ? 'search_comments' : 'search_ratings')],
        'selects' => $selects,
        'dates' => $kind === 'overview' ? [] : [['id' => 'engagement_from', 'name' => 'from', 'label' => $adminText('from'), 'value' => $filters['from'] ?? ''], ['id' => 'engagement_until', 'name' => 'until', 'label' => $adminText('until'), 'value' => $filters['until'] ?? '']],
        'showReset' => count(array_filter($filters, fn ($value, $key) => $value !== '' && $key !== 'sort', ARRAY_FILTER_USE_BOTH)) > 0,
        'resetUrl' => $filterRoute,
        'applyLabel' => $adminText('apply'), 'resetLabel' => $adminText('clear_filters'),
    ])
</div></div>
