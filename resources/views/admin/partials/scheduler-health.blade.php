@php($schedulerHealth = $schedulerHealth ?? app(\WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth::class)->forSite($site))
<div class="wb-alert {{ $schedulerHealth['required'] && in_array($schedulerHealth['status'], ['unverified', 'delayed', 'failed', 'unavailable'], true) ? 'wb-alert-warning' : 'wb-alert-info' }}">
<strong>{{ $schedulerHealth['required'] ? __('webblocks-cms::notifications.health_title').': '.__('webblocks-cms::notifications.health_'.$schedulerHealth['status']) : __('webblocks-cms::jobs.manual_profile') }}</strong>
<p>{{ __('webblocks-cms::notifications.panel_delivery_help') }}</p>
@if ($schedulerHealth['required'] && in_array($schedulerHealth['status'], ['unverified', 'delayed', 'failed', 'unavailable'], true))
<p>{{ __('webblocks-cms::notifications.health_impact') }}</p>
@endif
@if (! $schedulerHealth['required'])
<p>{{ __('webblocks-cms::notifications.health_notifications_optional') }}</p>
@endif
@if ($schedulerHealth['required'])
<div class="wb-table-wrap">
    <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ __('webblocks-cms::notifications.health_title') }}">
        <tbody>
            <tr><th scope="row">{{ __('webblocks-cms::notifications.health_last_seen') }}</th><td>{{ $schedulerHealth['last_seen_at'] ?? __('webblocks-cms::notifications.health_no_record') }}</td></tr>
            <tr><th scope="row">{{ __('webblocks-cms::notifications.health_last_completed') }}</th><td>{{ $schedulerHealth['last_completed_at'] ?? __('webblocks-cms::notifications.health_no_record') }}</td></tr>
        </tbody>
    </table>
</div>
<p>{{ __('webblocks-cms::notifications.health_setup') }}</p>
<p><a class="wb-link" href="{{ app(\WebBlocks\Cms\Support\Admin\DocumentationUrlResolver::class)->url(app()->getLocale()) }}/installation#scheduled-notifications-and-scheduler-health">{{ __('webblocks-cms::notifications.health_guide') }}</a></p>
@endif
</div>

@if (! empty($schedulerSetupUrl))
<p><a class="wb-link" href="{{ $schedulerSetupUrl }}">{{ __('webblocks-cms::scheduler.title') }}</a></p>
@endif
