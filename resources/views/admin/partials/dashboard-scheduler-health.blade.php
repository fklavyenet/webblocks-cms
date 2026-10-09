        @if (collect($notificationHealthSites ?? [])->isNotEmpty())
            <section class="wb-card">
                <div class="wb-card-header"><strong>{{ __('webblocks-cms::notifications.health_title') }}</strong></div>
                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.scheduler-health', ['site' => $notificationHealthSites->first()['site'], 'schedulerHealth' => $dashboardSchedulerHealth])
                    <ul>
                        @foreach ($notificationHealthSites as $healthSite)
                            <li><a href="{{ route('admin.sites.edit', ['site' => $healthSite['site'], 'tab' => 'contact']) }}">{{ $healthSite['site']->publicDisplayName() ?: $healthSite['site']->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
