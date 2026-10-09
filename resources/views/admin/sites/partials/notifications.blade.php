@php($notificationPolicy = \WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy::forSite($site))
@include('webblocks-cms::admin.partials.scheduler-health', ['site' => $site])
<h3>{{ __('webblocks-cms::notifications.settings_title') }}</h3>
<p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::notifications.settings_help') }}</p>
@foreach (['notification_mode' => ['alert_only', 'full'], 'notification_frequency' => ['immediate', 'batched', 'daily']] as $field => $choices)
<div class="wb-field wb-stack wb-gap-2">
<label for="site-{{ $field }}">{{ __('webblocks-cms::notifications.'.$field) }}</label>
<select id="site-{{ $field }}" name="notification_settings[{{ $field }}]" class="wb-input" @disabled($isReadOnly)>
@foreach ($choices as $choice)
<option value="{{ $choice }}" @selected(old('notification_settings.'.$field, $notificationPolicy[$field]) === $choice)>{{ __('webblocks-cms::notifications.'.$choice) }}</option>
@endforeach
</select>
</div>
@endforeach
@foreach (['batch_minutes' => [1, 60], 'summary_hour' => [0, 23]] as $field => $limits)
<div class="wb-field wb-stack wb-gap-2">
<label for="site-{{ $field }}">{{ __('webblocks-cms::notifications.'.$field) }}</label>
<input id="site-{{ $field }}" type="number" name="notification_settings[{{ $field }}]" class="wb-input" min="{{ $limits[0] }}" max="{{ $limits[1] }}" value="{{ old('notification_settings.'.$field, $notificationPolicy[$field]) }}" @disabled($isReadOnly)>
</div>
@endforeach
<label class="wb-check">
<input type="hidden" name="notification_settings[daily_summary]" value="0" @disabled($isReadOnly)>
<input type="checkbox" name="notification_settings[daily_summary]" value="1" @checked(old('notification_settings.daily_summary', $notificationPolicy['daily_summary'])) @disabled($isReadOnly)>
<span>{{ __('webblocks-cms::notifications.daily_summary') }}</span>
</label>
<p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::notifications.schedule_help') }}</p>
@if ($site->exists)
@foreach (['delivery_status' => \WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy::deliveryStatus($site), 'summary_delivery_status' => \WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy::summaryDeliveryStatus($site)] as $heading => $outcomes)
@if ($outcomes !== [])
<h4>{{ __('webblocks-cms::notifications.'.$heading) }}</h4>
<ul>
@foreach ($outcomes as $outcome => $count)
<li>{{ __('webblocks-cms::notifications.status_'.$outcome) }}: {{ $count }}</li>
@endforeach
</ul>
@endif
@endforeach
@endif
