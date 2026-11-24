<article class="wb-card wb-card-muted wb-api-token-activity-item" role="listitem">
    <div class="wb-card-body wb-stack wb-gap-3">
        <div class="wb-stack wb-gap-2">
            <div class="wb-cluster wb-gap-2 wb-flex-wrap">
                <span class="wb-status-pill {{ $activity->statusBadgeClass() }}">{{ $activity->statusLabel() }}</span>
                <span class="wb-text-sm wb-text-muted">{{ $activity->occurredAtLabel() }}</span>
            </div>

            <code class="wb-api-token-activity-request">
                <span class="wb-api-token-activity-method">{{ $activity->method }}</span>
                <span>{{ $activity->path }}</span>
            </code>

            @if ($activity->route_name)
                <span class="wb-text-sm wb-text-muted wb-api-token-activity-route">{{ $activity->route_name }}</span>
            @endif
        </div>

        <div class="wb-table-wrap">
            <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $activityText('activity_title') }}">
                <tbody>
                    <tr><th scope="row">{{ $activityText('client') }}</th><td>{{ $activity->ip ?? $activityText('unknown_ip') }}</td></tr>
                    <tr>
                        <th scope="row">{{ $activityText('capability') }}</th>
                        <td>
                            @if ($activity->required_capability)
                                <code>{{ $activity->required_capability }}</code>
                            @else
                                <span class="wb-text-muted">{{ $activityText('none') }}</span>
                            @endif
                        </td>
                    </tr>
                    @if ($activity->user_agent)
                        <tr>
                            <th scope="row">{{ $activityText('user_agent') }}</th>
                            <td>
                                <details class="wb-collapse-panel">
                                    <summary class="wb-collapse-trigger">{{ __('webblocks-cms::admin.record_tables.full_value') }}</summary>
                                    <div class="wb-collapse-body">{{ $activity->user_agent }}</div>
                                </details>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</article>
