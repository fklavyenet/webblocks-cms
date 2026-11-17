<div class="wb-cluster wb-cluster-2 wb-flex-wrap">
    @if ($dashboardAccess['create'])
        <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary wb-btn-sm">
            <i class="wb-icon wb-icon-plus" aria-hidden="true"></i>
            {{ $adminText('dashboard.new_page') }}
        </a>
    @endif
    @if ($dashboardAccess['pages'] || $dashboardAccess['shared'] || $canAccessSystem)
        <div class="wb-dropdown wb-dropdown-end">
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-toggle="dropdown" data-wb-target="#dashboard-quick-actions" aria-controls="dashboard-quick-actions" aria-expanded="false">
                {{ $adminText('dashboard.quick_actions') }}
                <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="wb-dropdown-menu" id="dashboard-quick-actions">
                @if ($dashboardAccess['pages'])<a href="{{ route('admin.pages.index') }}" class="wb-dropdown-item">{{ $adminText('dashboard.pages') }}</a>@endif
                @if ($dashboardAccess['shared'])<a href="{{ route('admin.shared-slots.index') }}" class="wb-dropdown-item">{{ $adminText('dashboard.shared_slots') }}</a>@endif
                @if ($canAccessSystem)
                    <a href="{{ route('admin.sites.index') }}" class="wb-dropdown-item">{{ $adminText('dashboard.sites') }}</a>
                    <a href="{{ route('admin.system.backups.index') }}" class="wb-dropdown-item">{{ $adminText('dashboard.backups') }}</a>
                    <a href="{{ route('admin.system.updates.index') }}" class="wb-dropdown-item">{{ $adminText('dashboard.update') }}</a>
                @endif
            </div>
        </div>
    @endif
</div>
