@php($schedulerHealth = $schedulerHealth ?? app(\WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth::class)->forSite($site))
<div class="wb-alert {{ $schedulerHealth['required'] && in_array($schedulerHealth['status'], ['unverified', 'delayed', 'failed', 'unavailable'], true) ? 'wb-alert-warning' : 'wb-alert-info' }}">
<strong>{{ __('webblocks-cms::notifications.health_title') }}: {{ __('webblocks-cms::notifications.health_'.$schedulerHealth['status']) }}</strong>
<p>{{ __('webblocks-cms::notifications.panel_delivery_help') }}</p>
@if ($schedulerHealth['required'] && in_array($schedulerHealth['status'], ['unverified', 'delayed', 'failed', 'unavailable'], true))
<p>{{ __('webblocks-cms::notifications.health_impact') }}</p>
@endif
@if (! $schedulerHealth['required'])
<p>{{ __('webblocks-cms::notifications.health_notifications_optional') }}</p>
@endif
<p>{{ __('webblocks-cms::notifications.health_last_seen') }}: {{ $schedulerHealth['last_seen_at'] ?? __('webblocks-cms::notifications.health_no_record') }}</p>
<p>{{ __('webblocks-cms::notifications.health_last_completed') }}: {{ $schedulerHealth['last_completed_at'] ?? __('webblocks-cms::notifications.health_no_record') }}</p>
<p>{{ __('webblocks-cms::notifications.health_setup') }}</p>
<p><a class="wb-link" href="{{ rtrim(config('webblocks-cms.admin.documentation_url', 'https://cms.webblocksui.com'), '/') }}/docs/installation#scheduled-notifications-and-scheduler-health">{{ __('webblocks-cms::notifications.health_guide') }}</a></p>
</div>
