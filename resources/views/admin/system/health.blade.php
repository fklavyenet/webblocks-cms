@php
    $locale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $text = static fn (string $key, array $parameters = []) => $translator->get('system_health.'.$key, $locale, $parameters);
    $message = static fn (array $check) => $text('messages.'.$check['message'], $check['parameters']);
    $badge = static fn (string $status) => match ($status) {
        'healthy' => 'wb-status-active', 'critical' => 'wb-status-error', 'warning' => 'wb-status-pending', default => 'wb-status-inactive',
    };
    $link = static fn (array $check) => route($check['route'], $check['route_parameters']);
    $date = static fn (string $value) => \Carbon\CarbonImmutable::parse($value)->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d H:i');
    $siteOptions = collect($health['site_options'])->pluck('name', 'id')->all();
    $priority = array_slice($health['issues'], 0, 6);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description')])
    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-card">
            <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-4 wb-flex-wrap">
                <div class="wb-stack wb-stack-1">
                    <strong>{{ $text('checked_at', ['time' => $date($health['checked_at'])]) }}</strong>
                    <span class="wb-text-sm wb-text-muted">{{ $text('snapshot_help') }}</span>
                </div>
                <form method="POST" action="{{ route('admin.system.health.refresh') }}">
                    @csrf
                    @if ($health['selected_site_id'])
                        <input type="hidden" name="site_id" value="{{ $health['selected_site_id'] }}">
                    @endif
                    <button type="submit" class="wb-btn wb-btn-secondary"><i class="wb-icon wb-icon-refresh-cw" aria-hidden="true"></i> {{ $text('refresh') }}</button>
                </form>
            </div>
        </div>

        @include('webblocks-cms::admin.partials.listing-filters', [
            'action' => route('admin.system.health.index'),
            'selects' => [['id' => 'health-site', 'name' => 'site_id', 'label' => $text('site_filter'), 'placeholder' => $text('all_sites'), 'options' => $siteOptions, 'selected' => $health['selected_site_id']]],
            'showReset' => $health['selected_site_id'] !== null,
            'resetUrl' => route('admin.system.health.index'),
        ])
        <p class="wb-text-sm wb-text-muted">{{ $text('filter_help') }}</p>

        <div class="wb-grid wb-grid-3 wb-gap-4">
            @foreach (['critical', 'warning', 'unknown'] as $status)
                <div class="wb-card">
                    <div class="wb-card-body wb-stack wb-stack-2">
                        <span class="wb-text-sm wb-text-muted">{{ $text('summary.'.$status) }}</span>
                        <strong class="wb-text-2xl">{{ number_format($health['counts'][$status]) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>

        <section class="wb-card" aria-labelledby="health-attention-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="health-attention-title">{{ $text('attention') }}</h2></div>
            @if ($priority === [])
                <div class="wb-card-body"><div class="wb-alert wb-alert-success"><div>{{ $text('no_attention') }}</div></div></div>
            @else
                <div class="wb-card-body"><div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        <thead><tr><th scope="col">{{ $text('area') }}</th><th scope="col">{{ $text('what_to_review') }}</th><th scope="col" class="wb-table-actions">{{ $translator->admin('common.actions', $locale) }}</th></tr></thead>
                        <tbody>
                            @foreach ($priority as $issue)
                                <tr>
                                    <td><strong>{{ $text('categories.'.$issue['category']) }}</strong>@if (isset($issue['site_name']))<br><span class="wb-text-sm wb-text-muted">{{ $issue['site_name'] }}</span>@endif</td>
                                    <td><div class="wb-stack wb-stack-2"><div class="wb-cluster wb-cluster-2"><span class="wb-status-pill {{ $badge($issue['status']) }}">{{ $text('statuses.'.$issue['status']) }}</span></div><span>{{ $message($issue) }}</span></div></td>
                                    <td class="wb-table-actions"><div class="wb-action-group"><a href="{{ $link($issue) }}" class="wb-action-btn wb-action-btn-view" title="{{ $text('review') }}" aria-label="{{ $text('review') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div></div>
                @if (count($health['issues']) > count($priority))
                    <div class="wb-card-footer wb-text-sm wb-text-muted">{{ $text('more_checks', ['shown' => count($priority), 'total' => count($health['issues'])]) }}</div>
                @endif
            @endif
        </section>

        <div class="wb-grid wb-grid-2 wb-gap-4">
            @foreach ($health['categories'] as $category => $result)
                @php($lead = $result['lead'])
                <section class="wb-card">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap"><h2 class="wb-card-title">{{ $text('categories.'.$category) }}</h2><span class="wb-status-pill {{ $badge($result['status']) }}">{{ $text('statuses.'.$result['status']) }}</span></div>
                    <div class="wb-card-body wb-stack wb-stack-3">
                        <p>{{ $message($lead) }}</p>
                        <div class="wb-accordion" data-wb-accordion>
                            <div class="wb-accordion-item">
                                <button type="button" class="wb-accordion-trigger" aria-expanded="false" aria-controls="health-{{ $category }}-details"><span>{{ $text('details') }}</span><i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i></button>
                                <div class="wb-accordion-content" id="health-{{ $category }}-details"><div class="wb-accordion-body wb-stack wb-stack-3">
                                    @foreach ($result['checks'] as $check)
                                        <div class="wb-stack wb-stack-1">
                                            <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap"><span class="wb-status-pill {{ $badge($check['status']) }}">{{ $text('statuses.'.$check['status']) }}</span><a href="{{ $link($check) }}">{{ $text('review') }}</a></div>
                                            @if (isset($check['site_name']))<strong>{{ $check['site_name'] }}</strong>@endif
                                            <p class="wb-text-sm">{{ $message($check) }}</p>
                                        </div>
                                    @endforeach
                                </div></div>
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <section class="wb-card" aria-labelledby="health-sites-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="health-sites-title">{{ $text('sites_title') }}</h2></div>
            @if ($health['sites'] === [])
                <div class="wb-card-body wb-text-muted">{{ $message($health['categories']['sites']['lead']) }}</div>
            @else
                <div class="wb-card-body"><div class="wb-table-wrap"><table class="wb-table wb-table-striped">
                    <thead><tr><th scope="col">{{ $text('site') }}</th><th scope="col">{{ $text('status') }}</th><th scope="col">{{ $text('content') }}</th><th scope="col">{{ $text('what_to_review') }}</th><th scope="col" class="wb-table-actions">{{ $translator->admin('common.actions', $locale) }}</th></tr></thead>
                    <tbody>
                        @foreach ($health['sites'] as $site)
                            @php($lead = $site['lead'])
                            <tr><th scope="row">{{ $site['name'] }}</th><td><span class="wb-status-pill {{ $badge($site['status']) }}">{{ $text('statuses.'.$site['status']) }}</span></td><td>{{ $text('page_count', ['published' => $site['published_pages'], 'total' => $site['pages']]) }}<br><span class="wb-text-sm wb-text-muted">{{ $text('history_size', ['size' => number_format($site['history_bytes'] / 1048576, 1).' MB']) }}</span></td><td>{{ $site['status'] === 'healthy' ? $text('site_ready') : $message($lead) }}</td><td class="wb-table-actions"><div class="wb-action-group"><a class="wb-action-btn wb-action-btn-view" href="{{ $link($lead) }}" title="{{ $text('review') }}" aria-label="{{ $text('review') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a></div></td></tr>
                        @endforeach
                    </tbody>
                </table></div></div>
            @endif
        </section>

        <div class="wb-card"><div class="wb-card-body">
            <div class="wb-accordion" data-wb-accordion>
                <div class="wb-accordion-item">
                    <button type="button" class="wb-accordion-trigger" aria-expanded="false" aria-controls="health-history"><span>{{ $text('recent_operations') }}</span><i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i></button>
                    <div class="wb-accordion-content" id="health-history"><div class="wb-accordion-body">
                        @if ($health['operations'] === [])
                            <p class="wb-text-muted">{{ $text('no_operations') }}</p>
                        @else
                            <div class="wb-table-wrap"><table class="wb-table"><thead><tr><th scope="col">{{ $text('operation') }}</th><th scope="col">{{ $text('status') }}</th><th scope="col">{{ $text('date') }}</th><th scope="col" class="wb-table-actions">{{ $translator->admin('common.actions', $locale) }}</th></tr></thead><tbody>
                                @foreach ($health['operations'] as $operation)
                                    <tr><td>{{ $text('operations.'.$operation['type']) }}</td><td><span class="wb-status-pill {{ $badge($operation['status']) }}">{{ $text('outcomes.'.$operation['outcome']) }}</span></td><td>{{ $date($operation['at']) }}</td><td class="wb-table-actions"><div class="wb-action-group"><a href="{{ route($operation['route']) }}" class="wb-action-btn wb-action-btn-view" title="{{ $text('review') }}" aria-label="{{ $text('review') }}"><i class="wb-icon wb-icon-eye" aria-hidden="true"></i></a></div></td></tr>
                                @endforeach
                            </tbody></table></div>
                        @endif
                    </div></div>
                </div>
                <div class="wb-accordion-item">
                    <button type="button" class="wb-accordion-trigger" aria-expanded="false" aria-controls="health-information"><span>{{ $text('system_details') }}</span><i class="wb-icon wb-icon-chevron-down wb-accordion-icon" aria-hidden="true"></i></button>
                    <div class="wb-accordion-content" id="health-information"><div class="wb-accordion-body">
                        <div class="wb-table-wrap"><table class="wb-table wb-table-striped"><thead><tr><th scope="col">{{ $translator->admin('system_information.property', $locale) }}</th><th scope="col">{{ $translator->admin('system_information.value', $locale) }}</th></tr></thead><tbody>
                            @foreach ($health['information'] as $key => $value)
                                <tr><th scope="row">{{ $translator->admin('system_information.'.$key, $locale) }}</th><td>{{ $key === 'debug_mode' ? $translator->admin('system_information.'.($value ? 'enabled' : 'disabled'), $locale) : $value }}</td></tr>
                            @endforeach
                        </tbody></table></div>
                    </div></div>
                </div>
            </div>
        </div></div>
    </div>
@endsection
